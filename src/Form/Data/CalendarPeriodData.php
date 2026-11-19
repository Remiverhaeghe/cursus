<?php

namespace App\Form\Data;

use App\Entity\CalendarPeriod;
use App\Entity\Cohort;
use App\Enum\EvaluationContext;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour une période d'alternance. */
final class CalendarPeriodData
{
    #[Assert\NotNull]
    public ?EvaluationContext $place = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $startDate = null;

    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: 'startDate', message: 'La période doit finir après son début.')]
    public ?\DateTimeImmutable $endDate = null;

    /** Deux périodes ne se chevauchent pas : un apprenti ne peut pas être à l'école et en entreprise le même jour. */
    public function overlapsAPeriodOf(Cohort $pCohort): bool
    {
        if (null === $this->startDate || null === $this->endDate) {
            throw new \LogicException('Période non validée.');
        }

        return $pCohort->hasPeriodOverlapping($this->startDate, $this->endDate);
    }

    public function toPeriod(Cohort $pCohort): CalendarPeriod
    {
        if (null === $this->place || null === $this->startDate || null === $this->endDate) {
            throw new \LogicException('Période non validée.');
        }

        return new CalendarPeriod($pCohort, $this->place, $this->startDate, $this->endDate);
    }
}
