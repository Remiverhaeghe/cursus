<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserBackupCodesTest extends TestCase
{
    private function user(): User
    {
        $rUser = new User('lea@cursus.test', UserRole::Tutor, 'Léa', 'Martin');
        $rUser->enableTotp('enc1:xxxx');
        $rUser->replaceBackupCodes(['abcde-fghjk', 'mnpqr-stuvw']);

        return $rUser;
    }

    public function testACodeWorksOnceOnly(): void
    {
        $vUser = $this->user();

        self::assertSame(2, $vUser->countBackupCodes());
        self::assertTrue($vUser->consumeBackupCode('ABCDE-FGHJK'));
        self::assertFalse($vUser->consumeBackupCode('abcde-fghjk'));
        self::assertSame(1, $vUser->countBackupCodes());
        self::assertTrue($vUser->consumeBackupCode('mnpqrstuvw'));
        self::assertSame(0, $vUser->countBackupCodes());
    }

    public function testAnUnknownCodeChangesNothing(): void
    {
        $vUser = $this->user();

        self::assertFalse($vUser->consumeBackupCode('zzzzz-zzzzz'));
        self::assertSame(2, $vUser->countBackupCodes());
    }

    public function testReplacingDropsTheOldCodes(): void
    {
        $vUser = $this->user();

        $vUser->replaceBackupCodes(['22222-33333']);

        self::assertFalse($vUser->consumeBackupCode('abcde-fghjk'));
        self::assertTrue($vUser->consumeBackupCode('22222-33333'));
    }

    public function testDisablingTotpOrDeletingTheAccountForgetsTheCodes(): void
    {
        $vDisabled = $this->user();
        $vDisabled->disableTotp();
        $vDeleted = $this->user();
        $vDeleted->anonymize();

        self::assertSame(0, $vDisabled->countBackupCodes());
        self::assertSame(0, $vDeleted->countBackupCodes());
        self::assertFalse($vDeleted->isTotpAuthenticationEnabled());
    }
}
