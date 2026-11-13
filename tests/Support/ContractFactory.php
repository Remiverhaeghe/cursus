<?php

namespace App\Tests\Support;

use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\UserRole;

/** Petit jeu de données en mémoire pour les tests unitaires : une formation à deux blocs et un contrat. */
final class ContractFactory
{
    public readonly User $aManager;
    public readonly User $aTutor;
    public readonly User $aApprentice;
    public readonly Formation $aFormation;
    public readonly CompetenceBlock $aFrontBlock;
    public readonly CompetenceBlock $aBackBlock;
    public readonly Competence $aModel;
    public readonly Competence $aInterface;
    public readonly Competence $aDatabase;
    public readonly Apprenticeship $aApprenticeship;

    public function __construct()
    {
        $this->aManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $this->aTutor = new User('tuteur@cursus.test', UserRole::Tutor, 'Marc', 'Petit');
        $this->aApprentice = new User('apprenti@cursus.test', UserRole::Apprentice, 'Lucas', 'Blanc');
        $this->aFormation = new Formation('Développeur web', 'Titre professionnel', $this->aManager);
        $this->aFrontBlock = new CompetenceBlock($this->aFormation, 'B1', 'Front-end');
        $this->aBackBlock = new CompetenceBlock($this->aFormation, 'B2', 'Back-end');
        $this->aModel = new Competence($this->aFrontBlock, 'C1.1', 'Maquetter');
        $this->aInterface = new Competence($this->aFrontBlock, 'C1.2', 'Intégrer');
        $this->aDatabase = new Competence($this->aBackBlock, 'C2.1', 'Base de données');
        $this->aApprenticeship = new Apprenticeship(
            $this->aApprentice,
            $this->aTutor,
            new Company('Atelier Lumen', 'Lille'),
            new Cohort($this->aFormation, 'Promotion 2026', 2026),
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable('2027-08-31'),
        );
    }
}
