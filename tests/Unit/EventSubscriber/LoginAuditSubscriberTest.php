<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Audit\AuditLog;
use App\Entity\User;
use App\Enum\UserRole;
use App\EventSubscriber\LoginAuditSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[CoversClass(LoginAuditSubscriber::class)]
final class LoginAuditSubscriberTest extends TestCase
{
    public function testSubscribesToLoginEvents(): void
    {
        self::assertSame([
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ], LoginAuditSubscriber::getSubscribedEvents());
    }

    public function testSuccessLogsTheAccountIdOnly(): void
    {
        $vUser = new User('lea@cursus.test', UserRole::Apprentice, 'Léa', 'Martin');
        (new \ReflectionProperty(User::class, 'aId'))->setValue($vUser, 42);
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('info')->with('Connexion réussie', ['user_id' => 42]);

        (new LoginAuditSubscriber($vLogger, $this->createStub(AuditLog::class), $this->createStub(EntityManagerInterface::class)))->onLoginSuccess($this->successEvent($vUser));
    }

    public function testSuccessWithAnotherUserTypeLogsNoId(): void
    {
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('info')->with('Connexion réussie', ['user_id' => null]);

        (new LoginAuditSubscriber($vLogger, $this->createStub(AuditLog::class), $this->createStub(EntityManagerInterface::class)))->onLoginSuccess($this->successEvent(new InMemoryUser('lea', null)));
    }

    public function testFailureLogsIpAndReason(): void
    {
        $vRequest = Request::create('/connexion', 'POST', server: ['REMOTE_ADDR' => '192.0.2.10']);
        $vEvent = new LoginFailureEvent(new BadCredentialsException(), $this->createStub(AuthenticatorInterface::class), $vRequest, null, 'main');
        $vLogger = $this->createMock(LoggerInterface::class);
        $vLogger->expects(self::once())->method('warning')->with('Échec de connexion', ['ip' => '192.0.2.10', 'reason' => 'Invalid credentials.']);

        (new LoginAuditSubscriber($vLogger, $this->createStub(AuditLog::class), $this->createStub(EntityManagerInterface::class)))->onLoginFailure($vEvent);
    }

    private function successEvent(UserInterface $pUser): LoginSuccessEvent
    {
        $vPassport = new SelfValidatingPassport(new UserBadge('lea', static fn (): UserInterface => $pUser));

        $rEvent = new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            $vPassport,
            $this->createStub(TokenInterface::class),
            new Request(),
            null,
            'main',
        );

        return $rEvent;
    }
}
