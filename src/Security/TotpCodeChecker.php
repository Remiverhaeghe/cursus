<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Vérificateur TOTP de Cursus : déchiffre le secret avant de le confier à celui du bundle,
 * et accepte à sa place un code de secours encore valable, qui est alors consommé.
 */
#[AsDecorator('scheb_two_factor.security.totp_authenticator')]
final class TotpCodeChecker implements TotpAuthenticatorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly TotpAuthenticatorInterface $aInner,
        private readonly SecretCipher $aCipher,
        private readonly EntityManagerInterface $aEntityManager,
    ) {
    }

    public function checkCode(TwoFactorInterface $user, string $code): bool
    {
        if ($this->aInner->checkCode($this->readable($user), $code)) {
            return true;
        }
        if ($user instanceof User && $user->consumeBackupCode($code)) {
            $this->aEntityManager->flush();

            return true;
        }

        return false;
    }

    public function getQRContent(TwoFactorInterface $user): string
    {
        return $this->aInner->getQRContent($this->readable($user));
    }

    public function generateSecret(): string
    {
        return $this->aInner->generateSecret();
    }

    private function readable(TwoFactorInterface $pUser): TwoFactorInterface
    {
        $vConfiguration = $pUser->getTotpAuthenticationConfiguration();
        if (null === $vConfiguration) {
            return $pUser;
        }

        return new PlainTotpSubject($pUser->getTotpAuthenticationUsername(), $this->aCipher->decrypt($vConfiguration->getSecret()));
    }
}
