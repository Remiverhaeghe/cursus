<?php

namespace App\Repository;

use App\Entity\AuditEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditEntry> */
class AuditEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, AuditEntry::class);
    }

    /** @return list<AuditEntry> les plus récentes d'abord */
    public function findLatest(int $pLimit): array
    {
        /** @var list<AuditEntry> $rEntries */
        $rEntries = $this->createQueryBuilder('e')
            ->addSelect('a')
            ->leftJoin('e.aActor', 'a')
            ->orderBy('e.aOccurredAt', 'DESC')
            ->addOrderBy('e.aId', 'DESC')
            ->setMaxResults($pLimit)
            ->getQuery()
            ->getResult();

        return $rEntries;
    }
}
