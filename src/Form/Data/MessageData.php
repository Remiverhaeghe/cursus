<?php

namespace App\Form\Data;

use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\CompetenceMessage;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/** Texte saisi pour un message sur une compétence. */
final class MessageData
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: CompetenceMessage::MAX_LENGTH, normalizer: 'trim')]
    public ?string $body = null;

    public function toMessage(Apprenticeship $pApprenticeship, Competence $pCompetence, User $pAuthor, \DateTimeImmutable $pNow): CompetenceMessage
    {
        if (null === $this->body) {
            throw new \LogicException('Message non validé.');
        }

        return new CompetenceMessage($pApprenticeship, $pCompetence, $pAuthor, $this->body, $pNow);
    }
}
