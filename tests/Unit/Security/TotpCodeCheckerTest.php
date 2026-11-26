<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Enum\UserRole;
use App\Security\PlainTotpSubject;
use App\Security\SecretCipher;
use App\Security\TotpCodeChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

#[CoversClass(TotpCodeChecker::class)]
#[CoversClass(PlainTotpSubject::class)]
final class TotpCodeCheckerTest extends TestCase
{
    private static function key(): string
    {
        return str_repeat('00112233', 8);
    }

    private SecretCipher $aCipher;

    protected function setUp(): void
    {
        $this->aCipher = new SecretCipher(self::key());
    }

    private function user(): User
    {
        $rUser = new User('lea@cursus.test', UserRole::Tutor, 'Léa', 'Martin');
        $rUser->enableTotp($this->aCipher->encrypt('JBSWY3DPEHPK3PXP'));
        $rUser->replaceBackupCodes(['abcde-fghjk']);

        return $rUser;
    }

    public function testTheInnerCheckerReceivesTheDecryptedSecret(): void
    {
        $vSeen = null;
        $vInner = $this->createMock(TotpAuthenticatorInterface::class);
        $vInner->method('checkCode')->willReturnCallback(static function (TwoFactorInterface $pSubject, string $pCode) use (&$vSeen): bool {
            $vSeen = [$pSubject->getTotpAuthenticationUsername(), $pSubject->getTotpAuthenticationConfiguration()?->getSecret(), $pCode];

            return true;
        });
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::never())->method('flush');

        self::assertTrue((new TotpCodeChecker($vInner, $this->aCipher, $vEntityManager))->checkCode($this->user(), '123456'));
        self::assertSame(['lea@cursus.test', 'JBSWY3DPEHPK3PXP', '123456'], $vSeen);
    }

    public function testBackupCodeIsAcceptedOnceAndSaved(): void
    {
        $vInner = $this->createStub(TotpAuthenticatorInterface::class);
        $vInner->method('checkCode')->willReturn(false);
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::once())->method('flush');
        $vUser = $this->user();
        $vChecker = new TotpCodeChecker($vInner, $this->aCipher, $vEntityManager);

        self::assertTrue($vChecker->checkCode($vUser, 'ABCDE-fghjk'));
        self::assertFalse($vChecker->checkCode($vUser, 'abcde-fghjk'));
        self::assertSame(0, $vUser->countBackupCodes());
    }

    public function testWrongCodeIsRefusedWithoutSaving(): void
    {
        $vInner = $this->createStub(TotpAuthenticatorInterface::class);
        $vInner->method('checkCode')->willReturn(false);
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::never())->method('flush');

        self::assertFalse((new TotpCodeChecker($vInner, $this->aCipher, $vEntityManager))->checkCode($this->user(), '000000'));
    }

    public function testQrContentAndSecretGenerationGoThroughTheInnerChecker(): void
    {
        $vInner = $this->createMock(TotpAuthenticatorInterface::class);
        $vInner->method('getQRContent')->willReturnCallback(static fn (TwoFactorInterface $pSubject): string => 'otpauth://'.$pSubject->getTotpAuthenticationConfiguration()?->getSecret());
        $vInner->method('generateSecret')->willReturn('NOUVEAUSECRET');
        $vChecker = new TotpCodeChecker($vInner, $this->aCipher, $this->createStub(EntityManagerInterface::class));

        self::assertSame('otpauth://JBSWY3DPEHPK3PXP', $vChecker->getQRContent($this->user()));
        self::assertSame('NOUVEAUSECRET', $vChecker->generateSecret());
    }

    public function testAnAccountWithoutTotpIsPassedAsIs(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::Apprentice, 'Léa', 'Martin');
        $vInner = $this->createMock(TotpAuthenticatorInterface::class);
        $vInner->expects(self::once())->method('checkCode')->with($vUser, '123456')->willReturn(false);

        self::assertFalse((new TotpCodeChecker($vInner, $this->aCipher, $this->createStub(EntityManagerInterface::class)))->checkCode($vUser, '123456'));
    }
}
