<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Apprenticeship::class)]
#[CoversClass(Cohort::class)]
#[CoversClass(Company::class)]
final class ApprenticeshipTest extends TestCase
{
    public function testContractLinksEveryone(): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vFormation = new Formation('Développeur web', 'Titre professionnel', $vManager);
        $vCohort = new Cohort($vFormation, 'Promotion 2026', 2026);
        $vCompany = new Company('Atelier Lumen', 'Lille');
        $vApprentice = new User('apprenti@cursus.test', UserRole::Apprentice, 'Lucas', 'Blanc');
        $vTutor = new User('tuteur@cursus.test', UserRole::Tutor, 'Marc', 'Petit');
        $vStart = new \DateTimeImmutable('2026-09-01');
        $vEnd = new \DateTimeImmutable('2027-08-31');

        $vApprenticeship = new Apprenticeship($vApprentice, $vTutor, $vCompany, $vCohort, $vStart, $vEnd);

        self::assertNull($vApprenticeship->getId());
        self::assertSame($vApprentice, $vApprenticeship->getApprentice());
        self::assertSame($vTutor, $vApprenticeship->getTutor());
        self::assertSame($vCompany, $vApprenticeship->getCompany());
        self::assertSame($vCohort, $vApprenticeship->getCohort());
        self::assertSame($vFormation, $vApprenticeship->getFormation());
        self::assertSame($vStart, $vApprenticeship->getStartDate());
        self::assertSame($vEnd, $vApprenticeship->getEndDate());
        self::assertTrue($vApprenticeship->isApprentice($vApprentice));
        self::assertFalse($vApprenticeship->isApprentice($vTutor));
        self::assertTrue($vApprenticeship->isTutor($vTutor));
        self::assertFalse($vApprenticeship->isTutor($vApprentice));
        self::assertTrue($vApprenticeship->isManagedBy($vManager));
        self::assertFalse($vApprenticeship->isManagedBy($vTutor));
        self::assertSame([$vApprenticeship], $vCohort->getApprenticeships()->toArray());
        self::assertSame([$vCohort], $vFormation->getCohorts()->toArray());
    }

    /** @return iterable<string, array{UserRole, UserRole, string, string, string}> */
    public static function invalidContracts(): iterable
    {
        yield 'apprenti qui n\'en est pas un' => [UserRole::Tutor, UserRole::Tutor, '2026-09-01', '2027-08-31', 'compte apprenti'];
        yield 'tuteur qui n\'en est pas un' => [UserRole::Apprentice, UserRole::School, '2026-09-01', '2027-08-31', 'compte tuteur'];
        yield 'fin avant le début' => [UserRole::Apprentice, UserRole::Tutor, '2026-09-01', '2026-08-31', 'doit suivre'];
        yield 'fin le jour du début' => [UserRole::Apprentice, UserRole::Tutor, '2026-09-01', '2026-09-01', 'doit suivre'];
    }

    #[DataProvider('invalidContracts')]
    public function testInvalidContractIsRefused(UserRole $pApprenticeRole, UserRole $pTutorRole, string $pStart, string $pEnd, string $pMessage): void
    {
        $vCohort = new Cohort(new Formation('Web', 'Titre', new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin')), 'P', 2026);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($pMessage);

        try {
            new Apprenticeship(
                new User('a@cursus.test', $pApprenticeRole, 'A', 'A'),
                new User('t@cursus.test', $pTutorRole, 'T', 'T'),
                new Company('Atelier Lumen', 'Lille'),
                $vCohort,
                new \DateTimeImmutable($pStart),
                new \DateTimeImmutable($pEnd),
            );
        } finally {
            self::assertCount(0, $vCohort->getApprenticeships());
        }
    }

    public function testCohortAndCompanyLabels(): void
    {
        $vFormation = new Formation('Web', 'Titre', new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin'));
        $vCohort = new Cohort($vFormation, 'Promotion 2026', 2026);
        $vCompany = new Company('Atelier Lumen', 'Lille');

        self::assertNull($vCohort->getId());
        self::assertSame($vFormation, $vCohort->getFormation());
        self::assertSame('Promotion 2026', $vCohort->getName());
        self::assertSame('Promotion 2026', (string) $vCohort);
        self::assertSame(2026, $vCohort->getStartYear());
        self::assertNull($vCompany->getId());
        self::assertSame('Atelier Lumen', $vCompany->getName());
        self::assertSame('Lille', $vCompany->getCity());
        self::assertSame('Atelier Lumen (Lille)', (string) $vCompany);
    }
}
