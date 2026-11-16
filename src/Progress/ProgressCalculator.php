<?php

namespace App\Progress;

use App\Entity\Apprenticeship;
use App\Entity\Evaluation;

/** Règle de progression : le niveau courant d'une compétence est sa dernière évaluation, sans lecture de la base. */
final class ProgressCalculator
{
    /** @param list<Evaluation> $pEvaluations évaluations du contrat, dans n'importe quel ordre */
    public function forApprenticeship(Apprenticeship $pApprenticeship, array $pEvaluations): ApprenticeProgress
    {
        // Tri stable par date : à date égale, l'ordre d'enregistrement est conservé
        usort($pEvaluations, static fn (Evaluation $pFirst, Evaluation $pSecond): int => $pFirst->getEvaluatedAt() <=> $pSecond->getEvaluatedAt());
        $vByCompetence = [];
        foreach ($pEvaluations as $vEvaluation) {
            $vByCompetence[spl_object_id($vEvaluation->getCompetence())][] = $vEvaluation;
        }

        $vBlocks = [];
        foreach ($pApprenticeship->getFormation()->getBlocks() as $vBlock) {
            $vCompetences = [];
            foreach ($vBlock->getCompetences() as $vCompetence) {
                $vHistory = $vByCompetence[spl_object_id($vCompetence)] ?? [];
                $vLast = [] === $vHistory ? null : $vHistory[\count($vHistory) - 1];
                $vCompetences[] = new CompetenceProgress(
                    $vCompetence,
                    array_map(static fn (Evaluation $pEvaluation) => $pEvaluation->getLevel(), $vHistory),
                    $vLast?->getEvaluatedAt(),
                );
            }
            $vBlocks[] = new BlockProgress($vBlock, $vCompetences);
        }

        $rProgress = new ApprenticeProgress($pApprenticeship, $vBlocks);

        return $rProgress;
    }
}
