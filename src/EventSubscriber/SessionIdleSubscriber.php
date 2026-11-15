<?php

namespace App\EventSubscriber;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ferme une session restée inactive trop longtemps (30 minutes par défaut).
 * Passe avant le pare-feu : une session vidée ne contient plus de jeton, la page demande alors une connexion.
 */
final class SessionIdleSubscriber implements EventSubscriberInterface
{
    public const string LAST_ACTIVITY = 'cursus_last_activity';

    public function __construct(
        private readonly ClockInterface $aClock,
        #[Autowire('%app.session_idle_timeout%')]
        private readonly int $aIdleTimeout,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Le pare-feu écoute en priorité 8 : passer juste avant lui
        return [KernelEvents::REQUEST => ['onKernelRequest', 9]];
    }

    public function onKernelRequest(RequestEvent $pEvent): void
    {
        $vRequest = $pEvent->getRequest();
        if (!$pEvent->isMainRequest() || !$vRequest->hasPreviousSession()) {
            return;
        }
        $vSession = $vRequest->getSession();
        $vNow = $this->aClock->now()->getTimestamp();
        $vLastActivity = $vSession->get(self::LAST_ACTIVITY);
        if (\is_int($vLastActivity) && $vNow - $vLastActivity > $this->aIdleTimeout) {
            $vSession->invalidate();
            if ($vSession instanceof FlashBagAwareSessionInterface) {
                $vSession->getFlashBag()->add('info', 'Ta session a expiré après une période d\'inactivité. Reconnecte-toi.');
            }
        }
        $vSession->set(self::LAST_ACTIVITY, $vNow);
    }
}
