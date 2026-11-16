<?php

namespace App\Controller;

use App\Entity\Apprenticeship;
use App\Entity\User;
use App\Evaluation\EvaluationRecorder;
use App\Form\Data\EvaluationData;
use App\Form\EvaluationType;
use App\Progress\ProgressCalculator;
use App\Repository\EvaluationRepository;
use App\Security\Voter\ApprenticeshipVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Suivi d'un contrat : progression, historique des évaluations, et formulaire pour l'école et le tuteur. */
final class TrackingController extends AbstractController
{
    #[Route('/suivi/{id}', name: 'app_tracking_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ApprenticeshipVoter::VIEW, subject: 'pApprenticeship')]
    public function show(
        Apprenticeship $pApprenticeship,
        #[CurrentUser] User $pUser,
        Request $pRequest,
        EvaluationRepository $pEvaluations,
        ProgressCalculator $pCalculator,
        EvaluationRecorder $pRecorder,
    ): Response {
        $vForm = null;
        if ($this->isGranted(ApprenticeshipVoter::EVALUATE, $pApprenticeship)) {
            $vData = new EvaluationData();
            $vForm = $this->createForm(EvaluationType::class, $vData, ['formation' => $pApprenticeship->getFormation()]);
            $vForm->handleRequest($pRequest);
            if ($vForm->isSubmitted() && $vForm->isValid()) {
                $pRecorder->record($vData, $pApprenticeship, $pUser);
                $this->addFlash('success', 'Évaluation enregistrée.');

                return $this->redirectToRoute('app_tracking_show', ['id' => $pApprenticeship->getId()]);
            }
        } elseif ($pRequest->isMethod('POST')) {
            throw $this->createAccessDeniedException('Seuls l\'école et le tuteur évaluent.');
        }

        $vEvaluations = $pEvaluations->findForApprenticeship($pApprenticeship);

        return $this->render('tracking/show.html.twig', [
            'apprenticeship' => $pApprenticeship,
            'progress' => $pCalculator->forApprenticeship($pApprenticeship, $vEvaluations),
            'evaluations' => array_reverse($vEvaluations),
            'form' => $vForm,
        ], new Response(status: null !== $vForm && $vForm->isSubmitted() ? 422 : 200));
    }
}
