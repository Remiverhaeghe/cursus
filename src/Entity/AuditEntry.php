<?php

namespace App\Entity;

use App\Repository\AuditEntryRepository;
use Doctrine\ORM\Mapping as ORM;

/** Une ligne du journal des actions : qui, quand, quoi. Jamais de mot de passe, de jeton ni d'adresse IP. */
#[ORM\Entity(repositoryClass: AuditEntryRepository::class)]
class AuditEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\Column(length: 40)]
    private string $aAction;

    #[ORM\Column(length: 255)]
    private string $aSummary;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $aActor;

    #[ORM\Column]
    private \DateTimeImmutable $aOccurredAt;

    public function __construct(string $pAction, string $pSummary, ?User $pActor, \DateTimeImmutable $pOccurredAt)
    {
        $this->aAction = $pAction;
        $this->aSummary = mb_substr($pSummary, 0, 255);
        $this->aActor = $pActor;
        $this->aOccurredAt = $pOccurredAt;
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getAction(): string
    {
        return $this->aAction;
    }

    public function getSummary(): string
    {
        return $this->aSummary;
    }

    public function getActor(): ?User
    {
        return $this->aActor;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->aOccurredAt;
    }
}
