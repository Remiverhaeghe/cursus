<?php

namespace App\Controller\School;

use App\Entity\CalendarPeriod;
use App\Entity\Cohort;
use App\Entity\PlannedAssessment;
use App\Form\CalendarPeriodType;
use App\Form\Data\CalendarPeriodData;
use App\Form\Data\PlannedAssessmentData;
use App\Form\PlannedAssessmentType;
use App\Security\Voter\FormationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Calendrier d'une promotion : l'école y fixe le rythme école et entreprise et les évaluations prévues. */
final class CalendarController extends AbstractController
{
    private const string DELETE_TOKEN = 'calendar_delete';

    public function __construct(private readonly EntityManagerInterface $aEntityManager)
    {
    }

    #[Route('/ecole/promotions/{id}/calendrier', name: 'app_school_calendar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pCohort')]
    public function edit(Cohort $pCohort, Request $pRequest): Response
    {
        $vPeriod = new CalendarPeriodData();
        $vPeriodForm = $this->createForm(CalendarPeriodType::class, $vPeriod);
        $vPeriodForm->handleRequest($pRequest);
        if ($vPeriodForm->isSubmitted() && $vPeriodForm->isValid()) {
            if ($vPeriod->overlapsAPeriodOf($pCohort)) {
                $vPeriodForm->addError(new FormError('Cette période chevauche une période déjà prévue.'));
            } else {
                return $this->save($vPeriod->toPeriod($pCohort), $pCohort, 'Période ajoutée.');
            }
        }

        $vAssessment = new PlannedAssessmentData();
        $vAssessmentForm = $this->createForm(PlannedAssessmentType::class, $vAssessment);
        $vAssessmentForm->handleRequest($pRequest);
        if ($vAssessmentForm->isSubmitted() && $vAssessmentForm->isValid()) {
            return $this->save($vAssessment->toAssessment($pCohort), $pCohort, 'Évaluation prévue ajoutée.');
        }

        return $this->render('school/calendar.html.twig', [
            'cohort' => $pCohort,
            'period_form' => $vPeriodForm,
            'assessment_form' => $vAssessmentForm,
            'delete_token' => self::DELETE_TOKEN,
        ], new Response(status: $vPeriodForm->isSubmitted() || $vAssessmentForm->isSubmitted() ? 422 : 200));
    }

    #[Route('/ecole/calendrier/periodes/{id}/suppression', name: 'app_school_period_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pPeriod')]
    public function deletePeriod(CalendarPeriod $pPeriod, Request $pRequest): Response
    {
        $vCohort = $pPeriod->getCohort();
        $this->checkDeleteToken($pRequest);
        $vCohort->detachPeriod($pPeriod);

        return $this->remove($pPeriod, $vCohort, 'Période supprimée.');
    }

    #[Route('/ecole/calendrier/evaluations/{id}/suppression', name: 'app_school_assessment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pAssessment')]
    public function deleteAssessment(PlannedAssessment $pAssessment, Request $pRequest): Response
    {
        $vCohort = $pAssessment->getCohort();
        $this->checkDeleteToken($pRequest);
        $vCohort->detachAssessment($pAssessment);

        return $this->remove($pAssessment, $vCohort, 'Évaluation prévue supprimée.');
    }

    private function checkDeleteToken(Request $pRequest): void
    {
        if (!$this->isCsrfTokenValid(self::DELETE_TOKEN, $pRequest->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de suppression invalide.');
        }
    }

    private function save(object $pEntity, Cohort $pCohort, string $pMessage): Response
    {
        $this->aEntityManager->persist($pEntity);
        $this->aEntityManager->flush();
        $this->addFlash('success', $pMessage);

        return $this->redirectToRoute('app_school_calendar', ['id' => $pCohort->getId()]);
    }

    private function remove(object $pEntity, Cohort $pCohort, string $pMessage): Response
    {
        $this->aEntityManager->remove($pEntity);
        $this->aEntityManager->flush();
        $this->addFlash('success', $pMessage);

        return $this->redirectToRoute('app_school_calendar', ['id' => $pCohort->getId()]);
    }
}
