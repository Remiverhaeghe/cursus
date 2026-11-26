<?php

namespace App\Controller;

use App\Audit\AuditLog;
use App\Entity\User;
use App\Security\BackupCodes;
use App\Security\SecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/** Connexion, déconnexion et activation de la double authentification. */
final class SecurityController extends AbstractController
{
    public const string SESSION_TOTP_SECRET = 'cursus_totp_pending_secret';
    public const string SESSION_BACKUP_CODES = 'cursus_backup_codes_once';

    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $pAuthenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $pAuthenticationUtils->getLastUsername(),
            'error' => self::loginErrorMessage($pAuthenticationUtils->getLastAuthenticationError()),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('La déconnexion est interceptée par le pare-feu.');
    }

    #[Route('/double-authentification', name: 'app_totp_setup', methods: ['GET', 'POST'])]
    public function totpSetup(
        #[CurrentUser] User $pUser,
        Request $pRequest,
        TotpAuthenticatorInterface $pTotpAuthenticator,
        EntityManagerInterface $pEntityManager,
        SecretCipher $pCipher,
        BackupCodes $pBackupCodes,
        AuditLog $pAudit,
    ): Response {
        if ($pUser->isTotpAuthenticationEnabled()) {
            $this->addFlash('info', 'La double authentification est déjà active sur ton compte.');

            return $this->redirectToRoute('app_home');
        }

        // Le secret attend en session tant que le premier code n'est pas confirmé
        $vSession = $pRequest->getSession();
        $vSecret = $vSession->get(self::SESSION_TOTP_SECRET);
        if (!\is_string($vSecret)) {
            $vSecret = $pTotpAuthenticator->generateSecret();
            $vSession->set(self::SESSION_TOTP_SECRET, $vSecret);
        }
        // Copie non suivie par Doctrine : elle porte le secret pour le code QR et la vérification du code
        $vCandidate = clone $pUser;
        $vCandidate->enableTotp($vSecret);

        $vError = null;
        if ($pRequest->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('totp_setup', $pRequest->request->getString('_token'))) {
                $vError = 'Le formulaire a expiré, recommence.';
            } elseif ($pTotpAuthenticator->checkCode($vCandidate, $pRequest->request->getString('code'))) {
                $vCodes = $pBackupCodes->generate();
                $pUser->enableTotp($pCipher->encrypt($vSecret));
                $pUser->replaceBackupCodes($vCodes);
                $pAudit->record(AuditLog::TOTP_ENABLED, $pUser, 'Double authentification activée');
                $pEntityManager->flush();
                $vSession->remove(self::SESSION_TOTP_SECRET);
                $vSession->set(self::SESSION_BACKUP_CODES, $vCodes);
                $this->addFlash('success', 'Double authentification activée.');

                return $this->redirectToRoute('app_backup_codes');
            } else {
                $vError = 'Code incorrect, vérifie l\'heure de ton téléphone et réessaie.';
            }
        }

        $vQrCode = (new SvgWriter())->write(new QrCode($pTotpAuthenticator->getQRContent($vCandidate)));

        return $this->render('security/totp_setup.html.twig', [
            'qr_code' => $vQrCode->getDataUri(),
            'secret' => $vSecret,
            'error' => $vError,
        ], new Response(status: null === $vError ? 200 : 422));
    }

    /** Affiche une seule fois les codes de secours qui viennent d'être créés. */
    #[Route('/compte/codes-de-secours', name: 'app_backup_codes', methods: ['GET'])]
    public function backupCodes(Request $pRequest): Response
    {
        $vSession = $pRequest->getSession();
        $vCodes = $vSession->get(self::SESSION_BACKUP_CODES);
        $vSession->remove(self::SESSION_BACKUP_CODES);
        if (!\is_array($vCodes) || [] === $vCodes) {
            return $this->redirectToRoute('app_account');
        }

        return $this->render('security/backup_codes.html.twig', ['codes' => $vCodes]);
    }

    /** Remplace tous les codes de secours : les anciens cessent de marcher. */
    #[Route('/compte/codes-de-secours/regenerer', name: 'app_backup_codes_regenerate', methods: ['POST'])]
    public function regenerateBackupCodes(
        #[CurrentUser] User $pUser,
        Request $pRequest,
        EntityManagerInterface $pEntityManager,
        BackupCodes $pBackupCodes,
        AuditLog $pAudit,
    ): Response {
        if (!$pUser->isTotpAuthenticationEnabled()) {
            $this->addFlash('error', 'Active d\'abord la double authentification.');

            return $this->redirectToRoute('app_account');
        }
        if (!$this->isCsrfTokenValid('backup_codes', $pRequest->request->getString('_token'))) {
            $this->addFlash('error', 'Le formulaire a expiré, recommence.');

            return $this->redirectToRoute('app_account');
        }
        $vCodes = $pBackupCodes->generate();
        $pUser->replaceBackupCodes($vCodes);
        $pAudit->record(AuditLog::BACKUP_CODES, $pUser, 'Codes de secours régénérés');
        $pEntityManager->flush();
        $pRequest->getSession()->set(self::SESSION_BACKUP_CODES, $vCodes);

        return $this->redirectToRoute('app_backup_codes');
    }

    /** Message en français pour l'erreur de connexion, sans dire si le compte existe. */
    public static function loginErrorMessage(?AuthenticationException $pError): ?string
    {
        if (null === $pError) {
            return null;
        }
        if (str_starts_with($pError->getMessageKey(), 'Too many failed login attempts')) {
            return 'Trop de tentatives : réessaie dans quelques minutes.';
        }
        if ('Invalid CSRF token.' === $pError->getMessageKey()) {
            return 'Le formulaire a expiré, recommence.';
        }

        return 'Identifiants invalides.';
    }
}
