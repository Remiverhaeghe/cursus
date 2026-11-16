<?php

namespace App\Form\Data;

use App\Entity\Formation;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour créer une formation. */
final class FormationData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $diploma = '';

    public function toFormation(User $pManager): Formation
    {
        return new Formation(trim($this->name), trim($this->diploma), $pManager);
    }
}
