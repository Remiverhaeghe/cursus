<?php

namespace App\Tests\Unit\Security;

use App\Security\SecretCipher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecretCipher::class)]
final class SecretCipherTest extends TestCase
{
    private static function key(): string
    {
        return str_repeat('00112233', 8);
    }

    private static function otherKey(): string
    {
        return str_repeat('ffeeddcc', 8);
    }

    public function testRoundTripKeepsTheSecretAndHidesIt(): void
    {
        $vStored = (new SecretCipher(self::key()))->encrypt('JBSWY3DPEHPK3PXP');

        self::assertStringStartsWith('enc1:', $vStored);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $vStored);
        self::assertSame('JBSWY3DPEHPK3PXP', (new SecretCipher(self::key()))->decrypt($vStored));
    }

    public function testEachEncryptionUsesANewNonce(): void
    {
        $vCipher = new SecretCipher(self::key());

        self::assertNotSame($vCipher->encrypt('secret'), $vCipher->encrypt('secret'));
    }

    public function testValueWithoutPrefixIsReadAsIs(): void
    {
        self::assertSame('JBSWY3DPEHPK3PXP', (new SecretCipher(self::key()))->decrypt('JBSWY3DPEHPK3PXP'));
    }

    public function testAnotherKeyCannotRead(): void
    {
        $vStored = (new SecretCipher(self::key()))->encrypt('secret');

        $this->expectException(\RuntimeException::class);
        (new SecretCipher(self::otherKey()))->decrypt($vStored);
    }

    public function testAlteredValueIsRefused(): void
    {
        $vCipher = new SecretCipher(self::key());
        $vStored = $vCipher->encrypt('secret');
        $vAltered = substr($vStored, 0, -2).('AA' === substr($vStored, -2) ? 'BB' : 'AA');

        $this->expectException(\RuntimeException::class);
        $vCipher->decrypt($vAltered);
    }

    public function testTruncatedAndNonBase64ValuesAreRefused(): void
    {
        $vCipher = new SecretCipher(self::key());

        foreach (['enc1:', 'enc1:abcd', 'enc1:!!!'] as $vValue) {
            try {
                $vCipher->decrypt($vValue);
                self::fail('Valeur illisible acceptée : '.$vValue);
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testBadKeysAreRefused(): void
    {
        foreach (['', 'abc', str_repeat('z', 64), str_repeat('0', 62)] as $vKey) {
            try {
                new SecretCipher($vKey);
                self::fail('Clé acceptée : '.$vKey);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
