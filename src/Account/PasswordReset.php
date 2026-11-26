<?php

namespace App\Account;

use App\Audit\AuditLog;
use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Mot de passe oublié : jeton aléatoire à usage unique, valable 30 minutes, stocké en empreinte.
 * La demande ne dit jamais si l'adresse existe.
 */
final class PasswordReset
{
    public function __construct(
        private readonly UserRepository $aUsers,
        private readonly PasswordResetTokenRepository $aTokens,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly UserPasswordHasherInterface $aPasswordHasher,
        private readonly MailerInterface $aMailer,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
        private readonly AuditLog $aAudit,
        #[Autowire('%app.mailer_from%')]
        private readonly string $aMailerFrom,
    ) {
    }

    /** Envoie un lien si le compte existe ; ne fait rien sinon, sans le laisser voir. */
    public function request(string $pEmail): void
    {
        $vUser = $this->aUsers->findOneByEmail($pEmail);
        if (null === $vUser) {
            $this->aLogger->info('Demande de mot de passe pour une adresse inconnue');

            return;
        }

        $vToken = bin2hex(random_bytes(32));
        $this->aTokens->removeForUser($vUser);
        $this->aEntityManager->persist(new PasswordResetToken($vUser, $vToken, $this->aClock->now()));
        $this->aEntityManager->flush();

        $this->aMailer->send((new TemplatedEmail())
            ->from($this->aMailerFrom)
            ->to($vUser->getEmail())
            ->subject('Cursus : choisir un nouveau mot de passe')
            ->htmlTemplate('email/password_reset.html.twig')
            ->textTemplate('email/password_reset.txt.twig')
            ->context(['user_first_name' => $vUser->getFirstName(), 'token' => $vToken]));
        $this->aLogger->info('Lien de nouveau mot de passe envoyé', ['user_id' => $vUser->getId()]);
    }

    /** Compte concerné par un jeton encore valable, ou null. */
    public function userForToken(string $pToken): ?User
    {
        $vRequest = $this->aTokens->findOneByToken($pToken);
        if (null === $vRequest || $vRequest->isExpired($this->aClock->now())) {
            return null;
        }

        return $vRequest->getUser();
    }

    /** Change le mot de passe et consomme le jeton ; les autres sessions du compte tombent avec l'ancienne empreinte. */
    public function reset(string $pToken, string $pPlainPassword): bool
    {
        $vUser = $this->userForToken($pToken);
        if (null === $vUser) {
            return false;
        }
        $vUser->setPassword($this->aPasswordHasher->hashPassword($vUser, $pPlainPassword));
        $this->aTokens->removeForUser($vUser);
        $this->aAudit->record(AuditLog::PASSWORD_RESET, $vUser, 'Mot de passe changé par lien');
        $this->aEntityManager->flush();
        $this->aLogger->info('Mot de passe changé par lien', ['user_id' => $vUser->getId()]);

        return true;
    }
}
