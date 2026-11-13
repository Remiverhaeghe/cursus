<?php

namespace App\Progress;

use App\Entity\Apprenticeship;
use App\Repository\EvaluationRepository;

/** Charge les évaluations depuis la base et délègue le calcul à ProgressCalculator. */
final class ProgressProvider
{
    public function __construct(
        private readonly EvaluationRepository $aEvaluations,
        private readonly ProgressCalculator $aCalculator,
    ) {
    }

    public function forApprenticeship(Apprenticeship $pApprenticeship): ApprenticeProgress
    {
        return $this->aCalculator->forApprenticeship($pApprenticeship, $this->aEvaluations->findForApprenticeship($pApprenticeship));
    }

    /**
     * @param list<Apprenticeship> $pApprenticeships
     *
     * @return list<ApprenticeProgress>
     */
    public function forApprenticeships(array $pApprenticeships): array
    {
        $vEvaluations = $this->aEvaluations->findGroupedByApprenticeship($pApprenticeships);
        $rProgresses = [];
        foreach ($pApprenticeships as $vApprenticeship) {
            $rProgresses[] = $this->aCalculator->forApprenticeship($vApprenticeship, $vEvaluations[(int) $vApprenticeship->getId()] ?? []);
        }

        return $rProgresses;
    }

    /**
     * Regroupe les progressions par promotion, dans l'ordre des contrats reçus.
     *
     * @param list<Apprenticeship> $pApprenticeships
     *
     * @return list<CohortProgress>
     */
    public function byCohort(array $pApprenticeships): array
    {
        $vGroups = [];
        $vCohorts = [];
        foreach ($this->forApprenticeships($pApprenticeships) as $vProgress) {
            $vCohort = $vProgress->aApprenticeship->getCohort();
            $vKey = spl_object_id($vCohort);
            $vCohorts[$vKey] = $vCohort;
            $vGroups[$vKey][] = $vProgress;
        }

        $rCohorts = [];
        foreach ($vGroups as $vKey => $vProgresses) {
            $rCohorts[] = new CohortProgress($vCohorts[$vKey], $vProgresses);
        }

        return $rCohorts;
    }
}
