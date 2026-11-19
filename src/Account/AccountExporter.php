<?php

namespace App\Account;

use App\Entity\Apprenticeship;
use App\Entity\CompetenceMessage;
use App\Entity\Evaluation;
use App\Entity\User;
use App\Repository\ApprenticeshipRepository;
use App\Repository\CompetenceMessageRepository;
use App\Repository\EvaluationRepository;

/** Export des données d'un compte : profil, contrats, évaluations et messages qui le concernent. Jamais l'empreinte du mot de passe ni le secret TOTP. */
final class AccountExporter
{
    public function __construct(
        private readonly ApprenticeshipRepository $aApprenticeships,
        private readonly EvaluationRepository $aEvaluations,
        private readonly CompetenceMessageRepository $aMessages,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(User $pUser, \DateTimeImmutable $pNow): array
    {
        $rData = [
            'exported_at' => $pNow->format(\DATE_ATOM),
            'account' => [
                'email' => $pUser->getEmail(),
                'first_name' => $pUser->getFirstName(),
                'last_name' => $pUser->getLastName(),
                'role' => $pUser->getRole()->label(),
                'two_factor_enabled' => $pUser->isTotpAuthenticationEnabled(),
            ],
            'apprenticeships' => array_map(self::apprenticeship(...), $this->aApprenticeships->findInvolving($pUser)),
            'evaluations' => array_map(self::evaluation(...), $this->aEvaluations->findInvolving($pUser)),
            'messages' => array_map(self::message(...), $this->aMessages->findInvolving($pUser)),
        ];

        return $rData;
    }

    /** @return array<string, string> */
    private static function apprenticeship(Apprenticeship $pApprenticeship): array
    {
        return [
            'formation' => $pApprenticeship->getFormation()->getName(),
            'cohort' => $pApprenticeship->getCohort()->getName(),
            'apprentice' => $pApprenticeship->getApprentice()->getFullName(),
            'tutor' => $pApprenticeship->getTutor()->getFullName(),
            'company' => (string) $pApprenticeship->getCompany(),
            'start_date' => $pApprenticeship->getStartDate()->format('Y-m-d'),
            'end_date' => $pApprenticeship->getEndDate()->format('Y-m-d'),
        ];
    }

    /** @return array<string, string|int|null> */
    private static function evaluation(Evaluation $pEvaluation): array
    {
        return [
            'date' => $pEvaluation->getEvaluatedAt()->format(\DATE_ATOM),
            'apprentice' => $pEvaluation->getApprenticeship()->getApprentice()->getFullName(),
            'competence' => (string) $pEvaluation->getCompetence(),
            'level' => $pEvaluation->getLevel()->value,
            'context' => $pEvaluation->getContext()->label(),
            'evaluator' => $pEvaluation->getEvaluator()->getFullName(),
            'comment' => $pEvaluation->getComment(),
        ];
    }

    /** @return array<string, string> */
    private static function message(CompetenceMessage $pMessage): array
    {
        return [
            'date' => $pMessage->getPostedAt()->format(\DATE_ATOM),
            'apprentice' => $pMessage->getApprenticeship()->getApprentice()->getFullName(),
            'competence' => (string) $pMessage->getCompetence(),
            'author' => $pMessage->getAuthor()->getFullName(),
            'body' => $pMessage->getBody(),
        ];
    }
}
