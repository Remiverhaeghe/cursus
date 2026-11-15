<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testEmailIsNormalised(): void
    {
        $vUser = new User('  Lea.Martin@Cursus.TEST ', UserRole::Apprentice, 'Léa', 'Martin');

        self::assertSame('lea.martin@cursus.test', $vUser->getEmail());
        self::assertSame('lea.martin@cursus.test', $vUser->getUserIdentifier());
        self::assertSame('lea.martin@cursus.test', $vUser->getTotpAuthenticationUsername());
    }

    public function testAccentedEmailIsLowercased(): void
    {
        self::assertSame('élodie@cursus.test', (new User('ÉLODIE@cursus.test', UserRole::Apprentice, 'Élodie', 'Roy'))->getEmail());
    }

    public function testEmptyEmailIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('   ', UserRole::Apprentice, 'Léa', 'Martin');
    }

    public function testIdentityAndRoles(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::Tutor, 'Léa', 'Martin');

        self::assertNull($vUser->getId());
        self::assertSame(UserRole::Tutor, $vUser->getRole());
        self::assertTrue($vUser->hasRole(UserRole::Tutor));
        self::assertFalse($vUser->hasRole(UserRole::School));
        self::assertSame(['ROLE_TUTOR', 'ROLE_USER'], $vUser->getRoles());
        self::assertSame('Léa', $vUser->getFirstName());
        self::assertSame('Martin', $vUser->getLastName());
        self::assertSame('Léa Martin', $vUser->getFullName());
        self::assertSame('Léa Martin', (string) $vUser);
    }

    public function testPasswordIsStoredAsGiven(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::Apprentice, 'Léa', 'Martin');
        self::assertSame('', $vUser->getPassword());

        $vUser->setPassword('empreinte');

        self::assertSame('empreinte', $vUser->getPassword());
    }

    public function testTotpCanBeEnabledAndDisabled(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::School, 'Léa', 'Martin');
        self::assertFalse($vUser->isTotpAuthenticationEnabled());
        self::assertNull($vUser->getTotpAuthenticationConfiguration());

        $vUser->enableTotp('JBSWY3DPEHPK3PXP');
        $vConfiguration = $vUser->getTotpAuthenticationConfiguration();

        self::assertTrue($vUser->isTotpAuthenticationEnabled());
        self::assertNotNull($vConfiguration);
        self::assertSame('JBSWY3DPEHPK3PXP', $vConfiguration->getSecret());
        self::assertSame('sha1', $vConfiguration->getAlgorithm());
        self::assertSame(30, $vConfiguration->getPeriod());
        self::assertSame(6, $vConfiguration->getDigits());

        $vUser->disableTotp();

        self::assertFalse($vUser->isTotpAuthenticationEnabled());
    }
}
