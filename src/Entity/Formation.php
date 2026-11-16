<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Diplôme préparé et son référentiel, géré par un responsable pédagogique de l'école. */
#[ORM\Entity(repositoryClass: FormationRepository::class)]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\Column(length: 150)]
    private string $aName;

    #[ORM\Column(length: 100)]
    private string $aDiploma;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $aManager;

    /** @var Collection<int, CompetenceBlock> */
    #[ORM\OneToMany(targetEntity: CompetenceBlock::class, mappedBy: 'aFormation')]
    #[ORM\OrderBy(['aPosition' => 'ASC'])]
    private Collection $aBlocks;

    /** @var Collection<int, Cohort> */
    #[ORM\OneToMany(targetEntity: Cohort::class, mappedBy: 'aFormation')]
    #[ORM\OrderBy(['aStartYear' => 'DESC'])]
    private Collection $aCohorts;

    public function __construct(string $pName, string $pDiploma, User $pManager)
    {
        if (!$pManager->hasRole(UserRole::School)) {
            throw new \InvalidArgumentException('Seul un membre de l\'école peut gérer une formation.');
        }
        $this->aName = $pName;
        $this->aDiploma = $pDiploma;
        $this->aManager = $pManager;
        $this->aBlocks = new ArrayCollection();
        $this->aCohorts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getName(): string
    {
        return $this->aName;
    }

    public function getDiploma(): string
    {
        return $this->aDiploma;
    }

    public function getManager(): User
    {
        return $this->aManager;
    }

    public function isManagedBy(User $pUser): bool
    {
        return $pUser === $this->aManager;
    }

    /** @return Collection<int, CompetenceBlock> */
    public function getBlocks(): Collection
    {
        return $this->aBlocks;
    }

    /** Appelé par le constructeur du bloc pour garder les deux côtés de la relation à jour. */
    public function attachBlock(CompetenceBlock $pBlock): void
    {
        $this->aBlocks->add($pBlock);
    }

    /** @return Collection<int, Cohort> */
    public function getCohorts(): Collection
    {
        return $this->aCohorts;
    }

    /** Appelé par le constructeur de la promotion pour garder les deux côtés de la relation à jour. */
    public function attachCohort(Cohort $pCohort): void
    {
        $this->aCohorts->add($pCohort);
    }

    public function nextBlockPosition(): int
    {
        return $this->aBlocks->count() + 1;
    }

    /** @return list<Competence> */
    public function getCompetences(): array
    {
        $rCompetences = [];
        foreach ($this->aBlocks as $vBlock) {
            foreach ($vBlock->getCompetences() as $vCompetence) {
                $rCompetences[] = $vCompetence;
            }
        }

        return $rCompetences;
    }

    public function hasCompetence(Competence $pCompetence): bool
    {
        return $this === $pCompetence->getBlock()->getFormation();
    }

    public function __toString(): string
    {
        return $this->aName;
    }
}
