<?php

namespace App\Entity;

use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/** Demande de nouveau mot de passe : seule l'empreinte du jeton est gardée, le jeton lui-même part par courriel. */
#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_password_reset_token_hash', columns: ['token_hash'])]
class PasswordResetToken
{
    public const string VALIDITY = '+30 minutes';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $aId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $aUser;

    #[ORM\Column(length: 64)]
    private string $aTokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $aExpiresAt;

    public function __construct(User $pUser, string $pToken, \DateTimeImmutable $pNow)
    {
        $this->aUser = $pUser;
        $this->aTokenHash = self::hash($pToken);
        $this->aExpiresAt = $pNow->modify(self::VALIDITY);
    }

    public static function hash(string $pToken): string
    {
        return hash('sha256', $pToken);
    }

    public function getId(): ?int
    {
        return $this->aId;
    }

    public function getUser(): User
    {
        return $this->aUser;
    }

    public function getTokenHash(): string
    {
        return $this->aTokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->aExpiresAt;
    }

    public function isExpired(\DateTimeImmutable $pNow): bool
    {
        return $pNow >= $this->aExpiresAt;
    }
}
