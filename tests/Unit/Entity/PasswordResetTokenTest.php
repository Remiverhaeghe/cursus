<?php

namespace App\Tests\Unit\Entity;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordResetToken::class)]
#[CoversClass(User::class)]
final class PasswordResetTokenTest extends TestCase
{
    public function testOnlyTheHashIsKeptAndItLastsThirtyMinutes(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::Apprentice, 'Léa', 'Martin');
        $vNow = new \DateTimeImmutable('2026-11-15 10:00:00');

        $vRequest = new PasswordResetToken($vUser, 'jeton-secret', $vNow);

        self::assertNull($vRequest->getId());
        self::assertSame($vUser, $vRequest->getUser());
        self::assertSame(hash('sha256', 'jeton-secret'), $vRequest->getTokenHash());
        self::assertSame(PasswordResetToken::hash('jeton-secret'), $vRequest->getTokenHash());
        self::assertSame('2026-11-15 10:30:00', $vRequest->getExpiresAt()->format('Y-m-d H:i:s'));
        self::assertFalse($vRequest->isExpired(new \DateTimeImmutable('2026-11-15 10:29:59')));
        self::assertTrue($vRequest->isExpired(new \DateTimeImmutable('2026-11-15 10:30:00')));
    }

    public function testAnonymizedAccountLosesItsIdentity(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::School, 'Léa', 'Martin');
        (new \ReflectionProperty(User::class, 'aId'))->setValue($vUser, 42);
        $vUser->setPassword('empreinte');
        $vUser->enableTotp('JBSWY3DPEHPK3PXP');

        $vUser->anonymize();

        self::assertSame('compte-supprime-42@cursus.invalid', $vUser->getEmail());
        self::assertSame('Compte supprimé', $vUser->getFullName());
        self::assertSame('', $vUser->getPassword());
        self::assertFalse($vUser->isTotpAuthenticationEnabled());
        self::assertSame(UserRole::School, $vUser->getRole());
    }
}
