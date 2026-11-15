<?php

namespace App\Evaluation;

use App\Entity\Apprenticeship;
use App\Entity\Evaluation;
use App\Entity\User;
use App\Form\Data\EvaluationData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

/** Enregistre une évaluation validée, datée de maintenant, et la note au journal (identifiants seulement). */
final class EvaluationRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $aEntityManager,
        private readonly ClockInterface $aClock,
        private readonly LoggerInterface $aLogger,
    ) {
    }

    public function record(EvaluationData $pData, Apprenticeship $pApprenticeship, User $pEvaluator): Evaluation
    {
        $rEvaluation = $pData->toEvaluation($pApprenticeship, $pEvaluator, $this->aClock->now());
        $this->aEntityManager->persist($rEvaluation);
        $this->aEntityManager->flush();
        $this->aLogger->info('Évaluation enregistrée', [
            'evaluation_id' => $rEvaluation->getId(),
            'apprenticeship_id' => $pApprenticeship->getId(),
            'evaluator_id' => $pEvaluator->getId(),
        ]);

        return $rEvaluation;
    }
}
