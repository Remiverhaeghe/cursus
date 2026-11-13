<?php

namespace App\Form\Data;

use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour ajouter un bloc de compétences. */
final class CompetenceBlockData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    public function toBlock(Formation $pFormation): CompetenceBlock
    {
        return new CompetenceBlock($pFormation, trim($this->code), trim($this->title));
    }
}
