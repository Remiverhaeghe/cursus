<?php

namespace App\Tests\Unit\Form;

use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\Data\ApprenticeshipData;
use App\Form\Data\CohortData;
use App\Form\Data\CompanyData;
use App\Form\Data\CompetenceBlockData;
use App\Form\Data\CompetenceData;
use App\Form\Data\FormationData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormationData::class)]
#[CoversClass(CompetenceBlockData::class)]
#[CoversClass(CompetenceData::class)]
#[CoversClass(CompanyData::class)]
#[CoversClass(CohortData::class)]
#[CoversClass(ApprenticeshipData::class)]
final class InputDataTest extends TestCase
{
    public function testReferentialInputsAreTrimmed(): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vFormationData = new FormationData();
        $vFormationData->name = '  Développeur web ';
        $vFormationData->diploma = ' Titre professionnel ';
        $vBlockData = new CompetenceBlockData();
        $vBlockData->code = ' B1 ';
        $vBlockData->title = ' Front-end ';
        $vCompetenceData = new CompetenceData();
        $vCompetenceData->code = ' C1.1 ';
        $vCompetenceData->label = ' Maquetter ';

        $vFormation = $vFormationData->toFormation($vManager);
        $vBlock = $vBlockData->toBlock($vFormation);
        $vCompetence = $vCompetenceData->toCompetence($vBlock);

        self::assertSame('Développeur web', $vFormation->getName());
        self::assertSame('Titre professionnel', $vFormation->getDiploma());
        self::assertSame($vManager, $vFormation->getManager());
        self::assertSame('B1', $vBlock->getCode());
        self::assertSame('Front-end', $vBlock->getTitle());
        self::assertSame($vFormation, $vBlock->getFormation());
        self::assertSame('C1.1', $vCompetence->getCode());
        self::assertSame('Maquetter', $vCompetence->getLabel());
        self::assertSame($vBlock, $vCompetence->getBlock());
    }

    public function testCompanyAndCohortInputsAreTrimmed(): void
    {
        $vFormation = (new FormationData())->toFormation(new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin'));
        $vCompanyData = new CompanyData();
        $vCompanyData->name = ' Atelier Lumen ';
        $vCompanyData->city = ' Lille ';
        $vCohortData = new CohortData();
        $vCohortData->name = ' Promotion 2026 ';
        $vCohortData->startYear = 2026;

        $vCompany = $vCompanyData->toCompany();
        $vCohort = $vCohortData->toCohort($vFormation);

        self::assertSame('Atelier Lumen', $vCompany->getName());
        self::assertSame('Lille', $vCompany->getCity());
        self::assertSame('Promotion 2026', $vCohort->getName());
        self::assertSame(2026, $vCohort->getStartYear());
        self::assertSame($vFormation, $vCohort->getFormation());
    }

    public function testCohortWithoutYearCannotBeBuilt(): void
    {
        $vFormation = (new FormationData())->toFormation(new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin'));
        $this->expectException(\LogicException::class);

        (new CohortData())->toCohort($vFormation);
    }

    public function testApprenticeshipInputBuildsTheContract(): void
    {
        $vData = $this->completeApprenticeshipData();
        $vCohort = $this->cohort();

        $vApprenticeship = $vData->toApprenticeship($vCohort);

        self::assertSame($vData->apprentice, $vApprenticeship->getApprentice());
        self::assertSame($vData->tutor, $vApprenticeship->getTutor());
        self::assertSame($vData->company, $vApprenticeship->getCompany());
        self::assertSame($vData->startDate, $vApprenticeship->getStartDate());
        self::assertSame($vData->endDate, $vApprenticeship->getEndDate());
        self::assertSame($vCohort, $vApprenticeship->getCohort());
    }

    /** @return iterable<string, array{string}> */
    public static function missingFields(): iterable
    {
        foreach (['apprentice', 'tutor', 'company', 'startDate', 'endDate'] as $vField) {
            yield $vField => [$vField];
        }
    }

    #[DataProvider('missingFields')]
    public function testIncompleteApprenticeshipCannotBeBuilt(string $pField): void
    {
        $vData = $this->completeApprenticeshipData();
        $vData->{$pField} = null;
        $this->expectException(\LogicException::class);

        $vData->toApprenticeship($this->cohort());
    }

    private function completeApprenticeshipData(): ApprenticeshipData
    {
        $rData = new ApprenticeshipData();
        $rData->apprentice = new User('apprenti@cursus.test', UserRole::Apprentice, 'Lucas', 'Blanc');
        $rData->tutor = new User('tuteur@cursus.test', UserRole::Tutor, 'Marc', 'Petit');
        $rData->company = new Company('Atelier Lumen', 'Lille');
        $rData->startDate = new \DateTimeImmutable('2026-09-01');
        $rData->endDate = new \DateTimeImmutable('2027-08-31');

        return $rData;
    }

    private function cohort(): Cohort
    {
        $vData = new CohortData();
        $vData->name = 'Promotion 2026';
        $vData->startYear = 2026;

        return $vData->toCohort((new FormationData())->toFormation(new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin')));
    }
}
