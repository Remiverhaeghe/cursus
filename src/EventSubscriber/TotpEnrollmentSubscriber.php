<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Oblige l'école et les tuteurs à activer la double authentification avant d'aller plus loin. */
final class TotpEnrollmentSubscriber implements EventSubscriberInterface
{
    /** Pages accessibles avant l'activation : la page d'activation elle-même et la déconnexion. */
    private const array ALLOWED_ROUTES = ['app_totp_setup', 'app_logout'];

    public function __construct(
        private readonly Security $aSecurity,
        private readonly UrlGeneratorInterface $aUrlGenerator,
        #[Autowire('%app.totp_required%')]
        private readonly bool $aTotpRequired,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $pEvent): void
    {
        if (!$this->aTotpRequired || !$pEvent->isMainRequest()) {
            return;
        }
        if (\in_array($pEvent->getRequest()->attributes->get('_route'), self::ALLOWED_ROUTES, true)) {
            return;
        }
        $vUser = $this->aSecurity->getUser();
        if (!$vUser instanceof User || !$vUser->getRole()->requiresTotp() || $vUser->isTotpAuthenticationEnabled()) {
            return;
        }

        $pEvent->setResponse(new RedirectResponse($this->aUrlGenerator->generate('app_totp_setup')));
    }
}
