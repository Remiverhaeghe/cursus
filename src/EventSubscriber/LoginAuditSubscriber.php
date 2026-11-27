<?php

namespace App\EventSubscriber;

use App\Audit\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/** Journal des connexions : identifiant du compte en cas de succès, adresse IP en cas d'échec, jamais de mot de passe. */
final class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $aLogger,
        private readonly AuditLog $aAudit,
        private readonly EntityManagerInterface $aEntityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $pEvent): void
    {
        $vUser = $pEvent->getUser();
        $this->aLogger->info('Connexion réussie', [
            'user_id' => $vUser instanceof User ? $vUser->getId() : null,
        ]);
        if ($vUser instanceof User) {
            $this->aAudit->record(AuditLog::LOGIN, $vUser, 'Connexion réussie');
            $this->aEntityManager->flush();
        }
    }

    public function onLoginFailure(LoginFailureEvent $pEvent): void
    {
        $this->aLogger->warning('Échec de connexion', [
            'ip' => $pEvent->getRequest()->getClientIp(),
            'reason' => $pEvent->getException()->getMessageKey(),
        ]);
    }
}
