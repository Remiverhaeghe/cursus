<?php

namespace App\Form\Data;

use Symfony\Component\Validator\Constraints as Assert;

/** Adresse saisie pour recevoir un lien de nouveau mot de passe. */
final class PasswordResetRequestData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';
}
