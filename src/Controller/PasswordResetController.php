<?php

namespace App\Controller;

use App\Account\PasswordReset;
use App\Form\Data\NewPasswordData;
use App\Form\Data\PasswordResetRequestData;
use App\Form\NewPasswordType;
use App\Form\PasswordResetRequestType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Mot de passe oublié : demande du lien, puis choix du nouveau mot de passe. */
final class PasswordResetController extends AbstractController
{
    public const string SENT_MESSAGE = 'Si un compte existe pour cette adresse, un lien valable 30 minutes vient d\'y être envoyé.';

    public function __construct(private readonly PasswordReset $aPasswordReset)
    {
    }

    #[Route('/mot-de-passe-oublie', name: 'app_password_forgotten', methods: ['GET', 'POST'])]
    public function request(
        Request $pRequest,
        #[Autowire(service: 'limiter.password_reset')]
        RateLimiterFactoryInterface $pLimiter,
    ): Response {
        $vData = new PasswordResetRequestData();
        $vForm = $this->createForm(PasswordResetRequestType::class, $vData);
        $vForm->handleRequest($pRequest);
        if ($vForm->isSubmitted() && $vForm->isValid()) {
            // Même limite pour une adresse connue ou inconnue : le nombre de refus ne trahit rien
            $vLimit = $pLimiter->create(mb_strtolower($vData->email).'|'.$pRequest->getClientIp())->consume();
            if (!$vLimit->isAccepted()) {
                return $this->render('security/password_forgotten.html.twig', [
                    'form' => $vForm,
                    'error' => 'Trop de demandes : réessaie dans quelques minutes.',
                ], new Response(status: 429));
            }
            $this->aPasswordReset->request($vData->email);
            $this->addFlash('info', self::SENT_MESSAGE);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/password_forgotten.html.twig', [
            'form' => $vForm,
            'error' => null,
        ], new Response(status: $vForm->isSubmitted() ? 422 : 200));
    }

    #[Route('/mot-de-passe-oublie/{token}', name: 'app_password_reset', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function reset(string $token, Request $pRequest): Response
    {
        if (null === $this->aPasswordReset->userForToken($token)) {
            $this->addFlash('error', 'Ce lien n\'est plus valable. Fais une nouvelle demande.');

            return $this->redirectToRoute('app_password_forgotten');
        }

        $vData = new NewPasswordData();
        $vForm = $this->createForm(NewPasswordType::class, $vData);
        $vForm->handleRequest($pRequest);
        if ($vForm->isSubmitted() && $vForm->isValid()) {
            $this->aPasswordReset->reset($token, $vData->password);
            $this->addFlash('success', 'Mot de passe changé. Tu peux te connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/password_reset.html.twig', [
            'form' => $vForm,
        ], new Response(status: $vForm->isSubmitted() ? 422 : 200));
    }
}
