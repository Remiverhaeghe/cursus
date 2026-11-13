<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;

/** Entreprise d'accueil d'un ou plusieurs apprentis. */
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\Column(length: 150)]
    private string $aName;

    #[ORM\Column(length: 100)]
    private string $aCity;

    public function __construct(string $pName, string $pCity)
    {
        $this->aName = $pName;
        $this->aCity = $pCity;
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getName(): string
    {
        return $this->aName;
    }

    public function getCity(): string
    {
        return $this->aCity;
    }

    public function __toString(): string
    {
        return $this->aName.' ('.$this->aCity.')';
    }
}
