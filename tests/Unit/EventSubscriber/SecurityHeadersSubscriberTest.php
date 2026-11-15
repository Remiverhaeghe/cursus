<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(SecurityHeadersSubscriber::class)]
final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testListensEarlyToRequestsAndToResponses(): void
    {
        self::assertSame([
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ], SecurityHeadersSubscriber::getSubscribedEvents());
    }

    public function testEachMainRequestGetsItsOwnNonce(): void
    {
        $vSubscriber = new SecurityHeadersSubscriber();
        $vFirst = new Request();
        $vSecond = new Request();
        $vSub = new Request();

        $vSubscriber->onKernelRequest($this->requestEvent($vFirst));
        $vSubscriber->onKernelRequest($this->requestEvent($vSecond));
        $vSubscriber->onKernelRequest($this->requestEvent($vSub, HttpKernelInterface::SUB_REQUEST));

        $vNonce = $vFirst->attributes->get(SecurityHeadersSubscriber::NONCE_ATTRIBUTE);
        self::assertIsString($vNonce);
        self::assertSame(24, \strlen($vNonce));
        self::assertNotSame($vNonce, $vSecond->attributes->get(SecurityHeadersSubscriber::NONCE_ATTRIBUTE));
        // Une page d'erreur rendue en sous-requête garde le nonce de la page principale
        self::assertSame($vSecond->attributes->get(SecurityHeadersSubscriber::NONCE_ATTRIBUTE), $vSub->attributes->get(SecurityHeadersSubscriber::NONCE_ATTRIBUTE));
    }

    public function testResponseCarriesThePolicyWithTheRequestNonce(): void
    {
        $vRequest = new Request();
        $vRequest->attributes->set(SecurityHeadersSubscriber::NONCE_ATTRIBUTE, 'abc123');
        $vResponse = new Response();

        (new SecurityHeadersSubscriber())->onKernelResponse($this->responseEvent($vRequest, $vResponse));

        self::assertSame(
            "default-src 'self'; script-src 'self' 'nonce-abc123'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
            $vResponse->headers->get('Content-Security-Policy'),
        );
        self::assertSame('nosniff', $vResponse->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $vResponse->headers->get('X-Frame-Options'));
        self::assertSame('same-origin', $vResponse->headers->get('Referrer-Policy'));
        self::assertSame('camera=(), microphone=(), geolocation=()', $vResponse->headers->get('Permissions-Policy'));
    }

    public function testPolicyWithoutNonceOnlyAllowsOwnScripts(): void
    {
        $vResponse = new Response();

        (new SecurityHeadersSubscriber())->onKernelResponse($this->responseEvent(new Request(), $vResponse));

        self::assertStringContainsString("script-src 'self';", (string) $vResponse->headers->get('Content-Security-Policy'));
    }

    public function testExistingHeadersAndSubRequestsAreLeftAlone(): void
    {
        $vResponse = new Response(headers: ['X-Frame-Options' => 'SAMEORIGIN']);
        $vSubResponse = new Response();
        $vSubscriber = new SecurityHeadersSubscriber();

        $vSubscriber->onKernelResponse($this->responseEvent(new Request(), $vResponse));
        $vSubscriber->onKernelResponse($this->responseEvent(new Request(), $vSubResponse, HttpKernelInterface::SUB_REQUEST));

        self::assertSame('SAMEORIGIN', $vResponse->headers->get('X-Frame-Options'));
        self::assertFalse($vSubResponse->headers->has('Content-Security-Policy'));
    }

    private function requestEvent(Request $pRequest, int $pType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $pRequest, $pType);
    }

    private function responseEvent(Request $pRequest, Response $pResponse, int $pType = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $pRequest, $pType, $pResponse);
    }
}
