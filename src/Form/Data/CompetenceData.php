<?php

namespace App\Form\Data;

use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour ajouter une compétence à un bloc. */
final class CompetenceData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $label = '';

    public function toCompetence(CompetenceBlock $pBlock): Competence
    {
        return new Competence($pBlock, trim($this->code), trim($this->label));
    }
}
