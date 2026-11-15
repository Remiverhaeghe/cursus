<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Bloc de compétences d'une formation (par exemple « Bloc 1 : support et mise à disposition de services »). */
#[ORM\Entity]
class CompetenceBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne(inversedBy: 'aBlocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Formation $aFormation;

    #[ORM\Column(length: 20)]
    private string $aCode;

    #[ORM\Column(length: 255)]
    private string $aTitle;

    #[ORM\Column]
    private int $aPosition;

    /** @var Collection<int, Competence> */
    #[ORM\OneToMany(targetEntity: Competence::class, mappedBy: 'aBlock')]
    #[ORM\OrderBy(['aCode' => 'ASC'])]
    private Collection $aCompetences;

    public function __construct(Formation $pFormation, string $pCode, string $pTitle)
    {
        $this->aFormation = $pFormation;
        $this->aCode = $pCode;
        $this->aTitle = $pTitle;
        $this->aPosition = $pFormation->nextBlockPosition();
        $this->aCompetences = new ArrayCollection();
        $pFormation->attachBlock($this);
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getFormation(): Formation
    {
        return $this->aFormation;
    }

    public function getCode(): string
    {
        return $this->aCode;
    }

    public function getTitle(): string
    {
        return $this->aTitle;
    }

    public function getPosition(): int
    {
        return $this->aPosition;
    }

    /** @return Collection<int, Competence> */
    public function getCompetences(): Collection
    {
        return $this->aCompetences;
    }

    /** Appelé par le constructeur de la compétence pour garder les deux côtés de la relation à jour. */
    public function attachCompetence(Competence $pCompetence): void
    {
        $this->aCompetences->add($pCompetence);
    }

    public function label(): string
    {
        return $this->aCode.' · '.$this->aTitle;
    }

    public function __toString(): string
    {
        return $this->label();
    }
}
