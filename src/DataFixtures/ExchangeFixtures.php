<?php

namespace App\DataFixtures;

use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\CompetenceMessage;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Échanges fictifs entre un tuteur et son apprenti sur deux compétences. */
final class ExchangeFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var list<array{string, string, string, string, string}> apprenti, auteur, compétence, date, message */
    private const array MESSAGES = [
        [UserFixtures::APPRENTICE, UserFixtures::APPRENTICE, 'C1.1', '2025-11-20 18:05', 'J\'ai commencé l\'inventaire du parc de l\'agence. Faut-il inclure les imprimantes ?'],
        [UserFixtures::APPRENTICE, UserFixtures::TUTOR, 'C1.1', '2025-11-21 08:40', 'Oui, avec leur numéro d\'inventaire. Regarde aussi les bornes Wi-Fi.'],
        [UserFixtures::APPRENTICE, UserFixtures::APPRENTICE, 'C1.1', '2025-12-12 17:30', 'Inventaire terminé, le fichier est sur le partage de l\'équipe.'],
        [UserFixtures::APPRENTICE, UserFixtures::TUTOR, 'C1.2', '2026-01-08 09:15', 'Tu prendras les tickets de niveau 1 à partir de lundi.'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::MESSAGES as [$vApprentice, $vAuthor, $vCode, $vDate, $vBody]) {
            $manager->persist(new CompetenceMessage(
                $this->getReference(CohortFixtures::apprenticeshipReference($vApprentice), Apprenticeship::class),
                $this->getReference(ReferentialFixtures::competenceReference(ReferentialFixtures::FORMATION_SIO, $vCode), Competence::class),
                $this->getReference($vAuthor, User::class),
                $vBody,
                new \DateTimeImmutable($vDate),
            ));
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CohortFixtures::class];
    }
}
