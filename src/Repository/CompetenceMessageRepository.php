<?php

namespace App\Repository;

use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\CompetenceMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CompetenceMessage> */
class CompetenceMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, CompetenceMessage::class);
    }

    /**
     * Fil d'une compétence pour un contrat, du plus ancien au plus récent.
     *
     * @return list<CompetenceMessage>
     */
    public function findThread(Apprenticeship $pApprenticeship, Competence $pCompetence): array
    {
        /** @var list<CompetenceMessage> $rMessages */
        $rMessages = $this->createQueryBuilder('m')
            ->addSelect('u')
            ->join('m.aAuthor', 'u')
            ->andWhere('m.aApprenticeship = :apprenticeship')
            ->andWhere('m.aCompetence = :competence')
            ->setParameter('apprenticeship', $pApprenticeship)
            ->setParameter('competence', $pCompetence)
            ->orderBy('m.aPostedAt')
            ->addOrderBy('m.aId')
            ->getQuery()
            ->getResult();

        return $rMessages;
    }

    /**
     * Nombre de messages et date du dernier, par identifiant de compétence, pour la liste des fils d'un contrat.
     *
     * @return array<int, array{count: int, last: \DateTimeImmutable}>
     */
    public function summarize(Apprenticeship $pApprenticeship): array
    {
        /** @var list<array{competence: int|string, total: int|string, last: string}> $vRows */
        $vRows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.aCompetence) AS competence', 'COUNT(m.aId) AS total', 'MAX(m.aPostedAt) AS last')
            ->andWhere('m.aApprenticeship = :apprenticeship')
            ->setParameter('apprenticeship', $pApprenticeship)
            ->groupBy('m.aCompetence')
            ->getQuery()
            ->getArrayResult();

        $rSummary = [];
        foreach ($vRows as $vRow) {
            $rSummary[(int) $vRow['competence']] = ['count' => (int) $vRow['total'], 'last' => new \DateTimeImmutable($vRow['last'])];
        }

        return $rSummary;
    }

    /**
     * Messages écrits par un compte ou échangés sur son contrat d'apprenti, pour l'export de ses données.
     *
     * @return list<CompetenceMessage>
     */
    public function findInvolving(User $pUser): array
    {
        /** @var list<CompetenceMessage> $rMessages */
        $rMessages = $this->createQueryBuilder('m')
            ->join('m.aApprenticeship', 'a')
            ->andWhere('a.aApprentice = :user OR m.aAuthor = :user')
            ->setParameter('user', $pUser)
            ->orderBy('m.aPostedAt')
            ->addOrderBy('m.aId')
            ->getQuery()
            ->getResult();

        return $rMessages;
    }
}
