<?php

namespace App\Repository;

use App\Entity\Apprenticeship;
use App\Entity\Evaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Evaluation> */
class EvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, Evaluation::class);
    }

    /**
     * Historique d'un contrat, du plus ancien au plus récent.
     *
     * @return list<Evaluation>
     */
    public function findForApprenticeship(Apprenticeship $pApprenticeship): array
    {
        /** @var list<Evaluation> $rEvaluations */
        $rEvaluations = $this->createQueryBuilder('e')
            ->addSelect('c', 'u')
            ->join('e.aCompetence', 'c')
            ->join('e.aEvaluator', 'u')
            ->andWhere('e.aApprenticeship = :apprenticeship')
            ->setParameter('apprenticeship', $pApprenticeship)
            ->orderBy('e.aEvaluatedAt')
            ->addOrderBy('e.aId')
            ->getQuery()
            ->getResult();

        return $rEvaluations;
    }
}
