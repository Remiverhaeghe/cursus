<?php

namespace App\Account;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ApprenticeshipRepository;
use App\Repository\FormationRepository;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Suppression d'un compte par son titulaire. Refusée tant que le compte porte un suivi en cours
 * (formation gérée, contrat non terminé) : l'école doit d'abord le confier à quelqu'un d'autre.
 */
final class AccountDeletion
{
    public function __construct(
        private readonly FormationRepository $aFormations,
        private readonly ApprenticeshipRepository $aApprenticeships,
        private readonly PasswordResetTokenRepository $aTokens,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    /** Raison du refus, ou null si le compte peut être supprimé. */
    public function blocker(User $pUser, \DateTimeImmutable $pToday): ?string
    {
        if ($pUser->hasRole(UserRole::School) && [] !== $this->aFormations->findManagedBy($pUser)) {
            return 'Tu gères encore au moins une formation : confie-la à un autre responsable avant de supprimer ton compte.';
        }
        if ($this->aApprenticeships->hasCurrentContract($pUser, $pToday)) {
            return 'Un contrat en cours te concerne : la suppression sera possible à sa fin, ou après accord de l\'école.';
        }

        return null;
    }

    public function delete(User $pUser): void
    {
        $vUserId = $pUser->getId();
        $this->aTokens->removeForUser($pUser);
        $pUser->anonymize();
        $this->aEntityManager->flush();
        $this->aLogger->info('Compte supprimé et anonymisé', ['user_id' => $vUserId]);
    }
}
