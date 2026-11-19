<?php

namespace App\Controller;

use App\Calendar\ApprenticeCalendar;
use App\Entity\Apprenticeship;
use App\Security\Voter\ApprenticeshipVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Calendrier de l'alternance d'un contrat, consultable par tous ceux qui suivent ce contrat. */
final class ApprenticeCalendarController extends AbstractController
{
    #[Route('/suivi/{id}/calendrier', name: 'app_tracking_calendar', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ApprenticeshipVoter::VIEW, subject: 'pApprenticeship')]
    public function show(Apprenticeship $pApprenticeship, ClockInterface $pClock): Response
    {
        return $this->render('tracking/calendar.html.twig', [
            'apprenticeship' => $pApprenticeship,
            'calendar' => ApprenticeCalendar::build($pApprenticeship, $pClock->now()),
        ]);
    }
}
