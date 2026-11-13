<?php

namespace App\DataFixtures;

use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\Formation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Entreprises, promotions et contrats fictifs : deux apprentis chez le même tuteur, un chez un autre, un dans une autre école. */
final class CohortFixtures extends Fixture implements DependentFixtureInterface
{
    public const string COHORT_SIO = 'cohort-sio';
    public const string COHORT_WEB = 'cohort-web';

    /** @var list<array{string, string, string, string}> apprenti, tuteur, entreprise, promotion */
    private const array CONTRACTS = [
        [UserFixtures::APPRENTICE, UserFixtures::TUTOR, 'lumen', self::COHORT_SIO],
        [UserFixtures::APPRENTICE_SAME_TUTOR, UserFixtures::TUTOR, 'lumen', self::COHORT_SIO],
        [UserFixtures::APPRENTICE_OTHER, UserFixtures::TUTOR_OTHER, 'brique', self::COHORT_SIO],
        [UserFixtures::APPRENTICE_OTHER_SCHOOL, UserFixtures::TUTOR_OTHER, 'brique', self::COHORT_WEB],
    ];

    public static function apprenticeshipReference(string $pApprenticeEmail): string
    {
        return 'apprenticeship-'.$pApprenticeEmail;
    }

    public function load(ObjectManager $manager): void
    {
        $vCompanies = [
            'lumen' => new Company('Atelier Lumen', 'Lille'),
            'brique' => new Company('Studio Brique', 'Roubaix'),
        ];
        foreach ($vCompanies as $vCompany) {
            $manager->persist($vCompany);
        }
        $vCohorts = [
            self::COHORT_SIO => new Cohort($this->getReference(ReferentialFixtures::FORMATION_SIO, Formation::class), 'Promotion 2025-2027', 2025),
            self::COHORT_WEB => new Cohort($this->getReference(ReferentialFixtures::FORMATION_WEB, Formation::class), 'Promotion 2025-2026', 2025),
        ];
        foreach ($vCohorts as $vReference => $vCohort) {
            $manager->persist($vCohort);
            $this->addReference($vReference, $vCohort);
        }
        foreach (self::CONTRACTS as [$vApprentice, $vTutor, $vCompany, $vCohort]) {
            $vApprenticeship = new Apprenticeship(
                $this->getReference($vApprentice, User::class),
                $this->getReference($vTutor, User::class),
                $vCompanies[$vCompany],
                $vCohorts[$vCohort],
                new \DateTimeImmutable('2025-09-01'),
                new \DateTimeImmutable('2027-08-31'),
            );
            $manager->persist($vApprenticeship);
            $this->addReference(self::apprenticeshipReference($vApprentice), $vApprenticeship);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, ReferentialFixtures::class];
    }
}
