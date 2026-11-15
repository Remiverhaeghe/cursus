<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages légales, publiques. Éditeur, hébergeur et contact viennent de la configuration :
 * rien n'est inventé, une valeur vide s'affiche « à compléter ».
 */
final class LegalController extends AbstractController
{
    /** @param array{publisher: string, host: string, contact: string} $aLegal */
    public function __construct(
        #[Autowire(['publisher' => '%env(LEGAL_PUBLISHER)%', 'host' => '%env(LEGAL_HOST)%', 'contact' => '%env(LEGAL_CONTACT)%'])]
        private readonly array $aLegal,
    ) {
    }

    #[Route('/mentions-legales', name: 'app_legal_notice', methods: ['GET'])]
    public function notice(): Response
    {
        return $this->render('legal/notice.html.twig', ['legal' => $this->aLegal]);
    }

    #[Route('/confidentialite', name: 'app_legal_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig', ['legal' => $this->aLegal]);
    }

    #[Route('/conditions-utilisation', name: 'app_legal_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig', ['legal' => $this->aLegal]);
    }
}
