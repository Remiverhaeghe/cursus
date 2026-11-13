<?php

namespace App\Repository;

use App\Entity\Apprenticeship;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Apprenticeship> */
class ApprenticeshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, Apprenticeship::class);
    }

    /** @return list<Apprenticeship> */
    public function findForTutor(User $pTutor): array
    {
        return $this->ordered($this->baseQuery()->andWhere('a.aTutor = :user')->setParameter('user', $pTutor));
    }

    /** @return list<Apprenticeship> */
    public function findForApprentice(User $pApprentice): array
    {
        return $this->ordered($this->baseQuery()->andWhere('a.aApprentice = :user')->setParameter('user', $pApprentice));
    }

    /**
     * Contrats des promotions dont l'utilisateur gère la formation.
     *
     * @return list<Apprenticeship>
     */
    public function findForManager(User $pManager): array
    {
        return $this->ordered($this->baseQuery()->andWhere('f.aManager = :user')->setParameter('user', $pManager));
    }

    private function baseQuery(): QueryBuilder
    {
        $rQuery = $this->createQueryBuilder('a')
            ->addSelect('ap', 'c', 'f')
            ->join('a.aApprentice', 'ap')
            ->join('a.aCohort', 'c')
            ->join('c.aFormation', 'f');

        return $rQuery;
    }

    /** @return list<Apprenticeship> */
    private function ordered(QueryBuilder $pQuery): array
    {
        /** @var list<Apprenticeship> $rApprenticeships */
        $rApprenticeships = $pQuery
            ->orderBy('c.aStartYear', 'DESC')
            ->addOrderBy('ap.aLastName')
            ->addOrderBy('ap.aFirstName')
            ->getQuery()
            ->getResult();

        return $rApprenticeships;
    }
}
