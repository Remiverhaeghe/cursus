<?php

namespace App\Repository;

use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Company> */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, Company::class);
    }

    /** @return list<Company> */
    public function findAllByName(): array
    {
        return $this->findBy([], ['aName' => 'ASC']);
    }
}
