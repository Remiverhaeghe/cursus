<?php

namespace App\DataFixtures;

use App\Entity\CalendarPeriod;
use App\Entity\Cohort;
use App\Entity\PlannedAssessment;
use App\Enum\EvaluationContext;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Calendriers fictifs : une semaine à l'école puis deux en entreprise, et quelques évaluations prévues. */
final class CalendarFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var array<string, array{string, string}> premier et dernier jour du rythme par promotion */
    private const array RHYTHMS = [
        CohortFixtures::COHORT_SIO => ['2025-09-01', '2027-08-31'],
        CohortFixtures::COHORT_WEB => ['2025-09-01', '2026-08-31'],
    ];

    /** @var list<array{string, string, string, string}> promotion, date, intitulé, lieu */
    private const array ASSESSMENTS = [
        [CohortFixtures::COHORT_SIO, '2026-01-19', 'Épreuve blanche E5', 'school'],
        [CohortFixtures::COHORT_SIO, '2026-06-08', 'Bilan de fin de première année', 'company'],
        [CohortFixtures::COHORT_SIO, '2027-01-18', 'Épreuve blanche E6', 'school'],
        [CohortFixtures::COHORT_SIO, '2027-05-17', 'Soutenance du projet en entreprise', 'company'],
        [CohortFixtures::COHORT_SIO, '2027-10-04', 'Rattrapage', 'school'],
        [CohortFixtures::COHORT_WEB, '2026-06-15', 'Soutenance du titre', 'school'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::RHYTHMS as $vReference => [$vFirst, $vLast]) {
            $vCohort = $this->getReference($vReference, Cohort::class);
            $vEnd = new \DateTimeImmutable($vLast);
            $vDay = new \DateTimeImmutable($vFirst);
            while ($vDay <= $vEnd) {
                $manager->persist(new CalendarPeriod($vCohort, EvaluationContext::School, $vDay, min($vDay->modify('+6 days'), $vEnd)));
                $vDay = $vDay->modify('+7 days');
                if ($vDay <= $vEnd) {
                    $manager->persist(new CalendarPeriod($vCohort, EvaluationContext::Company, $vDay, min($vDay->modify('+13 days'), $vEnd)));
                }
                $vDay = $vDay->modify('+14 days');
            }
        }
        foreach (self::ASSESSMENTS as [$vReference, $vDate, $vTitle, $vPlace]) {
            $manager->persist(new PlannedAssessment($this->getReference($vReference, Cohort::class), $vTitle, EvaluationContext::from($vPlace), new \DateTimeImmutable($vDate)));
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CohortFixtures::class];
    }
}
