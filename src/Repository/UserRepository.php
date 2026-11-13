<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $pRegistry)
    {
        parent::__construct($pRegistry, User::class);
    }

    /** Réécrit l'empreinte quand l'algorithme de hachage évolue (appelé par Symfony à la connexion). */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            return;
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findOneByEmail(string $pEmail): ?User
    {
        $rUser = $this->findOneBy(['aEmail' => mb_strtolower(trim($pEmail))]);

        return $rUser;
    }

    /** Comptes d'un profil triés par nom, pour les listes déroulantes. */
    public function queryByRole(UserRole $pRole): QueryBuilder
    {
        $rQuery = $this->createQueryBuilder('u')
            ->andWhere('u.aRole = :role')
            ->setParameter('role', $pRole->value)
            ->orderBy('u.aLastName')
            ->addOrderBy('u.aFirstName');

        return $rQuery;
    }
}
