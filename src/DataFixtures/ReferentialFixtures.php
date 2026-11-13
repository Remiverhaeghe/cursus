<?php

namespace App\DataFixtures;

use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Deux formations fictives et leur référentiel, une par responsable de l'école. */
final class ReferentialFixtures extends Fixture implements DependentFixtureInterface
{
    public const string FORMATION_SIO = 'formation-sio';
    public const string FORMATION_WEB = 'formation-web';

    /** @var array<string, array{string, string, string, array<string, array{string, array<string, string>}>}> */
    private const array FORMATIONS = [
        self::FORMATION_SIO => ['BTS Services informatiques aux organisations', 'BTS', UserFixtures::SCHOOL, [
            'B1' => ['Support et mise à disposition de services informatiques', [
                'C1.1' => 'Gérer le patrimoine informatique',
                'C1.2' => 'Répondre aux incidents et aux demandes d\'assistance',
                'C1.3' => 'Développer la présence en ligne de l\'organisation',
            ]],
            'B2' => ['Conception et développement d\'applications', [
                'C2.1' => 'Concevoir et développer une solution applicative',
                'C2.2' => 'Assurer la maintenance d\'une solution applicative',
                'C2.3' => 'Gérer les données',
            ]],
        ]],
        self::FORMATION_WEB => ['Développeur web', 'Titre professionnel', UserFixtures::SCHOOL_OTHER, [
            'B1' => ['Développer la partie front-end', [
                'C1.1' => 'Maquetter une application',
                'C1.2' => 'Réaliser une interface web',
            ]],
            'B2' => ['Développer la partie back-end', [
                'C2.1' => 'Créer une base de données',
                'C2.2' => 'Développer les composants d\'accès aux données',
            ]],
        ]],
    ];

    public static function competenceReference(string $pFormation, string $pCode): string
    {
        return $pFormation.'-'.$pCode;
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::FORMATIONS as $vReference => [$vName, $vDiploma, $vManager, $vBlocks]) {
            $vFormation = new Formation($vName, $vDiploma, $this->getReference($vManager, User::class));
            $manager->persist($vFormation);
            $this->addReference($vReference, $vFormation);
            foreach ($vBlocks as $vBlockCode => [$vBlockTitle, $vCompetences]) {
                $vBlock = new CompetenceBlock($vFormation, $vBlockCode, $vBlockTitle);
                $manager->persist($vBlock);
                foreach ($vCompetences as $vCode => $vLabel) {
                    $vCompetence = new Competence($vBlock, $vCode, $vLabel);
                    $manager->persist($vCompetence);
                    $this->addReference(self::competenceReference($vReference, $vCode), $vCompetence);
                }
            }
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
