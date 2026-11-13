<?php

namespace App\Security\Voter;

use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Gestion d'une formation et de son référentiel : réservée au responsable de l'école qui la porte.
 *
 * @extends Voter<string, Formation|CompetenceBlock|Competence>
 */
final class FormationVoter extends Voter
{
    public const string MANAGE = 'FORMATION_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute
            && ($subject instanceof Formation || $subject instanceof CompetenceBlock || $subject instanceof Competence);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $vUser = $token->getUser();
        if (!$vUser instanceof User) {
            return false;
        }
        $vFormation = $subject instanceof Formation ? $subject : $subject->getFormation();

        return $vFormation->isManagedBy($vUser);
    }
}
