<?php

namespace App\Tests\Support;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Base des tests fonctionnels : base de test rechargée avec les fixtures fictives avant chaque test. */
abstract class AppWebTestCase extends WebTestCase
{
    /** Secret TOTP de test, attribué aux comptes qui doivent avoir la double authentification active. */
    public const string TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    protected KernelBrowser $aClient;

    protected function setUp(): void
    {
        $this->aClient = static::createClient();
        $vEntityManager = $this->entityManager();
        $vLoader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($vEntityManager, new ORMPurger($vEntityManager)))->execute($vLoader->getFixtures());
    }

    protected function entityManager(): EntityManagerInterface
    {
        $rEntityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $rEntityManager;
    }

    protected function user(string $pEmail): User
    {
        $rUser = static::getContainer()->get(UserRepository::class)->findOneByEmail($pEmail);
        if (null === $rUser) {
            throw new \LogicException(\sprintf('Compte de test absent : %s', $pEmail));
        }

        return $rUser;
    }

    /** Connecte directement un compte ; l'école et les tuteurs reçoivent un secret TOTP pour passer l'obligation d'activation. */
    protected function loginAs(string $pEmail): User
    {
        $rUser = $this->user($pEmail);
        if ($rUser->getRole()->requiresTotp() && !$rUser->isTotpAuthenticationEnabled()) {
            $rUser->enableTotp(self::TOTP_SECRET);
            $this->entityManager()->flush();
        }
        $this->aClient->loginUser($rUser);

        return $rUser;
    }

    /**
     * Retrouve une entité des fixtures par ses attributs.
     *
     * @template T of object
     *
     * @param class-string<T>      $pClass
     * @param array<string, mixed> $pCriteria
     *
     * @return T
     */
    protected function find(string $pClass, array $pCriteria): object
    {
        $rEntity = $this->entityManager()->getRepository($pClass)->findOneBy($pCriteria);
        if (null === $rEntity) {
            throw new \LogicException(\sprintf('Donnée de test absente : %s', $pClass));
        }

        return $rEntity;
    }

    /** Recharge une entité depuis la base, après une requête qui l'a modifiée. */
    protected function refreshed(User $pUser): User
    {
        $rUser = $this->user($pUser->getEmail());
        $this->entityManager()->refresh($rUser);

        return $rUser;
    }
}
