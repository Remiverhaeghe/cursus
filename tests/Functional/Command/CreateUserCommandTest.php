<?php

namespace App\Tests\Functional\Command;

use App\Command\CreateUserCommand;
use App\Entity\AuditEntry;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(CreateUserCommand::class)]
final class CreateUserCommandTest extends KernelTestCase
{
    private function tester(): CommandTester
    {
        $vApplication = new Application(self::bootKernel());

        return new CommandTester($vApplication->find('app:user:create'));
    }

    public function testCreatesAnAccountWithAGeneratedPasswordShownOnce(): void
    {
        $vTester = $this->tester();

        $vStatus = $vTester->execute(['email' => 'Nouveau.Tuteur@cursus.test', 'role' => 'tutor', 'firstname' => 'Jules', 'lastname' => 'Marchand']);

        self::assertSame(Command::SUCCESS, $vStatus);
        $vDisplay = $vTester->getDisplay();
        self::assertStringContainsString('Compte Tuteur créé pour nouveau.tuteur@cursus.test', $vDisplay);
        self::assertSame(1, preg_match('/affiché une seule fois\) : ([0-9a-f]{18})/', $vDisplay, $vMatch));
        self::assertStringContainsString('double authentification', $vDisplay);
        $vUser = static::getContainer()->get(UserRepository::class)->findOneByEmail('nouveau.tuteur@cursus.test');
        self::assertNotNull($vUser);
        self::assertSame(UserRole::Tutor, $vUser->getRole());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($vUser, $vMatch[1]));
        $vEntries = static::getContainer()->get('doctrine')->getManager()->getRepository(AuditEntry::class)->findBy(['aAction' => 'compte_cree']);
        self::assertNotSame([], $vEntries);
    }

    public function testChosenPasswordIsUsedAndNotPrinted(): void
    {
        $vTester = $this->tester();

        $vStatus = $vTester->execute(['email' => 'nouvel.apprenti@cursus.test', 'role' => 'ROLE_APPRENTICE', 'firstname' => 'Lina', 'lastname' => 'Dubois', '--password' => 'un-mot-de-passe-long']);

        self::assertSame(Command::SUCCESS, $vStatus);
        self::assertStringNotContainsString('un-mot-de-passe-long', $vTester->getDisplay());
        self::assertStringNotContainsString('double authentification', $vTester->getDisplay());
        $vUser = static::getContainer()->get(UserRepository::class)->findOneByEmail('nouvel.apprenti@cursus.test');
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($vUser, 'un-mot-de-passe-long'));
    }

    public function testRefusals(): void
    {
        $vTester = $this->tester();
        $vBase = ['email' => 'refus@cursus.test', 'role' => 'school', 'firstname' => 'A', 'lastname' => 'B'];

        self::assertSame(Command::INVALID, $vTester->execute([...$vBase, 'role' => 'directeur']));
        self::assertStringContainsString('Profil inconnu', $vTester->getDisplay());
        self::assertSame(Command::INVALID, $vTester->execute([...$vBase, 'email' => 'pas-une-adresse']));
        self::assertStringContainsString('Adresse e-mail invalide', $vTester->getDisplay());
        self::assertSame(Command::INVALID, $vTester->execute([...$vBase, '--password' => 'court']));
        self::assertStringContainsString('au moins 12 caractères', $vTester->getDisplay());
        self::assertSame(Command::FAILURE, $vTester->execute([...$vBase, 'email' => 'claire.fontaine@cursus.test']));
        self::assertStringContainsString('existe déjà', $vTester->getDisplay());
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneByEmail('refus@cursus.test'));
    }

    public function testRoleNamesAreAccepted(): void
    {
        self::assertSame(UserRole::School, CreateUserCommand::parseRole('SCHOOL'));
        self::assertSame(UserRole::Apprentice, CreateUserCommand::parseRole(' apprentice '));
        self::assertSame(UserRole::Tutor, CreateUserCommand::parseRole('ROLE_TUTOR'));
        self::assertNull(CreateUserCommand::parseRole('autre'));
    }
}
