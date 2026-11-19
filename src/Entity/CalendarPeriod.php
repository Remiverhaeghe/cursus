<?php

namespace App\Entity;

use App\Enum\EvaluationContext;
use Doctrine\ORM\Mapping as ORM;

/** Période du rythme d'alternance d'une promotion : à l'école ou en entreprise, du premier au dernier jour inclus. */
#[ORM\Entity]
class CalendarPeriod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne(inversedBy: 'aPeriods')]
    #[ORM\JoinColumn(nullable: false)]
    private Cohort $aCohort;

    #[ORM\Column(length: 20, enumType: EvaluationContext::class)]
    private EvaluationContext $aPlace;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $aStartDate;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $aEndDate;

    public function __construct(Cohort $pCohort, EvaluationContext $pPlace, \DateTimeImmutable $pStartDate, \DateTimeImmutable $pEndDate)
    {
        // Seuls les jours comptent : une période d'un jour commence et finit à la même date
        $vStartDate = $pStartDate->setTime(0, 0);
        $vEndDate = $pEndDate->setTime(0, 0);
        if ($vEndDate < $vStartDate) {
            throw new \InvalidArgumentException('La période doit finir après son début.');
        }
        $this->aCohort = $pCohort;
        $this->aPlace = $pPlace;
        $this->aStartDate = $vStartDate;
        $this->aEndDate = $vEndDate;
        $pCohort->attachPeriod($this);
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getCohort(): Cohort
    {
        return $this->aCohort;
    }

    public function getFormation(): Formation
    {
        return $this->aCohort->getFormation();
    }

    public function getPlace(): EvaluationContext
    {
        return $this->aPlace;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->aStartDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->aEndDate;
    }

    /** Vrai si la période partage au moins un jour avec l'intervalle donné, bornes incluses. */
    public function overlaps(\DateTimeImmutable $pStartDate, \DateTimeImmutable $pEndDate): bool
    {
        return $this->aStartDate <= $pEndDate->setTime(0, 0) && $pStartDate->setTime(0, 0) <= $this->aEndDate;
    }
}
