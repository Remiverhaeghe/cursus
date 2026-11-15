<?php

namespace App\Tests\Functional\School;

use App\Controller\School\CohortController;
use App\Controller\School\FormationController;
use App\DataFixtures\UserFixtures;
use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\Formation;
use App\Form\ApprenticeshipType;
use App\Form\CohortType;
use App\Repository\UserRepository;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CohortController::class)]
#[CoversClass(FormationController::class)]
#[CoversClass(CohortType::class)]
#[CoversClass(ApprenticeshipType::class)]
#[CoversClass(UserRepository::class)]
final class CohortControllerTest extends AppWebTestCase
{
    private const string SIO = 'BTS Services informatiques aux organisations';

    public function testSchoolOpensACohort(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vFormation = $this->find(Formation::class, ['aName' => self::SIO]);
        $vCrawler = $this->aClient->request('GET', '/ecole/formations/'.$vFormation->getId());
        self::assertSame('Promotion 2025-2027', $vCrawler->filter('td a')->first()->text());

        $this->aClient->submitForm('Ouvrir la promotion', ['cohort[name]' => 'Promotion 2026-2028', 'cohort[startYear]' => '2026']);

        $vCohort = $this->find(Cohort::class, ['aName' => 'Promotion 2026-2028']);
        self::assertResponseRedirects('/ecole/promotions/'.$vCohort->getId());
        self::assertSame(2026, $vCohort->getStartYear());
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Promotion ouverte.');
        self::assertSelectorTextContains('.card .muted', 'Aucun apprenti rattaché.');
    }

    public function testCohortWithAnImpossibleYearIsRefused(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vFormation = $this->find(Formation::class, ['aName' => self::SIO]);
        $this->aClient->request('GET', '/ecole/formations/'.$vFormation->getId());

        $this->aClient->submitForm('Ouvrir la promotion', ['cohort[name]' => 'Promotion lointaine', 'cohort[startYear]' => '1990']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="cohort"]', 'This value should be between 2000 and 2100.');
        self::assertInputValueSame('cohort[name]', 'Promotion lointaine');
        self::assertNull($this->entityManager()->getRepository(Cohort::class)->findOneBy(['aName' => 'Promotion lointaine']));
    }

    public function testCohortListsItsContracts(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vCohort = $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']);

        $this->aClient->request('GET', '/ecole/promotions/'.$vCohort->getId());

        self::assertResponseStatusCodeSame(200);
        self::assertAnySelectorTextContains('tbody td', 'Lucas Bernard');
        self::assertAnySelectorTextContains('tbody td', 'Marc Lefebvre');
        self::assertAnySelectorTextContains('tbody td', '01/09/2025 → 31/08/2027');
    }

    public function testFormOnlyOffersAccountsOfTheRightProfile(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vCohort = $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']);

        $vCrawler = $this->aClient->request('GET', '/ecole/promotions/'.$vCohort->getId());

        $vApprentices = $vCrawler->filter('#apprenticeship_apprentice option')->each(static fn ($pOption): string => $pOption->text());
        $vTutors = $vCrawler->filter('#apprenticeship_tutor option')->each(static fn ($pOption): string => $pOption->text());
        self::assertSame(['Choisir un apprenti', 'Lucas Bernard', 'Hugo Moreau', 'Emma Petit', 'Nina Roux'], $vApprentices);
        self::assertSame(['Choisir un tuteur', 'Sophie Garnier', 'Marc Lefebvre'], $vTutors);
    }

    public function testSchoolAttachesAnApprentice(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vCohort = $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']);
        $this->aClient->request('GET', '/ecole/promotions/'.$vCohort->getId());

        $this->aClient->submitForm('Rattacher', $this->contractValues('2026-09-01', '2028-08-31'));

        self::assertResponseRedirects('/ecole/promotions/'.$vCohort->getId());
        $vContract = $this->find(Apprenticeship::class, ['aApprentice' => $this->user(UserFixtures::APPRENTICE_OTHER_SCHOOL), 'aCohort' => $vCohort]);
        self::assertSame(UserFixtures::TUTOR, $vContract->getTutor()->getEmail());
        self::assertSame('2028-08-31', $vContract->getEndDate()->format('Y-m-d'));
        $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Apprenti rattaché à la promotion.');
    }

    public function testContractEndingBeforeItStartsIsRefused(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $vCohort = $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']);
        $this->aClient->request('GET', '/ecole/promotions/'.$vCohort->getId());

        $this->aClient->submitForm('Rattacher', $this->contractValues('2026-09-01', '2026-06-30'));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'La fin du contrat doit suivre son début.');
        self::assertNull($this->entityManager()->getRepository(Apprenticeship::class)->findOneBy(['aApprentice' => $this->user(UserFixtures::APPRENTICE_OTHER_SCHOOL), 'aCohort' => $vCohort]));
    }

    public function testAnotherSchoolCannotOpenTheCohort(): void
    {
        $this->loginAs(UserFixtures::SCHOOL_OTHER);
        $vCohort = $this->find(Cohort::class, ['aName' => 'Promotion 2025-2027']);
        $vFormation = $this->find(Formation::class, ['aName' => self::SIO]);

        $this->aClient->request('GET', '/ecole/promotions/'.$vCohort->getId());
        self::assertResponseStatusCodeSame(403);

        $this->aClient->request('POST', '/ecole/formations/'.$vFormation->getId().'/promotions', ['cohort' => ['name' => 'Intrus', 'startYear' => 2026]]);
        self::assertResponseStatusCodeSame(403);
    }

    /** @return array<string, string> */
    private function contractValues(string $pStart, string $pEnd): array
    {
        return [
            'apprenticeship[apprentice]' => (string) $this->user(UserFixtures::APPRENTICE_OTHER_SCHOOL)->getId(),
            'apprenticeship[tutor]' => (string) $this->user(UserFixtures::TUTOR)->getId(),
            'apprenticeship[company]' => (string) $this->find(Company::class, ['aName' => 'Atelier Lumen'])->getId(),
            'apprenticeship[startDate]' => $pStart,
            'apprenticeship[endDate]' => $pEnd,
        ];
    }
}
