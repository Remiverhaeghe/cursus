<?php

namespace App\Tests\Unit\Progress;

use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\Evaluation;
use App\Entity\User;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use App\Enum\UserRole;
use App\Progress\ProgressCalculator;
use App\Progress\ProgressProvider;
use App\Repository\EvaluationRepository;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgressProvider::class)]
final class ProgressProviderTest extends TestCase
{
    public function testContractsAreGroupedByCohortWithTheirOwnEvaluations(): void
    {
        $vData = new ContractFactory();
        (new \ReflectionProperty(Apprenticeship::class, 'aId'))->setValue($vData->aApprenticeship, 1);
        $vOtherCohort = new Cohort($vData->aFormation, 'Promotion 2027', 2027);
        $vSecond = new Apprenticeship(
            new User('autre@cursus.test', UserRole::Apprentice, 'Emma', 'Petit'),
            $vData->aTutor,
            new Company('Studio Brique', 'Roubaix'),
            $vOtherCohort,
            new \DateTimeImmutable('2027-09-01'),
            new \DateTimeImmutable('2028-08-31'),
        );
        (new \ReflectionProperty(Apprenticeship::class, 'aId'))->setValue($vSecond, 2);
        $vEvaluation = new Evaluation($vSecond, $vData->aModel, $vData->aTutor, CompetenceLevel::Mastered, EvaluationContext::Company, new \DateTimeImmutable('2027-10-01'));
        $vEvaluations = $this->createStub(EvaluationRepository::class);
        $vEvaluations->method('findGroupedByApprenticeship')->willReturn([2 => [$vEvaluation]]);

        $vCohorts = (new ProgressProvider($vEvaluations, new ProgressCalculator()))->byCohort([$vData->aApprenticeship, $vSecond]);

        self::assertCount(2, $vCohorts);
        self::assertSame($vData->aApprenticeship->getCohort(), $vCohorts[0]->aCohort);
        self::assertSame($vOtherCohort, $vCohorts[1]->aCohort);
        self::assertSame(0.0, $vCohorts[0]->average());
        self::assertSame(1.33, $vCohorts[1]->average());
    }
}
