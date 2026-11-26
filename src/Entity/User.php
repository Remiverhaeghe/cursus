<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Security\BackupCodes;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Compte d'un membre de l'école, d'un tuteur ou d'un apprenti, avec sa double authentification facultative. */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    /** @var non-empty-string */
    #[ORM\Column(length: 180)]
    private string $aEmail;

    #[ORM\Column]
    private string $aPassword = '';

    #[ORM\Column(length: 20, enumType: UserRole::class)]
    private UserRole $aRole;

    #[ORM\Column(length: 100)]
    private string $aFirstName;

    #[ORM\Column(length: 100)]
    private string $aLastName;

    /** Secret TOTP chiffré par App\Security\SecretCipher. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $aTotpSecret = null;

    /** @var list<string> empreintes des codes de secours encore valables */
    #[ORM\Column(type: Types::JSON)]
    private array $aBackupCodes = [];

    public function __construct(string $pEmail, UserRole $pRole, string $pFirstName, string $pLastName)
    {
        $vEmail = mb_strtolower(trim($pEmail));
        if ('' === $vEmail) {
            throw new \InvalidArgumentException('L\'adresse e-mail est obligatoire.');
        }
        $this->aEmail = $vEmail;
        $this->aRole = $pRole;
        $this->aFirstName = $pFirstName;
        $this->aLastName = $pLastName;
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getEmail(): string
    {
        return $this->aEmail;
    }

    public function getUserIdentifier(): string
    {
        return $this->aEmail;
    }

    public function getRole(): UserRole
    {
        return $this->aRole;
    }

    public function hasRole(UserRole $pRole): bool
    {
        return $pRole === $this->aRole;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return [$this->aRole->value, 'ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->aPassword;
    }

    public function setPassword(string $pHashedPassword): void
    {
        $this->aPassword = $pHashedPassword;
    }

    public function getFirstName(): string
    {
        return $this->aFirstName;
    }

    public function getLastName(): string
    {
        return $this->aLastName;
    }

    public function getFullName(): string
    {
        return $this->aFirstName.' '.$this->aLastName;
    }

    /** Le secret est confié déjà chiffré (SecretCipher::encrypt). */
    public function enableTotp(string $pSecret): void
    {
        $this->aTotpSecret = $pSecret;
    }

    public function disableTotp(): void
    {
        $this->aTotpSecret = null;
        $this->aBackupCodes = [];
    }

    /**
     * Remplace tous les codes de secours par ceux-ci ; seules leurs empreintes sont gardées.
     *
     * @param list<string> $pPlainCodes
     */
    public function replaceBackupCodes(array $pPlainCodes): void
    {
        $this->aBackupCodes = array_map(BackupCodes::hash(...), $pPlainCodes);
    }

    public function countBackupCodes(): int
    {
        return \count($this->aBackupCodes);
    }

    /** Consomme un code de secours : vrai une seule fois par code. */
    public function consumeBackupCode(string $pCode): bool
    {
        $vIndex = array_search(BackupCodes::hash($pCode), $this->aBackupCodes, true);
        if (false === $vIndex) {
            return false;
        }
        unset($this->aBackupCodes[$vIndex]);
        $this->aBackupCodes = array_values($this->aBackupCodes);

        return true;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->aTotpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->aEmail;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->aTotpSecret) {
            return null;
        }

        $rConfiguration = new TotpConfiguration($this->aTotpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);

        return $rConfiguration;
    }

    /**
     * Suppression du compte : l'identité est effacée, la ligne reste pour que les évaluations
     * déjà enregistrées gardent leur évaluateur, devenu anonyme. Plus aucune connexion possible.
     */
    public function anonymize(): void
    {
        $this->aEmail = \sprintf('compte-supprime-%d@cursus.invalid', $this->aId);
        $this->aFirstName = 'Compte';
        $this->aLastName = 'supprimé';
        $this->aPassword = '';
        $this->aTotpSecret = null;
        $this->aBackupCodes = [];
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
