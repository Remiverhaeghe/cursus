<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Promotion d'une formation, repérée par son année d'entrée. */
#[ORM\Entity]
class Cohort
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne(inversedBy: 'aCohorts')]
    #[ORM\JoinColumn(nullable: false)]
    private Formation $aFormation;

    #[ORM\Column(length: 100)]
    private string $aName;

    #[ORM\Column(type: 'smallint')]
    private int $aStartYear;

    /** @var Collection<int, Apprenticeship> */
    #[ORM\OneToMany(targetEntity: Apprenticeship::class, mappedBy: 'aCohort')]
    private Collection $aApprenticeships;

    public function __construct(Formation $pFormation, string $pName, int $pStartYear)
    {
        $this->aFormation = $pFormation;
        $this->aName = $pName;
        $this->aStartYear = $pStartYear;
        $this->aApprenticeships = new ArrayCollection();
        $pFormation->attachCohort($this);
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getFormation(): Formation
    {
        return $this->aFormation;
    }

    public function getName(): string
    {
        return $this->aName;
    }

    public function getStartYear(): int
    {
        return $this->aStartYear;
    }

    /** @return Collection<int, Apprenticeship> */
    public function getApprenticeships(): Collection
    {
        return $this->aApprenticeships;
    }

    /** Appelé par le constructeur du contrat pour garder les deux côtés de la relation à jour. */
    public function attachApprenticeship(Apprenticeship $pApprenticeship): void
    {
        $this->aApprenticeships->add($pApprenticeship);
    }

    public function __toString(): string
    {
        return $this->aName;
    }
}
