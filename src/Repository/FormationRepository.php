<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Formation> */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, Formation::class);
    }

    /** @return list<Formation> */
    public function findManagedBy(User $pManager): array
    {
        /** @var list<Formation> $rFormations */
        $rFormations = $this->createQueryBuilder('f')
            ->andWhere('f.aManager = :manager')
            ->setParameter('manager', $pManager)
            ->orderBy('f.aName')
            ->getQuery()
            ->getResult();

        return $rFormations;
    }
}
