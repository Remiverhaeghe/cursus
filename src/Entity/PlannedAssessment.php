<?php

namespace App\Entity;

use App\Enum\EvaluationContext;
use Doctrine\ORM\Mapping as ORM;

/** Évaluation prévue au calendrier d'une promotion : épreuve à l'école ou bilan en entreprise. */
#[ORM\Entity]
class PlannedAssessment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne(inversedBy: 'aAssessments')]
    #[ORM\JoinColumn(nullable: false)]
    private Cohort $aCohort;

    #[ORM\Column(length: 150)]
    private string $aTitle;

    #[ORM\Column(length: 20, enumType: EvaluationContext::class)]
    private EvaluationContext $aPlace;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $aDate;

    public function __construct(Cohort $pCohort, string $pTitle, EvaluationContext $pPlace, \DateTimeImmutable $pDate)
    {
        $vTitle = trim($pTitle);
        if ('' === $vTitle) {
            throw new \InvalidArgumentException('Une évaluation prévue porte un intitulé.');
        }
        $this->aCohort = $pCohort;
        $this->aTitle = $vTitle;
        $this->aPlace = $pPlace;
        $this->aDate = $pDate->setTime(0, 0);
        $pCohort->attachAssessment($this);
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

    public function getTitle(): string
    {
        return $this->aTitle;
    }

    public function getPlace(): EvaluationContext
    {
        return $this->aPlace;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->aDate;
    }
}
