<?php

namespace App\Form\Data;

use Symfony\Component\Validator\Constraints as Assert;

/** Nouveau mot de passe : 12 caractères au moins, absent des fuites connues. */
final class NewPasswordData
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 4096)]
    #[Assert\NotCompromisedPassword(message: 'Ce mot de passe figure dans une fuite de données connue, choisis-en un autre.')]
    public ?string $password = null;
}
