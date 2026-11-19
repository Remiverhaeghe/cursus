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

    /** @var Collection<int, CalendarPeriod> */
    #[ORM\OneToMany(targetEntity: CalendarPeriod::class, mappedBy: 'aCohort')]
    private Collection $aPeriods;

    /** @var Collection<int, PlannedAssessment> */
    #[ORM\OneToMany(targetEntity: PlannedAssessment::class, mappedBy: 'aCohort')]
    private Collection $aAssessments;

    public function __construct(Formation $pFormation, string $pName, int $pStartYear)
    {
        $this->aFormation = $pFormation;
        $this->aName = $pName;
        $this->aStartYear = $pStartYear;
        $this->aApprenticeships = new ArrayCollection();
        $this->aPeriods = new ArrayCollection();
        $this->aAssessments = new ArrayCollection();
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

    /**
     * Rythme d'alternance, dans l'ordre chronologique.
     *
     * @return list<CalendarPeriod>
     */
    public function getPeriods(): array
    {
        $rPeriods = $this->aPeriods->getValues();
        usort($rPeriods, static fn (CalendarPeriod $pLeft, CalendarPeriod $pRight): int => $pLeft->getStartDate() <=> $pRight->getStartDate());

        return $rPeriods;
    }

    /** Appelé par le constructeur de la période. */
    public function attachPeriod(CalendarPeriod $pPeriod): void
    {
        $this->aPeriods->add($pPeriod);
    }

    public function detachPeriod(CalendarPeriod $pPeriod): void
    {
        $this->aPeriods->removeElement($pPeriod);
    }

    /**
     * Évaluations prévues, de la plus proche à la plus lointaine.
     *
     * @return list<PlannedAssessment>
     */
    public function getAssessments(): array
    {
        $rAssessments = $this->aAssessments->getValues();
        usort($rAssessments, static fn (PlannedAssessment $pLeft, PlannedAssessment $pRight): int => $pLeft->getDate() <=> $pRight->getDate());

        return $rAssessments;
    }

    /** Appelé par le constructeur de l'évaluation prévue. */
    public function attachAssessment(PlannedAssessment $pAssessment): void
    {
        $this->aAssessments->add($pAssessment);
    }

    public function detachAssessment(PlannedAssessment $pAssessment): void
    {
        $this->aAssessments->removeElement($pAssessment);
    }

    /** Vrai si une période existante partage un jour avec l'intervalle donné : deux lieux le même jour seraient contradictoires. */
    public function hasPeriodOverlapping(\DateTimeImmutable $pStartDate, \DateTimeImmutable $pEndDate): bool
    {
        foreach ($this->aPeriods as $vPeriod) {
            if ($vPeriod->overlaps($pStartDate, $pEndDate)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->aName;
    }
}
