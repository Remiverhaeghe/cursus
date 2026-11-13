<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Espace d'accueil de chaque profil ; l'accès est filtré par access_control dans security.yaml. */
final class DashboardController extends AbstractController
{
    #[Route('/ecole', name: 'app_school_home', methods: ['GET'])]
    public function school(#[CurrentUser] User $pUser): Response
    {
        return $this->render('dashboard/school.html.twig', ['user' => $pUser]);
    }

    #[Route('/tuteur', name: 'app_tutor_home', methods: ['GET'])]
    public function tutor(#[CurrentUser] User $pUser): Response
    {
        return $this->render('dashboard/tutor.html.twig', ['user' => $pUser]);
    }

    #[Route('/apprenti', name: 'app_apprentice_home', methods: ['GET'])]
    public function apprentice(#[CurrentUser] User $pUser): Response
    {
        return $this->render('dashboard/apprentice.html.twig', ['user' => $pUser]);
    }
}
