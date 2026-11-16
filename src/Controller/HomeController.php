<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Envoie chaque profil vers son espace après la connexion. */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $pUser): RedirectResponse
    {
        return $this->redirectToRoute($pUser->getRole()->homeRoute());
    }
}
