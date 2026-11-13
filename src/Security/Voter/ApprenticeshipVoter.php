<?php

namespace App\Security\Voter;

use App\Entity\Apprenticeship;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Suivi d'un contrat : l'école responsable de la formation, le tuteur du contrat et l'apprenti concerné le consultent ;
 * seuls l'école et le tuteur évaluent.
 *
 * @extends Voter<string, Apprenticeship>
 */
final class ApprenticeshipVoter extends Voter
{
    public const string VIEW = 'APPRENTICESHIP_VIEW';
    public const string EVALUATE = 'APPRENTICESHIP_EVALUATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EVALUATE], true) && $subject instanceof Apprenticeship;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $vUser = $token->getUser();
        if (!$vUser instanceof User) {
            return false;
        }

        $rAllowed = match ($vUser->getRole()) {
            UserRole::School => $subject->isManagedBy($vUser),
            UserRole::Tutor => $subject->isTutor($vUser),
            UserRole::Apprentice => self::VIEW === $attribute && $subject->isApprentice($vUser),
        };

        return $rAllowed;
    }
}
