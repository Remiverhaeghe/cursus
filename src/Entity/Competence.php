<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Compétence évaluable d'un bloc, repérée par un code (C1.1, C1.2…). */
#[ORM\Entity]
class Competence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne(inversedBy: 'aCompetences')]
    #[ORM\JoinColumn(nullable: false)]
    private CompetenceBlock $aBlock;

    #[ORM\Column(length: 20)]
    private string $aCode;

    #[ORM\Column(length: 255)]
    private string $aLabel;

    public function __construct(CompetenceBlock $pBlock, string $pCode, string $pLabel)
    {
        $this->aBlock = $pBlock;
        $this->aCode = $pCode;
        $this->aLabel = $pLabel;
        $pBlock->attachCompetence($this);
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getBlock(): CompetenceBlock
    {
        return $this->aBlock;
    }

    public function getFormation(): Formation
    {
        return $this->aBlock->getFormation();
    }

    public function getCode(): string
    {
        return $this->aCode;
    }

    public function getLabel(): string
    {
        return $this->aLabel;
    }

    public function __toString(): string
    {
        return $this->aCode.' · '.$this->aLabel;
    }
}
