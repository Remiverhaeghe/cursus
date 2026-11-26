<?php

namespace App\Controller\School;

use App\Audit\AuditLog;
use App\Entity\AuditEntry;
use App\Repository\AuditEntryRepository;
use App\Security\Voter\AuditVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Journal des actions : connexions, évaluations, changements de mot de passe, comptes créés ou supprimés. */
final class AuditController extends AbstractController
{
    public const int LIMIT = 200;

    #[Route('/ecole/journal', name: 'app_school_audit', methods: ['GET'])]
    public function index(AuditEntryRepository $pEntries): Response
    {
        $this->denyAccessUnlessGranted(AuditVoter::VIEW, AuditEntry::class);

        return $this->render('school/audit.html.twig', [
            'entries' => $pEntries->findLatest(self::LIMIT),
            'labels' => AuditLog::LABELS,
            'limit' => self::LIMIT,
        ]);
    }
}
