<?php

namespace App\Controller;

use App\Entity\User;
use App\Progress\ProgressProvider;
use App\Repository\ApprenticeshipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Tableau de bord de chaque profil. L'accès à l'espace est filtré par access_control,
 * et chaque requête ne charge que les contrats de l'utilisateur connecté.
 */
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ApprenticeshipRepository $aApprenticeships,
        private readonly ProgressProvider $aProgress,
    ) {
    }

    #[Route('/ecole', name: 'app_school_home', methods: ['GET'])]
    public function school(#[CurrentUser] User $pUser): Response
    {
        return $this->render('dashboard/school.html.twig', [
            'user' => $pUser,
            'cohorts' => $this->aProgress->byCohort($this->aApprenticeships->findForManager($pUser)),
        ]);
    }

    #[Route('/tuteur', name: 'app_tutor_home', methods: ['GET'])]
    public function tutor(#[CurrentUser] User $pUser): Response
    {
        return $this->render('dashboard/tutor.html.twig', [
            'user' => $pUser,
            'progresses' => $this->aProgress->forApprenticeships($this->aApprenticeships->findForTutor($pUser)),
        ]);
    }

    #[Route('/apprenti', name: 'app_apprentice_home', methods: ['GET'])]
    public function apprentice(#[CurrentUser] User $pUser): Response
    {
        return $this->render('dashboard/apprentice.html.twig', [
            'user' => $pUser,
            'progresses' => $this->aProgress->forApprenticeships($this->aApprenticeships->findForApprentice($pUser)),
        ]);
    }
}
