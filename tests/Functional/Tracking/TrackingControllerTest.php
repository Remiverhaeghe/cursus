<?php

namespace App\Tests\Functional\Tracking;

use App\Controller\TrackingController;
use App\DataFixtures\UserFixtures;
use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\Evaluation;
use App\Entity\Formation;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Form\EvaluationType;
use App\Repository\EvaluationRepository;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TrackingController::class)]
#[CoversClass(EvaluationType::class)]
#[CoversClass(EvaluationRepository::class)]
final class TrackingControllerTest extends AppWebTestCase
{
    /** Contrat de Lucas : formation SIO de Claire, tuteur Marc. */
    private function contract(): Apprenticeship
    {
        return $this->find(Apprenticeship::class, ['aApprentice' => $this->user(UserFixtures::APPRENTICE)]);
    }

    private function competence(string $pCode, string $pFormation = 'BTS Services informatiques aux organisations'): Competence
    {
        $vFormation = $this->find(Formation::class, ['aName' => $pFormation]);
        foreach ($vFormation->getCompetences() as $vCompetence) {
            if ($pCode === $vCompetence->getCode()) {
                return $vCompetence;
            }
        }
        throw new \LogicException('Compétence absente : '.$pCode);
    }

    /** @return iterable<string, array{string, int, bool}> */
    public static function viewMatrix(): iterable
    {
        yield 'école responsable' => [UserFixtures::SCHOOL, 200, true];
        yield 'autre école' => [UserFixtures::SCHOOL_OTHER, 403, false];
        yield 'tuteur du contrat' => [UserFixtures::TUTOR, 200, true];
        yield 'autre tuteur' => [UserFixtures::TUTOR_OTHER, 403, false];
        yield 'apprenti concerné' => [UserFixtures::APPRENTICE, 200, false];
        yield 'autre apprenti' => [UserFixtures::APPRENTICE_SAME_TUTOR, 403, false];
    }

    /** @return iterable<string, array{string, int}> */
    public static function reportMatrix(): iterable
    {
        foreach (self::viewMatrix() as $vLabel => [$vEmail, $vStatus]) {
            yield $vLabel => [$vEmail, $vStatus];
        }
    }

    #[DataProvider('viewMatrix')]
    public function testEachProfileSeesWhatItIsAllowedTo(string $pEmail, int $pStatus, bool $pCanEvaluate): void
    {
        $this->loginAs($pEmail);

        $this->aClient->request('GET', '/suivi/'.$this->contract()->getId());

        self::assertResponseStatusCodeSame($pStatus);
        if (200 === $pStatus) {
            self::assertSelectorTextContains('h1', 'Lucas Bernard');
            $pCanEvaluate ? self::assertSelectorExists('form[name="evaluation"]') : self::assertSelectorNotExists('form[name="evaluation"]');
        }
    }

    #[DataProvider('reportMatrix')]
    public function testEachProfileDownloadsTheReportItIsAllowedTo(string $pEmail, int $pStatus): void
    {
        $this->loginAs($pEmail);

        $this->aClient->request('GET', '/suivi/'.$this->contract()->getId().'/bilan.pdf');

        self::assertResponseStatusCodeSame($pStatus);
        if (200 === $pStatus) {
            $vResponse = $this->aClient->getResponse();
            self::assertSame('application/pdf', $vResponse->headers->get('Content-Type'));
            self::assertStringContainsString('attachment; filename=bilan-lucas-bernard-', (string) $vResponse->headers->get('Content-Disposition'));
            self::assertStringStartsWith('%PDF-1.4', (string) $vResponse->getContent());
            self::assertStringContainsString('(Lucas Bernard) Tj', (string) $vResponse->getContent());
        }
    }

    public function testReportIsNotOfferedToAnonymousVisitors(): void
    {
        $this->aClient->request('GET', '/suivi/'.$this->contract()->getId().'/bilan.pdf');

        self::assertResponseRedirects('/connexion');
    }

    public function testTrackingPageLinksToTheReport(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $this->aClient->request('GET', '/suivi/'.$this->contract()->getId());

        self::assertSelectorExists('a[href$="/bilan.pdf"]');
    }

    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->aClient->request('GET', '/suivi/'.$this->contract()->getId());

        self::assertResponseRedirects('/connexion');
    }

    public function testHistoryShowsTheMostRecentFirst(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $vCrawler = $this->aClient->request('GET', '/suivi/'.$this->contract()->getId());

        $vDates = $vCrawler->filter('#history tbody tr td:first-child')->each(static fn ($pCell): string => $pCell->text());
        self::assertSame(['18/05/2026', '02/03/2026', '09/02/2026', '12/01/2026', '15/12/2025', '06/10/2025'], $vDates);
        self::assertSelectorTextContains('#history', 'Autonome sur le support de niveau 1.');
    }

    public function testTutorEvaluatesInTheCompanyContext(): void
    {
        $this->loginAs(UserFixtures::TUTOR);
        $vContract = $this->contract();
        $vBefore = \count($this->evaluations($vContract));
        $this->aClient->request('GET', '/suivi/'.$vContract->getId());

        $this->aClient->submitForm('Enregistrer l\'évaluation', [
            'evaluation[competence]' => (string) $this->competence('C1.1')->getId(),
            'evaluation[level]' => '4',
            'evaluation[comment]' => 'Gère le parc en autonomie.',
        ]);

        self::assertResponseRedirects('/suivi/'.$vContract->getId());
        $vEvaluations = $this->evaluations($vContract);
        self::assertCount($vBefore + 1, $vEvaluations);
        $vLast = end($vEvaluations);
        self::assertNotFalse($vLast);
        self::assertSame(EvaluationContext::Company, $vLast->getContext());
        self::assertSame(CompetenceLevel::Mastered, $vLast->getLevel());
        self::assertSame(UserFixtures::TUTOR, $vLast->getEvaluator()->getEmail());
        self::assertSame((new \DateTimeImmutable())->format('Y-m-d'), $vLast->getEvaluatedAt()->format('Y-m-d'));
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Évaluation enregistrée.');
        self::assertSelectorTextContains('#history tbody tr:first-child', 'Entreprise');
    }

    public function testSchoolEvaluatesAtSchoolWithoutOverwritingThePast(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vContract = $this->contract();
        $vPrevious = array_filter($this->evaluations($vContract), static fn (Evaluation $pEvaluation): bool => 'C1.1' === $pEvaluation->getCompetence()->getCode());
        $this->aClient->request('GET', '/suivi/'.$vContract->getId());

        $this->aClient->submitForm('Enregistrer l\'évaluation', [
            'evaluation[competence]' => (string) $this->competence('C1.1')->getId(),
            'evaluation[level]' => '2',
        ]);

        self::assertResponseRedirects();
        $vNow = array_values(array_filter($this->evaluations($vContract), static fn (Evaluation $pEvaluation): bool => 'C1.1' === $pEvaluation->getCompetence()->getCode()));
        self::assertCount(\count($vPrevious) + 1, $vNow);
        self::assertSame([1, 2, 3, 2], array_map(static fn (Evaluation $pEvaluation): int => $pEvaluation->getLevel()->value, $vNow));
        self::assertSame(EvaluationContext::School, $vNow[3]->getContext());
        self::assertNull($vNow[3]->getComment());
    }

    /** @return iterable<string, array{string, string}> */
    public static function forgedValues(): iterable
    {
        yield 'niveau 5' => ['C1.1', '5'];
        yield 'niveau négatif' => ['C1.1', '-1'];
        yield 'sans niveau' => ['C1.1', ''];
        yield 'compétence d\'une autre formation' => ['web-C1.1', '3'];
    }

    #[DataProvider('forgedValues')]
    public function testInvalidEvaluationIsRefused(string $pCompetence, string $pLevel): void
    {
        $this->loginAs(UserFixtures::TUTOR);
        $vContract = $this->contract();
        $vBefore = \count($this->evaluations($vContract));
        $vCompetence = 'web-C1.1' === $pCompetence ? $this->competence('C1.1', 'Développeur web') : $this->competence($pCompetence);
        $vCrawler = $this->aClient->request('GET', '/suivi/'.$vContract->getId());
        $vToken = $vCrawler->filter('#evaluation__token')->attr('value');

        $this->aClient->request('POST', '/suivi/'.$vContract->getId(), ['evaluation' => [
            'competence' => (string) $vCompetence->getId(),
            'level' => $pLevel,
            'comment' => '',
            '_token' => (string) $vToken,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount($vBefore, $this->evaluations($vContract));
    }

    public function testApprenticeCannotEvaluate(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);
        $vContract = $this->contract();

        $this->aClient->request('POST', '/suivi/'.$vContract->getId(), ['evaluation' => ['competence' => (string) $this->competence('C1.1')->getId(), 'level' => '4']]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOtherTutorCannotEvaluate(): void
    {
        $this->loginAs(UserFixtures::TUTOR_OTHER);

        $this->aClient->request('POST', '/suivi/'.$this->contract()->getId(), ['evaluation' => ['competence' => (string) $this->competence('C1.1')->getId(), 'level' => '4']]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSchoolReachesTheContractFromItsCohort(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vCohort = $this->contract()->getCohort();
        $vCrawler = $this->aClient->request('GET', '/ecole/promotions/'.$vCohort->getId());

        $this->aClient->click($vCrawler->selectLink('Lucas Bernard')->link());

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('h1', 'Lucas Bernard');
    }

    /** @return list<Evaluation> */
    private function evaluations(Apprenticeship $pContract): array
    {
        $this->entityManager()->clear();

        return static::getContainer()->get(EvaluationRepository::class)->findForApprenticeship($this->find(Apprenticeship::class, ['aId' => $pContract->getId()]));
    }
}
