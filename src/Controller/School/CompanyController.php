<?php

namespace App\Controller\School;

use App\Form\CompanyType;
use App\Form\Data\CompanyData;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Entreprises d'accueil, partagées par toute l'école. */
final class CompanyController extends AbstractController
{
    #[Route('/ecole/entreprises', name: 'app_school_companies', methods: ['GET', 'POST'])]
    public function index(Request $pRequest, CompanyRepository $pCompanies, EntityManagerInterface $pEntityManager): Response
    {
        $vData = new CompanyData();
        $vForm = $this->createForm(CompanyType::class, $vData);
        $vForm->handleRequest($pRequest);
        if ($vForm->isSubmitted() && $vForm->isValid()) {
            $pEntityManager->persist($vData->toCompany());
            $pEntityManager->flush();
            $this->addFlash('success', 'Entreprise ajoutée.');

            return $this->redirectToRoute('app_school_companies');
        }

        return $this->render('school/companies.html.twig', [
            'companies' => $pCompanies->findAllByName(),
            'form' => $vForm,
        ], new Response(status: $vForm->isSubmitted() ? 422 : 200));
    }
}
