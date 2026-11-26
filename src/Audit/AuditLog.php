<?php

namespace App\Audit;

use App\Entity\AuditEntry;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Journal des actions lisible par l'école. Ajoute la ligne au gestionnaire d'entités sans l'enregistrer :
 * l'appelant fait son flush, la ligne et l'action partent ensemble.
 */
class AuditLog
{
    public const string LOGIN = 'connexion';
    public const string EVALUATION = 'evaluation';
    public const string PASSWORD_RESET = 'mot_de_passe';
    public const string ACCOUNT_DELETED = 'compte_supprime';
    public const string ACCOUNT_CREATED = 'compte_cree';
    public const string TOTP_ENABLED = 'double_authentification';
    public const string BACKUP_CODES = 'codes_de_secours';

    /** Libellés affichés dans le journal. */
    public const array LABELS = [
        self::LOGIN => 'Connexion',
        self::EVALUATION => 'Évaluation',
        self::PASSWORD_RESET => 'Mot de passe',
        self::ACCOUNT_DELETED => 'Suppression de compte',
        self::ACCOUNT_CREATED => 'Création de compte',
        self::TOTP_ENABLED => 'Double authentification',
        self::BACKUP_CODES => 'Codes de secours',
    ];

    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
    ) {
    }

    public function record(string $pAction, ?User $pActor, string $pSummary): void
    {
        $this->aEntityManager->persist(new AuditEntry($pAction, $pSummary, $pActor, $this->aClock->now()));
    }
}
