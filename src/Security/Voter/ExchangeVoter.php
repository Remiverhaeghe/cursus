<?php

namespace App\Security\Voter;

use App\Entity\Apprenticeship;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Échanges sur les compétences d'un contrat : le tuteur et l'apprenti écrivent ;
 * l'école responsable lit les fils pour suivre l'alternance, sans y écrire.
 *
 * @extends Voter<string, Apprenticeship>
 */
final class ExchangeVoter extends Voter
{
    public const string VIEW = 'EXCHANGE_VIEW';
    public const string POST = 'EXCHANGE_POST';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::POST], true) && $subject instanceof Apprenticeship;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $vUser = $token->getUser();
        if (!$vUser instanceof User) {
            return false;
        }

        $rAllowed = match ($vUser->getRole()) {
            UserRole::School => self::VIEW === $attribute && $subject->isManagedBy($vUser),
            UserRole::Tutor => $subject->isTutor($vUser),
            UserRole::Apprentice => $subject->isApprentice($vUser),
        };

        return $rAllowed;
    }
}
