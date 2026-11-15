<?php

namespace App\Controller;

use App\Account\AccountDeletion;
use App\Account\AccountExporter;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Mon compte : export de mes données et suppression de mon compte, ce que promet la politique de confidentialité. */
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountDeletion $aDeletion,
        private readonly ClockInterface $aClock,
    ) {
    }

    #[Route('/compte', name: 'app_account', methods: ['GET'])]
    public function show(#[CurrentUser] User $pUser): Response
    {
        return $this->render('account/show.html.twig', [
            'user' => $pUser,
            'blocker' => $this->aDeletion->blocker($pUser, $this->aClock->now()),
            'error' => null,
        ]);
    }

    #[Route('/compte/export', name: 'app_account_export', methods: ['GET'])]
    public function export(#[CurrentUser] User $pUser, AccountExporter $pExporter): JsonResponse
    {
        $rResponse = new JsonResponse($pExporter->export($pUser, $this->aClock->now()));
        $rResponse->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $rResponse->headers->set('Content-Disposition', $rResponse->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'cursus-mes-donnees.json'));

        return $rResponse;
    }

    #[Route('/compte/supprimer', name: 'app_account_delete', methods: ['POST'])]
    public function delete(
        #[CurrentUser] User $pUser,
        Request $pRequest,
        UserPasswordHasherInterface $pPasswordHasher,
        Security $pSecurity,
    ): Response {
        $vError = null;
        $vBlocker = $this->aDeletion->blocker($pUser, $this->aClock->now());
        if (!$this->isCsrfTokenValid('account_delete', (string) $pRequest->request->get('_token'))) {
            $vError = 'Le formulaire a expiré, recommence.';
        } elseif (null !== $vBlocker) {
            $vError = $vBlocker;
        } elseif (!$pPasswordHasher->isPasswordValid($pUser, (string) $pRequest->request->get('password'))) {
            $vError = 'Mot de passe incorrect.';
        }
        if (null !== $vError) {
            return $this->render('account/show.html.twig', [
                'user' => $pUser,
                'blocker' => $vBlocker,
                'error' => $vError,
            ], new Response(status: 422));
        }

        $this->aDeletion->delete($pUser);
        $pSecurity->logout(false);
        $this->addFlash('success', 'Ton compte a été supprimé.');

        return $this->redirectToRoute('app_login');
    }
}
