<?php

namespace App\Repository;

use App\Entity\Apprenticeship;
use App\Entity\Evaluation;
use App\Entity\User;
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

    /**
     * Évaluations de plusieurs contrats en une requête, rangées par identifiant de contrat.
     *
     * @param list<Apprenticeship> $pApprenticeships
     *
     * @return array<int, list<Evaluation>>
     */
    public function findGroupedByApprenticeship(array $pApprenticeships): array
    {
        if ([] === $pApprenticeships) {
            return [];
        }
        /** @var list<Evaluation> $vEvaluations */
        $vEvaluations = $this->createQueryBuilder('e')
            ->andWhere('e.aApprenticeship IN (:apprenticeships)')
            ->setParameter('apprenticeships', $pApprenticeships)
            ->orderBy('e.aEvaluatedAt')
            ->addOrderBy('e.aId')
            ->getQuery()
            ->getResult();

        $rGroups = [];
        foreach ($vEvaluations as $vEvaluation) {
            $rGroups[(int) $vEvaluation->getApprenticeship()->getId()][] = $vEvaluation;
        }

        return $rGroups;
    }

    /**
     * Évaluations reçues (apprenti) ou données (école, tuteur) par un compte, pour l'export de ses données.
     *
     * @return list<Evaluation>
     */
    public function findInvolving(User $pUser): array
    {
        /** @var list<Evaluation> $rEvaluations */
        $rEvaluations = $this->createQueryBuilder('e')
            ->join('e.aApprenticeship', 'a')
            ->andWhere('a.aApprentice = :user OR e.aEvaluator = :user')
            ->setParameter('user', $pUser)
            ->orderBy('e.aEvaluatedAt')
            ->addOrderBy('e.aId')
            ->getQuery()
            ->getResult();

        return $rEvaluations;
    }
}
