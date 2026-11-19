<?php

namespace App\Entity;

use App\Repository\CompetenceMessageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Message échangé entre le tuteur et l'apprenti à propos d'une compétence du contrat.
 * Un message n'est jamais modifié ni supprimé : le fil garde tout l'historique.
 */
#[ORM\Entity(repositoryClass: CompetenceMessageRepository::class)]
class CompetenceMessage
{
    public const int MAX_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Apprenticeship $aApprenticeship;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Competence $aCompetence;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $aAuthor;

    #[ORM\Column(type: 'text')]
    private string $aBody;

    #[ORM\Column]
    private \DateTimeImmutable $aPostedAt;

    public function __construct(Apprenticeship $pApprenticeship, Competence $pCompetence, User $pAuthor, string $pBody, \DateTimeImmutable $pPostedAt)
    {
        if (!$pApprenticeship->getFormation()->hasCompetence($pCompetence)) {
            throw new \InvalidArgumentException('Cette compétence n\'appartient pas à la formation de l\'apprenti.');
        }
        if (!$pApprenticeship->isTutor($pAuthor) && !$pApprenticeship->isApprentice($pAuthor)) {
            throw new \InvalidArgumentException('Seuls le tuteur et l\'apprenti du contrat échangent sur ses compétences.');
        }
        $vBody = trim($pBody);
        if ('' === $vBody || mb_strlen($vBody) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Un message compte de 1 à 2000 caractères.');
        }
        $this->aApprenticeship = $pApprenticeship;
        $this->aCompetence = $pCompetence;
        $this->aAuthor = $pAuthor;
        $this->aBody = $vBody;
        $this->aPostedAt = $pPostedAt;
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getApprenticeship(): Apprenticeship
    {
        return $this->aApprenticeship;
    }

    public function getCompetence(): Competence
    {
        return $this->aCompetence;
    }

    public function getAuthor(): User
    {
        return $this->aAuthor;
    }

    public function getBody(): string
    {
        return $this->aBody;
    }

    public function getPostedAt(): \DateTimeImmutable
    {
        return $this->aPostedAt;
    }
}
