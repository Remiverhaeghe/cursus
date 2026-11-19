<?php

namespace App\Controller;

use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\User;
use App\Form\Data\MessageData;
use App\Form\MessageType;
use App\Repository\CompetenceMessageRepository;
use App\Security\Voter\ExchangeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Échanges entre le tuteur et l'apprenti, un fil par compétence du contrat. */
final class ExchangeController extends AbstractController
{
    #[Route('/suivi/{id}/echanges', name: 'app_exchange_index', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ExchangeVoter::VIEW, subject: 'pApprenticeship')]
    public function index(Apprenticeship $pApprenticeship, CompetenceMessageRepository $pMessages): Response
    {
        return $this->render('exchange/index.html.twig', [
            'apprenticeship' => $pApprenticeship,
            'summary' => $pMessages->summarize($pApprenticeship),
        ]);
    }

    #[Route('/suivi/{id}/echanges/{competence}', name: 'app_exchange_thread', requirements: ['id' => '\d+', 'competence' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ExchangeVoter::VIEW, subject: 'pApprenticeship')]
    public function thread(
        Apprenticeship $pApprenticeship,
        #[MapEntity(id: 'competence')] Competence $pCompetence,
        #[CurrentUser] User $pUser,
        Request $pRequest,
        CompetenceMessageRepository $pMessages,
        EntityManagerInterface $pEntityManager,
        ClockInterface $pClock,
    ): Response {
        // Une compétence d'une autre formation n'a pas de fil pour ce contrat
        if (!$pApprenticeship->getFormation()->hasCompetence($pCompetence)) {
            throw $this->createNotFoundException('Compétence hors de la formation.');
        }

        $vForm = null;
        if ($this->isGranted(ExchangeVoter::POST, $pApprenticeship)) {
            $vData = new MessageData();
            $vForm = $this->createForm(MessageType::class, $vData);
            $vForm->handleRequest($pRequest);
            if ($vForm->isSubmitted() && $vForm->isValid()) {
                $pEntityManager->persist($vData->toMessage($pApprenticeship, $pCompetence, $pUser, $pClock->now()));
                $pEntityManager->flush();
                $this->addFlash('success', 'Message envoyé.');

                return $this->redirectToRoute('app_exchange_thread', ['id' => $pApprenticeship->getId(), 'competence' => $pCompetence->getId()]);
            }
        } elseif ($pRequest->isMethod('POST')) {
            throw $this->createAccessDeniedException('L\'école lit les échanges sans y écrire.');
        }

        return $this->render('exchange/thread.html.twig', [
            'apprenticeship' => $pApprenticeship,
            'competence' => $pCompetence,
            'messages' => $pMessages->findThread($pApprenticeship, $pCompetence),
            'form' => $vForm,
        ], new Response(status: null !== $vForm && $vForm->isSubmitted() ? 422 : 200));
    }
}
