<?php

namespace App\Form\Data;

use App\Entity\Company;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour ajouter une entreprise d'accueil. */
final class CompanyData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $city = '';

    public function toCompany(): Company
    {
        return new Company(trim($this->name), trim($this->city));
    }
}
