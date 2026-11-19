<?php

namespace App\Form\Data;

use App\Entity\Cohort;
use App\Entity\PlannedAssessment;
use App\Enum\EvaluationContext;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour une évaluation prévue au calendrier. */
final class PlannedAssessmentData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public ?string $title = null;

    #[Assert\NotNull]
    public ?EvaluationContext $place = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $date = null;

    public function toAssessment(Cohort $pCohort): PlannedAssessment
    {
        if (null === $this->title || null === $this->place || null === $this->date) {
            throw new \LogicException('Évaluation prévue non validée.');
        }

        return new PlannedAssessment($pCohort, $this->title, $this->place, $this->date);
    }
}
