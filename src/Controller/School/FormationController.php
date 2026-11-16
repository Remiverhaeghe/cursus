<?php

namespace App\Controller\School;

use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use App\Entity\User;
use App\Form\CohortType;
use App\Form\CompetenceBlockType;
use App\Form\CompetenceType;
use App\Form\Data\CohortData;
use App\Form\Data\CompetenceBlockData;
use App\Form\Data\CompetenceData;
use App\Form\Data\FormationData;
use App\Form\FormationType;
use App\Repository\FormationRepository;
use App\Security\Voter\FormationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Formations de l'école : référentiel (blocs puis compétences) et promotions. */
#[Route('/ecole')]
final class FormationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly FormFactoryInterface $aFormFactory,
    ) {
    }

    #[Route('/formations', name: 'app_school_formations', methods: ['GET', 'POST'])]
    public function index(#[CurrentUser] User $pUser, Request $pRequest, FormationRepository $pFormations): Response
    {
        $vData = new FormationData();
        $vForm = $this->createForm(FormationType::class, $vData);
        $vForm->handleRequest($pRequest);
        if ($vForm->isSubmitted() && $vForm->isValid()) {
            $vFormation = $vData->toFormation($pUser);
            $this->aEntityManager->persist($vFormation);
            $this->aEntityManager->flush();
            $this->addFlash('success', 'Formation créée.');

            return $this->redirectToRoute('app_school_formation_show', ['id' => $vFormation->getId()]);
        }

        return $this->render('school/formations.html.twig', [
            'formations' => $pFormations->findManagedBy($pUser),
            'form' => $vForm,
        ], new Response(status: $vForm->isSubmitted() ? 422 : 200));
    }

    #[Route('/formations/{id}', name: 'app_school_formation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pFormation')]
    public function show(Formation $pFormation): Response
    {
        return $this->renderShow($pFormation);
    }

    #[Route('/formations/{id}/blocs', name: 'app_school_block_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pFormation')]
    public function newBlock(Formation $pFormation, Request $pRequest): Response
    {
        $vData = new CompetenceBlockData();
        $vForm = $this->createForm(CompetenceBlockType::class, $vData);
        $vForm->handleRequest($pRequest);
        if (!$vForm->isSubmitted() || !$vForm->isValid()) {
            return $this->renderShow($pFormation, $vForm);
        }
        $this->aEntityManager->persist($vData->toBlock($pFormation));
        $this->aEntityManager->flush();
        $this->addFlash('success', 'Bloc ajouté.');

        return $this->redirectToRoute('app_school_formation_show', ['id' => $pFormation->getId()]);
    }

    #[Route('/blocs/{id}/competences', name: 'app_school_competence_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pBlock')]
    public function newCompetence(CompetenceBlock $pBlock, Request $pRequest): Response
    {
        $vData = new CompetenceData();
        $vForm = $this->competenceForm($pBlock, $vData);
        $vForm->handleRequest($pRequest);
        if (!$vForm->isSubmitted() || !$vForm->isValid()) {
            return $this->renderShow($pBlock->getFormation(), pCompetenceForms: [(int) $pBlock->getId() => $vForm]);
        }
        $this->aEntityManager->persist($vData->toCompetence($pBlock));
        $this->aEntityManager->flush();
        $this->addFlash('success', 'Compétence ajoutée.');

        return $this->redirectToRoute('app_school_formation_show', ['id' => $pBlock->getFormation()->getId()]);
    }

    #[Route('/formations/{id}/promotions', name: 'app_school_cohort_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(FormationVoter::MANAGE, subject: 'pFormation')]
    public function newCohort(Formation $pFormation, Request $pRequest): Response
    {
        $vData = new CohortData();
        $vForm = $this->createForm(CohortType::class, $vData);
        $vForm->handleRequest($pRequest);
        if (!$vForm->isSubmitted() || !$vForm->isValid()) {
            return $this->renderShow($pFormation, pCohortForm: $vForm);
        }
        $vCohort = $vData->toCohort($pFormation);
        $this->aEntityManager->persist($vCohort);
        $this->aEntityManager->flush();
        $this->addFlash('success', 'Promotion ouverte.');

        return $this->redirectToRoute('app_school_cohort_show', ['id' => $vCohort->getId()]);
    }

    /**
     * @param FormInterface<mixed>|null        $pBlockForm
     * @param array<int, FormInterface<mixed>> $pCompetenceForms formulaires déjà soumis, par identifiant de bloc
     * @param FormInterface<mixed>|null        $pCohortForm
     */
    private function renderShow(
        Formation $pFormation,
        ?FormInterface $pBlockForm = null,
        array $pCompetenceForms = [],
        ?FormInterface $pCohortForm = null,
    ): Response {
        $vCompetenceViews = [];
        foreach ($pFormation->getBlocks() as $vBlock) {
            $vBlockId = (int) $vBlock->getId();
            $vCompetenceViews[$vBlockId] = ($pCompetenceForms[$vBlockId] ?? $this->competenceForm($vBlock, new CompetenceData()))->createView();
        }
        $vSubmitted = null !== $pBlockForm || [] !== $pCompetenceForms || null !== $pCohortForm;

        return $this->render('school/formation_show.html.twig', [
            'formation' => $pFormation,
            'block_form' => ($pBlockForm ?? $this->createForm(CompetenceBlockType::class, new CompetenceBlockData()))->createView(),
            'competence_forms' => $vCompetenceViews,
            'cohort_form' => ($pCohortForm ?? $this->createForm(CohortType::class, new CohortData()))->createView(),
        ], new Response(status: $vSubmitted ? 422 : 200));
    }

    /** @return FormInterface<mixed> */
    private function competenceForm(CompetenceBlock $pBlock, CompetenceData $pData): FormInterface
    {
        $rForm = $this->aFormFactory->createNamed('competence_'.$pBlock->getId(), CompetenceType::class, $pData, [
            'action' => $this->generateUrl('app_school_competence_new', ['id' => $pBlock->getId()]),
        ]);

        return $rForm;
    }
}
