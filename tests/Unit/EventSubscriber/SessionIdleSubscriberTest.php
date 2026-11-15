<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\SessionIdleSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(SessionIdleSubscriber::class)]
final class SessionIdleSubscriberTest extends TestCase
{
    private const int TIMEOUT = 1800;

    public function testRunsJustBeforeTheFirewall(): void
    {
        self::assertSame([KernelEvents::REQUEST => ['onKernelRequest', 9]], SessionIdleSubscriber::getSubscribedEvents());
    }

    public function testFirstRequestRecordsTheActivity(): void
    {
        $vClock = new MockClock('2026-11-15 10:00:00');
        [$vRequest, $vSession] = $this->requestWithSession();

        $this->subscriber($vClock)->onKernelRequest($this->event($vRequest));

        self::assertSame($vClock->now()->getTimestamp(), $vSession->get(SessionIdleSubscriber::LAST_ACTIVITY));
    }

    public function testActivityAtTheLimitKeepsTheSession(): void
    {
        $vClock = new MockClock('2026-11-15 10:00:00');
        [$vRequest, $vSession] = $this->requestWithSession();
        $vSession->set('_security_main', 'jeton');
        $vSession->set(SessionIdleSubscriber::LAST_ACTIVITY, $vClock->now()->getTimestamp() - self::TIMEOUT);

        $this->subscriber($vClock)->onKernelRequest($this->event($vRequest));

        self::assertSame('jeton', $vSession->get('_security_main'));
        self::assertSame($vClock->now()->getTimestamp(), $vSession->get(SessionIdleSubscriber::LAST_ACTIVITY));
        self::assertSame([], $vSession->getFlashBag()->peekAll());
    }

    public function testIdleSessionIsEmptiedAndExplained(): void
    {
        $vClock = new MockClock('2026-11-15 10:00:00');
        [$vRequest, $vSession] = $this->requestWithSession();
        $vSession->set('_security_main', 'jeton');
        $vSession->set(SessionIdleSubscriber::LAST_ACTIVITY, $vClock->now()->getTimestamp() - self::TIMEOUT - 1);

        $this->subscriber($vClock)->onKernelRequest($this->event($vRequest));

        self::assertNull($vSession->get('_security_main'));
        self::assertSame($vClock->now()->getTimestamp(), $vSession->get(SessionIdleSubscriber::LAST_ACTIVITY));
        self::assertSame(['Ta session a expiré après une période d\'inactivité. Reconnecte-toi.'], $vSession->getFlashBag()->peek('info'));
    }

    public function testRequestsWithoutSessionOrSubRequestsAreIgnored(): void
    {
        $vClock = new MockClock('2026-11-15 10:00:00');
        $vWithoutCookie = new Request();
        $vWithoutCookie->setSession(new Session(new MockArraySessionStorage()));
        [$vSubRequest, $vSubSession] = $this->requestWithSession();

        $this->subscriber($vClock)->onKernelRequest($this->event($vWithoutCookie));
        $this->subscriber($vClock)->onKernelRequest($this->event($vSubRequest, HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($vWithoutCookie->getSession()->has(SessionIdleSubscriber::LAST_ACTIVITY));
        self::assertFalse($vSubSession->has(SessionIdleSubscriber::LAST_ACTIVITY));
    }

    /** @return array{Request, Session} */
    private function requestWithSession(): array
    {
        $vSession = new Session(new MockArraySessionStorage());
        $vRequest = new Request(cookies: [$vSession->getName() => 'identifiant']);
        $vRequest->setSession($vSession);

        return [$vRequest, $vSession];
    }

    private function subscriber(MockClock $pClock): SessionIdleSubscriber
    {
        return new SessionIdleSubscriber($pClock, self::TIMEOUT);
    }

    private function event(Request $pRequest, int $pType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $pRequest, $pType);
    }
}
