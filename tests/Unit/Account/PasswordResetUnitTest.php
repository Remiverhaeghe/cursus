<?php

namespace App\Tests\Unit\Account;

use App\Account\PasswordReset;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Règles du mot de passe oublié, sans base ni envoi réel. */
#[CoversClass(PasswordReset::class)]
final class PasswordResetUnitTest extends TestCase
{
    private User $aUser;
    private MockClock $aClock;

    protected function setUp(): void
    {
        $this->aUser = new User('lea@cursus.test', UserRole::Apprentice, 'Léa', 'Martin');
        (new \ReflectionProperty(User::class, 'aId'))->setValue($this->aUser, 7);
        $this->aClock = new MockClock('2026-11-15 10:00:00');
    }

    public function testUnknownAddressSendsNothing(): void
    {
        $vMailer = $this->createMock(MailerInterface::class);
        $vMailer->expects(self::never())->method('send');
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('info')->with('Demande de mot de passe pour une adresse inconnue');

        $this->service(pUser: null, pMailer: $vMailer, pLogger: $vLogger)->request('inconnu@cursus.test');
    }

    public function testKnownAddressGetsAFreshTokenByEmail(): void
    {
        $vPersisted = [];
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->method('persist')->willReturnCallback(static function (object $pEntity) use (&$vPersisted): void {
            $vPersisted[] = $pEntity;
        });
        $vEntityManager->expects(self::once())->method('flush');
        $vTokens = $this->createMock(PasswordResetTokenRepository::class);
        $vTokens->expects(self::once())->method('removeForUser')->with($this->aUser);
        $vSent = null;
        $vMailer = $this->createMock(MailerInterface::class);
        $vMailer->expects(self::once())->method('send')->willReturnCallback(static function (TemplatedEmail $pEmail) use (&$vSent): void {
            $vSent = $pEmail;
        });
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('info')->with('Lien de nouveau mot de passe envoyé', ['user_id' => 7]);

        $this->service($this->aUser, $vTokens, $vEntityManager, $vMailer, $vLogger)->request('lea@cursus.test');

        self::assertInstanceOf(TemplatedEmail::class, $vSent);
        self::assertSame('lea@cursus.test', $vSent->getTo()[0]->getAddress());
        self::assertSame('no-reply@cursus.test', $vSent->getFrom()[0]->getAddress());
        self::assertSame('Cursus : choisir un nouveau mot de passe', $vSent->getSubject());
        self::assertSame('email/password_reset.html.twig', $vSent->getHtmlTemplate());
        self::assertSame('email/password_reset.txt.twig', $vSent->getTextTemplate());
        $vContext = $vSent->getContext();
        self::assertSame('Léa', $vContext['user_first_name']);
        self::assertIsString($vContext['token']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $vContext['token']);
        self::assertCount(1, $vPersisted);
        self::assertInstanceOf(PasswordResetToken::class, $vPersisted[0]);
        self::assertSame(PasswordResetToken::hash($vContext['token']), $vPersisted[0]->getTokenHash());
        self::assertSame('2026-11-15 10:30:00', $vPersisted[0]->getExpiresAt()->format('Y-m-d H:i:s'));
    }

    public function testTokenIsValidUntilItsExpiry(): void
    {
        $vRequest = new PasswordResetToken($this->aUser, 'jeton', new \DateTimeImmutable('2026-11-15 09:30:01'));
        $vTokens = $this->createStub(PasswordResetTokenRepository::class);
        $vTokens->method('findOneByToken')->willReturnMap([['jeton', $vRequest], ['autre', null]]);
        $vService = $this->service($this->aUser, $vTokens);

        self::assertSame($this->aUser, $vService->userForToken('jeton'));
        self::assertNull($vService->userForToken('autre'));
        $this->aClock->modify('+1 second');
        self::assertNull($vService->userForToken('jeton'));
    }

    public function testResetHashesThePasswordAndConsumesTheToken(): void
    {
        $vRequest = new PasswordResetToken($this->aUser, 'jeton', $this->aClock->now());
        $vTokens = $this->createMock(PasswordResetTokenRepository::class);
        $vTokens->method('findOneByToken')->willReturn($vRequest);
        $vTokens->expects(self::once())->method('removeForUser')->with($this->aUser);
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::once())->method('flush');
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('info')->with('Mot de passe changé par lien', ['user_id' => 7]);

        self::assertTrue($this->service($this->aUser, $vTokens, $vEntityManager, pLogger: $vLogger)->reset('jeton', 'nouveau-mot-de-passe'));
        self::assertSame('empreinte de nouveau-mot-de-passe', $this->aUser->getPassword());
    }

    public function testResetWithAnUnknownTokenChangesNothing(): void
    {
        $vTokens = $this->createStub(PasswordResetTokenRepository::class);
        $vTokens->method('findOneByToken')->willReturn(null);
        $vEntityManager = $this->createMock(EntityManagerInterface::class);
        $vEntityManager->expects(self::never())->method('flush');

        self::assertFalse($this->service($this->aUser, $vTokens, $vEntityManager)->reset('inconnu', 'nouveau-mot-de-passe'));
        self::assertSame('', $this->aUser->getPassword());
    }

    private function service(
        ?User $pUser,
        ?PasswordResetTokenRepository $pTokens = null,
        ?EntityManagerInterface $pEntityManager = null,
        ?MailerInterface $pMailer = null,
        ?LoggerInterface $pLogger = null,
    ): PasswordReset {
        $vUsers = $this->createStub(UserRepository::class);
        $vUsers->method('findOneByEmail')->willReturn($pUser);
        $vHasher = $this->createStub(UserPasswordHasherInterface::class);
        $vHasher->method('hashPassword')->willReturnCallback(static fn (User $pAccount, string $pPlain): string => 'empreinte de '.$pPlain);

        return new PasswordReset(
            $vUsers,
            $pTokens ?? $this->createStub(PasswordResetTokenRepository::class),
            $pEntityManager ?? $this->createStub(EntityManagerInterface::class),
            $vHasher,
            $pMailer ?? $this->createStub(MailerInterface::class),
            $this->aClock,
            $pLogger ?? $this->createStub(LoggerInterface::class),
            'no-reply@cursus.test',
        );
    }
}
