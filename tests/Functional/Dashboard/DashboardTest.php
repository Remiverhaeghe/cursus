<?php

namespace App\Tests\Functional\Dashboard;

use App\Controller\DashboardController;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\UserRole;
use App\Progress\ProgressProvider;
use App\Repository\ApprenticeshipRepository;
use App\Repository\EvaluationRepository;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DomCrawler\Crawler;

/** Chaque tableau de bord ne montre que les apprentis de l'utilisateur connecté. */
#[CoversClass(DashboardController::class)]
#[CoversClass(ProgressProvider::class)]
#[CoversClass(ApprenticeshipRepository::class)]
#[CoversClass(EvaluationRepository::class)]
final class DashboardTest extends AppWebTestCase
{
    public function testSchoolSeesItsCohortWithEachApprenticeAndTheAverage(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);

        $vCrawler = $this->aClient->request('GET', '/ecole');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.cohort h2', 'BTS Services informatiques aux organisations · Promotion 2025-2027');
        self::assertSelectorTextContains('.cohort p', '0,8 / 4');
        self::assertSame(['Lucas Bernard', 'Hugo Moreau', 'Emma Petit'], $this->names($vCrawler));
        self::assertSame(['1,5 / 4', '2 / 6', '3'], $this->row($vCrawler, 0));
    }

    public function testOtherSchoolOnlySeesItsOwnApprentices(): void
    {
        $this->loginAs(UserFixtures::SCHOOL_OTHER);

        $vCrawler = $this->aClient->request('GET', '/ecole');

        self::assertSame(['Nina Roux'], $this->names($vCrawler));
    }

    public function testTutorOnlySeesTheApprenticesOfItsContracts(): void
    {
        $this->loginAs(UserFixtures::TUTOR);

        $vCrawler = $this->aClient->request('GET', '/tuteur');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Lucas Bernard', 'Hugo Moreau'], $this->names($vCrawler));
    }

    public function testTutorWithSeveralCohortsSeesBoth(): void
    {
        $this->loginAs(UserFixtures::TUTOR_OTHER);

        $vCrawler = $this->aClient->request('GET', '/tuteur');

        self::assertSame(['Emma Petit', 'Nina Roux'], $this->names($vCrawler));
    }

    public function testApprenticeSeesBlocksCurvesAndWhatRemains(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $vCrawler = $this->aClient->request('GET', '/apprenti');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.card p', 'Moyenne 1,5 / 4 · 2 compétence(s) validée(s) sur 6');
        self::assertAnySelectorTextContains('h3', 'B1 · Support et mise à disposition de services informatiques · 2,3 / 4');
        self::assertAnySelectorTextContains('.badge-warning', '1 compétence(s) jamais évaluée(s), comptée(s) à 0');
        self::assertSame('0,24 60,16 120,8', $vCrawler->filter('.sparkline polyline')->first()->attr('points'));
        self::assertCount(2, $vCrawler->filter('.sparkline'));
        self::assertSame(4, $vCrawler->filter('.remaining li')->count());
        self::assertSelectorTextContains('.remaining', 'C2.1 · Concevoir et développer une solution applicative — En cours d\'acquisition');
        self::assertSelectorTextNotContains('body', 'Hugo Moreau');
    }

    public function testAccountsWithoutContractSeeAnEmptyState(): void
    {
        $vTutor = new User('nouveau.tuteur@cursus.test', UserRole::Tutor, 'Yanis', 'Leroy');
        $vApprentice = new User('nouvel.apprenti@cursus.test', UserRole::Apprentice, 'Inès', 'Faure');
        $vSchool = new User('nouvelle.ecole@cursus.test', UserRole::School, 'Rose', 'Michel');
        foreach ([$vTutor, $vApprentice, $vSchool] as $vUser) {
            $this->entityManager()->persist($vUser);
        }
        $this->entityManager()->flush();

        $this->loginAs('nouveau.tuteur@cursus.test');
        $this->aClient->request('GET', '/tuteur');
        self::assertSelectorTextContains('.muted', 'Aucun apprenti ne t\'est confié');

        $this->loginAs('nouvel.apprenti@cursus.test');
        $this->aClient->request('GET', '/apprenti');
        self::assertSelectorTextContains('.muted', 'rattaché à aucune promotion');

        $this->loginAs('nouvelle.ecole@cursus.test');
        $this->aClient->request('GET', '/ecole');
        self::assertSelectorTextContains('.muted', 'Aucun apprenti dans tes promotions');
    }

    public function testTrackingPageShowsTheProgressToo(): void
    {
        $this->loginAs(UserFixtures::TUTOR);
        $vCrawler = $this->aClient->request('GET', '/tuteur');

        $this->aClient->click($vCrawler->selectLink('Lucas Bernard')->link());

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('.card p', 'Moyenne 1,5 / 4');
        self::assertSelectorExists('.remaining');
    }

    /** @return list<string> */
    private function names(Crawler $pCrawler): array
    {
        return $pCrawler->filter('table.apprentices tbody tr td:first-child')->each(static fn (Crawler $pCell): string => $pCell->text());
    }

    /** @return list<string> progression, validées, non évaluées */
    private function row(Crawler $pCrawler, int $pIndex): array
    {
        $vCells = $pCrawler->filter('table.apprentices tbody tr')->eq($pIndex)->filter('td');

        return [
            trim(str_replace($vCells->eq(2)->filter('progress')->text(), '', $vCells->eq(2)->text())),
            $vCells->eq(3)->text(),
            $vCells->eq(4)->text(),
        ];
    }
}
