<?php

namespace App\Security\Voter;

use App\Entity\AuditEntry;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Journal des actions : lisible par l'école seulement. Le sujet est la classe AuditEntry elle-même,
 * le journal se lit en entier ou pas du tout.
 *
 * @extends Voter<string, class-string<AuditEntry>>
 */
final class AuditVoter extends Voter
{
    public const string VIEW = 'AUDIT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && AuditEntry::class === $subject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $vUser = $token->getUser();

        return $vUser instanceof User && $vUser->hasRole(UserRole::School);
    }
}
