<?php

namespace App\Security;

use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

/** Compte vu par le vérificateur TOTP avec son secret déchiffré, le temps d'un contrôle. */
final class PlainTotpSubject implements TwoFactorInterface
{
    public function __construct(private readonly string $aUsername, private readonly string $aSecret)
    {
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return true;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->aUsername;
    }

    public function getTotpAuthenticationConfiguration(): TotpConfigurationInterface
    {
        return new TotpConfiguration($this->aSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
