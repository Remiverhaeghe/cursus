<?php

namespace App\Controller\School;

use App\Entity\Cohort;
use App\Form\ApprenticeshipType;
use App\Form\Data\ApprenticeshipData;
use App\Security\Voter\FormationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Une promotion et ses contrats : c'est ici que l'école rattache un apprenti à son tuteur. */
final class CohortController extends AbstractController
{
    #[Route('/ecole/promotions/{id}', name: 'app_school_cohort_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pCohort')]
    public function show(Cohort $pCohort, Request $pRequest, EntityManagerInterface $pEntityManager): Response
    {
        $vData = new ApprenticeshipData();
        $vForm = $this->createForm(ApprenticeshipType::class, $vData);
        $vForm->handleRequest($pRequest);
        if ($vForm->isSubmitted() && $vForm->isValid()) {
            // Le formulaire ne propose que des apprentis et des tuteurs : le contrat est valide par construction
            $pEntityManager->persist($vData->toApprenticeship($pCohort));
            $pEntityManager->flush();
            $this->addFlash('success', 'Apprenti rattaché à la promotion.');

            return $this->redirectToRoute('app_school_cohort_show', ['id' => $pCohort->getId()]);
        }

        return $this->render('school/cohort_show.html.twig', [
            'cohort' => $pCohort,
            'form' => $vForm,
        ], new Response(status: $vForm->isSubmitted() ? 422 : 200));
    }
}
