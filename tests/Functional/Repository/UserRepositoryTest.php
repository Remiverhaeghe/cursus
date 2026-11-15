<?php

namespace App\Tests\Functional\Repository;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends KernelTestCase
{
    public function testEmailSearchIgnoresCaseAccentsAndSpaces(): void
    {
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vEntityManager->persist(new User('élodie.roy@cursus.test', UserRole::Apprentice, 'Élodie', 'Roy'));
        $vEntityManager->flush();
        $vUsers = static::getContainer()->get(UserRepository::class);

        self::assertSame('élodie.roy@cursus.test', $vUsers->findOneByEmail('ÉLODIE.ROY@cursus.test')?->getEmail());
        self::assertSame(UserFixtures::APPRENTICE, $vUsers->findOneByEmail('  '.UserFixtures::APPRENTICE.'  ')?->getEmail());
        self::assertNull($vUsers->findOneByEmail('personne@cursus.test'));
    }
}
