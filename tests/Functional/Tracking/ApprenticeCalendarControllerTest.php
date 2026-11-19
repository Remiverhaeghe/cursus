<?php

namespace App\Tests\Functional\Tracking;

use App\Calendar\ApprenticeCalendar;
use App\Controller\ApprenticeCalendarController;
use App\DataFixtures\UserFixtures;
use App\Entity\Apprenticeship;
use App\Entity\CalendarPeriod;
use App\Entity\Cohort;
use App\Entity\PlannedAssessment;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[CoversClass(ApprenticeCalendarController::class)]
#[CoversClass(ApprenticeCalendar::class)]
#[CoversClass(CalendarPeriod::class)]
#[CoversClass(PlannedAssessment::class)]
#[CoversClass(Cohort::class)]
final class ApprenticeCalendarControllerTest extends AppWebTestCase
{
    use ClockSensitiveTrait;

    private function url(string $pApprentice = UserFixtures::APPRENTICE): string
    {
        $vContract = $this->find(Apprenticeship::class, ['aApprentice' => $this->user($pApprentice)]);

        return '/suivi/'.$vContract->getId().'/calendrier';
    }

    /** @return iterable<string, array{string, int}> */
    public static function viewMatrix(): iterable
    {
        yield 'école responsable' => [UserFixtures::SCHOOL, 200];
        yield 'autre école' => [UserFixtures::SCHOOL_OTHER, 403];
        yield 'tuteur du contrat' => [UserFixtures::TUTOR, 200];
        yield 'autre tuteur' => [UserFixtures::TUTOR_OTHER, 403];
        yield 'apprenti concerné' => [UserFixtures::APPRENTICE, 200];
        yield 'autre apprenti' => [UserFixtures::APPRENTICE_SAME_TUTOR, 403];
    }

    #[DataProvider('viewMatrix')]
    public function testEachProfileSeesTheCalendarItIsAllowedTo(string $pEmail, int $pStatus): void
    {
        $this->loginAs($pEmail);

        $this->aClient->request('GET', $this->url());

        self::assertResponseStatusCodeSame($pStatus);
    }

    public function testApprenticeSeesWhereHeIsAndWhatIsComing(): void
    {
        // Le 10/03/2026 tombe dans la semaine d'école du 09 au 15/03 du rythme de démonstration
        self::mockTime('2026-03-10 09:00');
        $this->loginAs(UserFixtures::APPRENTICE);

        $vCrawler = $this->aClient->request('GET', $this->url());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#now', 'École jusqu\'au 15/03/2026.');
        self::assertSelectorTextContains('#now', 'Ensuite : entreprise à partir du 16/03/2026.');
        $vTitles = $vCrawler->filter('#upcoming tbody tr td:nth-child(2)')->each(static fn ($pCell): string => $pCell->text());
        self::assertSame(['Bilan de fin de première année', 'Épreuve blanche E6', 'Soutenance du projet en entreprise'], $vTitles);
        self::assertSelectorTextContains('#periods tr[aria-current="date"]', 'en cours');
        self::assertCount(70, $vCrawler->filter('#periods tbody tr'));
    }

    public function testCalendarWithoutPeriodsSaysSo(): void
    {
        self::mockTime('2026-09-01');
        $this->loginAs(UserFixtures::SCHOOL);
        $vCohort = $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']);
        foreach ($vCohort->getPeriods() as $vPeriod) {
            $this->entityManager()->remove($vPeriod);
        }
        foreach ($vCohort->getAssessments() as $vAssessment) {
            $this->entityManager()->remove($vAssessment);
        }
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $this->aClient->request('GET', $this->url());

        self::assertSelectorTextContains('#now', 'Aucune période prévue aujourd\'hui.');
        self::assertSelectorTextContains('main', 'Aucune évaluation prévue d\'ici la fin du contrat.');
        self::assertSelectorTextContains('main', 'L\'école n\'a pas encore saisi le calendrier.');
    }

    public function testTrackingPageLinksToCalendarAndExchanges(): void
    {
        $this->loginAs(UserFixtures::TUTOR);
        $vUrl = $this->url();

        $this->aClient->request('GET', substr($vUrl, 0, -\strlen('/calendrier')));

        self::assertSelectorExists('a[href="'.$vUrl.'"]');
        self::assertSelectorExists('a[href="'.substr($vUrl, 0, -\strlen('/calendrier')).'/echanges"]');
    }
}
