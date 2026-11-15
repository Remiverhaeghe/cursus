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

    /**
     * Contrats où le compte est apprenti ou tuteur, pour l'export de ses données.
     *
     * @return list<Apprenticeship>
     */
    public function findInvolving(User $pUser): array
    {
        return $this->ordered($this->baseQuery()->andWhere('a.aApprentice = :user OR a.aTutor = :user')->setParameter('user', $pUser));
    }

    /** Un contrat encore en cours (fin aujourd'hui ou plus tard) empêche de supprimer le compte de son apprenti ou de son tuteur. */
    public function hasCurrentContract(User $pUser, \DateTimeImmutable $pToday): bool
    {
        $vCount = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.aId)')
            ->andWhere('a.aApprentice = :user OR a.aTutor = :user')
            ->andWhere('a.aEndDate >= :today')
            ->setParameter('user', $pUser)
            ->setParameter('today', $pToday->setTime(0, 0), 'date_immutable')
            ->getQuery()
            ->getSingleScalarResult();

        return $vCount > 0;
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
