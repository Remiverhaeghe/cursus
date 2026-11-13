<?php

namespace App\Controller;

use App\Entity\Apprenticeship;
use App\Entity\User;
use App\Form\Data\EvaluationData;
use App\Form\EvaluationType;
use App\Repository\EvaluationRepository;
use App\Security\Voter\ApprenticeshipVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Suivi d'un contrat : historique des évaluations, et formulaire pour l'école et le tuteur. */
final class TrackingController extends AbstractController
{
    #[Route('/suivi/{id}', name: 'app_tracking_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ApprenticeshipVoter::VIEW, subject: 'pApprenticeship')]
    public function show(
        Apprenticeship $pApprenticeship,
        #[CurrentUser] User $pUser,
        Request $pRequest,
        EvaluationRepository $pEvaluations,
        EntityManagerInterface $pEntityManager,
        ClockInterface $pClock,
        LoggerInterface $pLogger,
    ): Response {
        $vForm = null;
        if ($this->isGranted(ApprenticeshipVoter::EVALUATE, $pApprenticeship)) {
            $vData = new EvaluationData();
            $vForm = $this->createForm(EvaluationType::class, $vData, ['formation' => $pApprenticeship->getFormation()]);
            $vForm->handleRequest($pRequest);
            if ($vForm->isSubmitted() && $vForm->isValid()) {
                $vEvaluation = $vData->toEvaluation($pApprenticeship, $pUser, $pClock->now());
                $pEntityManager->persist($vEvaluation);
                $pEntityManager->flush();
                $pLogger->info('Évaluation enregistrée', [
                    'evaluation_id' => $vEvaluation->getId(),
                    'apprenticeship_id' => $pApprenticeship->getId(),
                    'evaluator_id' => $pUser->getId(),
                ]);
                $this->addFlash('success', 'Évaluation enregistrée.');

                return $this->redirectToRoute('app_tracking_show', ['id' => $pApprenticeship->getId()]);
            }
        } elseif ($pRequest->isMethod('POST')) {
            throw $this->createAccessDeniedException('Seuls l\'école et le tuteur évaluent.');
        }

        return $this->render('tracking/show.html.twig', [
            'apprenticeship' => $pApprenticeship,
            'evaluations' => array_reverse($pEvaluations->findForApprenticeship($pApprenticeship)),
            'form' => $vForm,
        ], new Response(status: null !== $vForm && $vForm->isSubmitted() ? 422 : 200));
    }
}
