<?php

namespace App\Tests\Functional\School;

use App\Controller\School\CalendarController;
use App\DataFixtures\UserFixtures;
use App\Entity\CalendarPeriod;
use App\Entity\Cohort;
use App\Entity\PlannedAssessment;
use App\Enum\EvaluationContext;
use App\Form\CalendarPeriodType;
use App\Form\Data\CalendarPeriodData;
use App\Form\Data\PlannedAssessmentData;
use App\Form\PlannedAssessmentType;
use App\Security\Voter\FormationVoter;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CalendarController::class)]
#[CoversClass(CalendarPeriodType::class)]
#[CoversClass(PlannedAssessmentType::class)]
#[CoversClass(CalendarPeriodData::class)]
#[CoversClass(PlannedAssessmentData::class)]
#[CoversClass(CalendarPeriod::class)]
#[CoversClass(PlannedAssessment::class)]
#[CoversClass(Cohort::class)]
#[CoversClass(FormationVoter::class)]
final class CalendarControllerTest extends AppWebTestCase
{
    /** Promotion SIO de Claire : rythme saisi du 01/09/2025 au 31/08/2027. */
    private function cohort(): Cohort
    {
        return $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']);
    }

    private function url(): string
    {
        return '/ecole/promotions/'.$this->cohort()->getId().'/calendrier';
    }

    /** @return iterable<string, array{string, int}> */
    public static function accessMatrix(): iterable
    {
        yield 'école responsable' => [UserFixtures::SCHOOL, 200];
        yield 'autre école' => [UserFixtures::SCHOOL_OTHER, 403];
        yield 'tuteur' => [UserFixtures::TUTOR, 403];
        yield 'apprenti' => [UserFixtures::APPRENTICE, 403];
    }

    #[DataProvider('accessMatrix')]
    public function testOnlyTheResponsibleSchoolManagesTheCalendar(string $pEmail, int $pStatus): void
    {
        $this->loginAs($pEmail);

        $this->aClient->request('GET', $this->url());

        self::assertResponseStatusCodeSame($pStatus);
        if (200 === $pStatus) {
            self::assertSelectorTextContains('#periods tbody tr:first-child', '01/09/2025');
            self::assertSelectorTextContains('#assessments tbody tr:first-child', 'Épreuve blanche E5');
            self::assertSelectorExists('form[name="calendar_period"]');
            self::assertSelectorExists('form[name="planned_assessment"]');
        }
    }

    public function testCohortPageLinksToTheCalendar(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);

        $this->aClient->request('GET', '/ecole/promotions/'.$this->cohort()->getId());

        self::assertSelectorExists('a[href="'.$this->url().'"]');
    }

    public function testSchoolAddsAPeriodAfterTheRhythm(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vBefore = \count($this->cohort()->getPeriods());
        $this->aClient->request('GET', $this->url());

        $this->aClient->submitForm('Ajouter la période', [
            'calendar_period[place]' => 'school',
            'calendar_period[startDate]' => '2027-09-01',
            'calendar_period[endDate]' => '2027-09-05',
        ]);

        self::assertResponseRedirects($this->url());
        $this->entityManager()->clear();
        $vPeriods = $this->cohort()->getPeriods();
        self::assertCount($vBefore + 1, $vPeriods);
        $vLast = end($vPeriods);
        self::assertNotFalse($vLast);
        self::assertSame(EvaluationContext::School, $vLast->getPlace());
        self::assertSame('2027-09-05', $vLast->getEndDate()->format('Y-m-d'));
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Période ajoutée.');
    }

    public function testOverlappingPeriodIsRefused(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vBefore = \count($this->cohort()->getPeriods());
        $this->aClient->request('GET', $this->url());

        $this->aClient->submitForm('Ajouter la période', [
            'calendar_period[place]' => 'company',
            'calendar_period[startDate]' => '2027-08-30',
            'calendar_period[endDate]' => '2027-09-12',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="calendar_period"]', 'Cette période chevauche une période déjà prévue.');
        $this->entityManager()->clear();
        self::assertCount($vBefore, $this->cohort()->getPeriods());
    }

    public function testIncompleteFormsAreRefused(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $this->aClient->request('GET', $this->url());
        $this->aClient->submitForm('Ajouter la période', ['calendar_period[startDate]' => '2027-09-10', 'calendar_period[endDate]' => '2027-09-01']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="calendar_period"]', 'La période doit finir après son début.');

        $this->aClient->request('GET', $this->url());
        $this->aClient->submitForm('Prévoir l\'évaluation', ['planned_assessment[title]' => '', 'planned_assessment[date]' => '2027-06-21']);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager()->getRepository(PlannedAssessment::class)->findOneBy(['aDate' => new \DateTimeImmutable('2027-06-21')]));
    }

    public function testSchoolPlansAnAssessment(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $this->aClient->request('GET', $this->url());

        $this->aClient->submitForm('Prévoir l\'évaluation', [
            'planned_assessment[title]' => ' Conseil de classe ',
            'planned_assessment[place]' => 'school',
            'planned_assessment[date]' => '2027-06-21',
        ]);

        self::assertResponseRedirects($this->url());
        $vAssessment = $this->find(PlannedAssessment::class, ['aTitle' => 'Conseil de classe']);
        self::assertSame('2027-06-21', $vAssessment->getDate()->format('Y-m-d'));
        self::assertSame($this->cohort()->getId(), $vAssessment->getCohort()->getId());
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Évaluation prévue ajoutée.');
    }

    public function testSchoolDeletesAPeriodAndAnAssessment(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vPeriod = $this->cohort()->getPeriods()[0];
        $vAssessment = $this->cohort()->getAssessments()[0];
        $vPeriodId = $vPeriod->getId();
        $vAssessmentId = $vAssessment->getId();

        $this->aClient->request('GET', $this->url());
        $this->aClient->submit($this->aClient->getCrawler()->filter('form[action="/ecole/calendrier/periodes/'.$vPeriodId.'/suppression"]')->form());
        self::assertResponseRedirects($this->url());
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Période supprimée.');

        $this->aClient->submit($this->aClient->getCrawler()->filter('form[action="/ecole/calendrier/evaluations/'.$vAssessmentId.'/suppression"]')->form());
        self::assertResponseRedirects($this->url());
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Évaluation prévue supprimée.');

        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->find(CalendarPeriod::class, $vPeriodId));
        self::assertNull($this->entityManager()->find(PlannedAssessment::class, $vAssessmentId));
        self::assertSelectorTextNotContains('#assessments', 'Épreuve blanche E5');
    }

    /** @return iterable<string, array{string, class-string<CalendarPeriod|PlannedAssessment>}> */
    public static function deletions(): iterable
    {
        yield 'période' => ['periodes', CalendarPeriod::class];
        yield 'évaluation prévue' => ['evaluations', PlannedAssessment::class];
    }

    /** @param class-string<CalendarPeriod|PlannedAssessment> $pClass */
    #[DataProvider('deletions')]
    public function testDeletionNeedsTheTokenAndTheResponsibleSchool(string $pPath, string $pClass): void
    {
        $vEntity = $this->entityManager()->getRepository($pClass)->findOneBy(['aCohort' => $this->cohort()]);
        self::assertNotNull($vEntity);
        $vUrl = '/ecole/calendrier/'.$pPath.'/'.$vEntity->getId().'/suppression';

        $this->loginAs(UserFixtures::SCHOOL);
        $this->aClient->request('POST', $vUrl, ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);

        $this->loginAs(UserFixtures::SCHOOL_OTHER);
        $this->aClient->request('POST', $vUrl, ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);

        $this->entityManager()->clear();
        self::assertNotNull($this->entityManager()->find($pClass, $vEntity->getId()));
    }
}
