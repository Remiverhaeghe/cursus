<?php

namespace App\Tests\Functional\School;

use App\Controller\School\CompanyController;
use App\DataFixtures\UserFixtures;
use App\Entity\Company;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use App\Tests\Support\AppWebTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompanyController::class)]
#[CoversClass(CompanyRepository::class)]
#[CoversClass(CompanyType::class)]
final class CompanyControllerTest extends AppWebTestCase
{
    public function testCompaniesAreListedByName(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);

        $vCrawler = $this->aClient->request('GET', '/ecole/entreprises');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Atelier Lumen', 'Studio Brique'], $vCrawler->filter('tbody tr td:first-child')->each(static fn ($pCell): string => $pCell->text()));
    }

    public function testSchoolAddsACompany(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $this->aClient->request('GET', '/ecole/entreprises');

        $this->aClient->submitForm('Ajouter l\'entreprise', ['company[name]' => ' Agence Nord ', 'company[city]' => ' Lens ']);

        self::assertResponseRedirects('/ecole/entreprises');
        self::assertSame('Lens', $this->find(Company::class, ['aName' => 'Agence Nord'])->getCity());
        $vCrawler = $this->aClient->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Entreprise ajoutée.');
        self::assertSame(['Agence Nord', 'Atelier Lumen', 'Studio Brique'], $vCrawler->filter('tbody tr td:first-child')->each(static fn ($pCell): string => $pCell->text()));
    }

    public function testIncompleteCompanyIsRefused(): void
    {
        $this->loginAs(UserFixtures::SCHOOL);
        $this->aClient->request('GET', '/ecole/entreprises');

        $this->aClient->submitForm('Ajouter l\'entreprise', ['company[name]' => 'Sans ville', 'company[city]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager()->getRepository(Company::class)->findOneBy(['aName' => 'Sans ville']));
    }

    public function testApprenticeCannotSeeCompanies(): void
    {
        $this->loginAs(UserFixtures::APPRENTICE);

        $this->aClient->request('GET', '/ecole/entreprises');

        self::assertResponseStatusCodeSame(403);
    }
}
