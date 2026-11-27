<?php

namespace App\Security;

/**
 * Chiffre les secrets gardés en base (secret TOTP) avec libsodium : une clé de 32 octets en hexadécimal,
 * un nonce neuf à chaque chiffrement. Une valeur sans préfixe est lue telle quelle (secret enregistré avant le chiffrement).
 */
final class SecretCipher
{
    public const string PREFIX = 'enc1:';

    private readonly string $aKey;

    public function __construct(string $pHexKey)
    {
        $vKey = 2 * \SODIUM_CRYPTO_SECRETBOX_KEYBYTES === \strlen($pHexKey) && ctype_xdigit($pHexKey) ? hex2bin($pHexKey) : false;
        if (false === $vKey || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($vKey)) {
            throw new \InvalidArgumentException('APP_ENCRYPTION_KEY doit contenir 64 caractères hexadécimaux (openssl rand -hex 32).');
        }
        $this->aKey = $vKey;
    }

    public function encrypt(string $pPlain): string
    {
        $vNonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX.base64_encode($vNonce.sodium_crypto_secretbox($pPlain, $vNonce, $this->aKey));
    }

    public function decrypt(string $pStored): string
    {
        if (!str_starts_with($pStored, self::PREFIX)) {
            return $pStored;
        }
        $vRaw = base64_decode(substr($pStored, \strlen(self::PREFIX)), true);
        if (false === $vRaw || \strlen($vRaw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Secret chiffré illisible.');
        }
        $vPlain = sodium_crypto_secretbox_open(
            substr($vRaw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($vRaw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->aKey,
        );
        if (false === $vPlain) {
            throw new \RuntimeException('Secret chiffré illisible : mauvaise clé ou valeur modifiée.');
        }

        return $vPlain;
    }
}
