<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PasswordResetToken> */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, PasswordResetToken::class);
    }

    public function findOneByToken(string $pToken): ?PasswordResetToken
    {
        return $this->findOneBy(['aTokenHash' => PasswordResetToken::hash($pToken)]);
    }

    /** Supprime toutes les demandes d'un compte : après un changement de mot de passe ou une suppression du compte. */
    public function removeForUser(User $pUser): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.aUser = :user')
            ->setParameter('user', $pUser)
            ->getQuery()
            ->execute();
    }
}
