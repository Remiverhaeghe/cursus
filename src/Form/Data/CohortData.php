<?php

namespace App\Form\Data;

use App\Entity\Cohort;
use App\Entity\Formation;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour ouvrir une promotion. */
final class CohortData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $name = '';

    #[Assert\NotNull]
    #[Assert\Range(min: 2000, max: 2100)]
    public ?int $startYear = null;

    public function toCohort(Formation $pFormation): Cohort
    {
        return new Cohort($pFormation, trim($this->name), $this->startYear ?? throw new \LogicException('Année d\'entrée non validée.'));
    }
}
