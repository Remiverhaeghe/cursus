<?php

namespace App\DataFixtures;

use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\Evaluation;
use App\Entity\User;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Historique d'évaluations fictif : des progrès sur plusieurs mois et des compétences encore jamais évaluées. */
final class EvaluationFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var list<array{string, string, string, string, int, string, ?string}> apprenti, évaluateur, formation, compétence, niveau, date, commentaire */
    private const array EVALUATIONS = [
        [UserFixtures::APPRENTICE, UserFixtures::SCHOOL, ReferentialFixtures::FORMATION_SIO, 'C1.1', 1, '2025-10-06', 'Premiers inventaires réalisés en TP.'],
        [UserFixtures::APPRENTICE, UserFixtures::TUTOR, ReferentialFixtures::FORMATION_SIO, 'C1.1', 2, '2025-12-15', 'Inventaire du parc de l\'agence mené seul.'],
        [UserFixtures::APPRENTICE, UserFixtures::SCHOOL, ReferentialFixtures::FORMATION_SIO, 'C1.1', 3, '2026-03-02', null],
        [UserFixtures::APPRENTICE, UserFixtures::TUTOR, ReferentialFixtures::FORMATION_SIO, 'C1.2', 2, '2026-01-12', 'Traite les tickets simples.'],
        [UserFixtures::APPRENTICE, UserFixtures::TUTOR, ReferentialFixtures::FORMATION_SIO, 'C1.2', 4, '2026-05-18', 'Autonome sur le support de niveau 1.'],
        [UserFixtures::APPRENTICE, UserFixtures::SCHOOL, ReferentialFixtures::FORMATION_SIO, 'C2.1', 2, '2026-02-09', null],
        [UserFixtures::APPRENTICE_SAME_TUTOR, UserFixtures::TUTOR, ReferentialFixtures::FORMATION_SIO, 'C1.2', 1, '2025-11-17', null],
        [UserFixtures::APPRENTICE_SAME_TUTOR, UserFixtures::SCHOOL, ReferentialFixtures::FORMATION_SIO, 'C2.3', 3, '2026-04-07', 'Bonne maîtrise du SQL.'],
        [UserFixtures::APPRENTICE_OTHER, UserFixtures::TUTOR_OTHER, ReferentialFixtures::FORMATION_SIO, 'C1.3', 2, '2026-01-26', null],
        [UserFixtures::APPRENTICE_OTHER_SCHOOL, UserFixtures::SCHOOL_OTHER, ReferentialFixtures::FORMATION_WEB, 'C1.1', 3, '2025-11-03', null],
        [UserFixtures::APPRENTICE_OTHER_SCHOOL, UserFixtures::TUTOR_OTHER, ReferentialFixtures::FORMATION_WEB, 'C1.2', 2, '2026-02-16', null],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::EVALUATIONS as [$vApprentice, $vEvaluator, $vFormation, $vCode, $vLevel, $vDate, $vComment]) {
            $vUser = $this->getReference($vEvaluator, User::class);
            $manager->persist(new Evaluation(
                $this->getReference(CohortFixtures::apprenticeshipReference($vApprentice), Apprenticeship::class),
                $this->getReference(ReferentialFixtures::competenceReference($vFormation, $vCode), Competence::class),
                $vUser,
                CompetenceLevel::from($vLevel),
                EvaluationContext::forRole($vUser->getRole()) ?? EvaluationContext::School,
                new \DateTimeImmutable($vDate.' 10:00'),
                $vComment,
            ));
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CohortFixtures::class];
    }
}
