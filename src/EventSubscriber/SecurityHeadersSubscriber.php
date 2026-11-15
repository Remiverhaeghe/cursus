<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité sur chaque réponse : CSP stricte (scripts autorisés par un nonce propre à la requête),
 * pas d'affichage dans un cadre, pas de devinette du type de contenu.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public const string NONCE_ATTRIBUTE = 'csp_nonce';

    /** @var array<string, string> */
    private const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    /** Nonce de la requête principale en cours, repris par ses sous-requêtes (pages d'erreur, fragments). */
    private string $aNonce = '';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $pEvent): void
    {
        if ($pEvent->isMainRequest()) {
            $this->aNonce = base64_encode(random_bytes(16));
        }
        $pEvent->getRequest()->attributes->set(self::NONCE_ATTRIBUTE, $this->aNonce);
    }

    public function onKernelResponse(ResponseEvent $pEvent): void
    {
        if (!$pEvent->isMainRequest()) {
            return;
        }
        $vHeaders = $pEvent->getResponse()->headers;
        $vNonce = $pEvent->getRequest()->attributes->get(self::NONCE_ATTRIBUTE);
        $vHeaders->set('Content-Security-Policy', self::policy(\is_string($vNonce) ? $vNonce : null), false);
        foreach (self::HEADERS as $vName => $vValue) {
            $vHeaders->set($vName, $vValue, false);
        }
    }

    public static function policy(?string $pNonce): string
    {
        $vScripts = null === $pNonce ? "'self'" : \sprintf("'self' 'nonce-%s'", $pNonce);

        $rPolicy = implode('; ', [
            "default-src 'self'",
            'script-src '.$vScripts,
            "style-src 'self'",
            "img-src 'self' data:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        return $rPolicy;
    }
}
