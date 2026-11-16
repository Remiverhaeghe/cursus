<?php

namespace App\Form\Data;

use App\Entity\Apprenticeship;
use App\Entity\Competence;
use App\Entity\Evaluation;
use App\Entity\User;
use App\Enum\CompetenceLevel;
use App\Enum\EvaluationContext;
use Symfony\Component\Validator\Constraints as Assert;

/** Valeurs saisies pour une évaluation ; le contexte vient du profil de l'évaluateur. */
final class EvaluationData
{
    #[Assert\NotNull]
    public ?Competence $competence = null;

    #[Assert\NotNull]
    public ?CompetenceLevel $level = null;

    #[Assert\Length(max: 1000)]
    public ?string $comment = null;

    public function toEvaluation(Apprenticeship $pApprenticeship, User $pEvaluator, \DateTimeImmutable $pNow): Evaluation
    {
        $vContext = EvaluationContext::forRole($pEvaluator->getRole());
        if (null === $this->competence || null === $this->level || null === $vContext) {
            throw new \LogicException('Évaluation non validée.');
        }

        return new Evaluation($pApprenticeship, $this->competence, $pEvaluator, $this->level, $vContext, $pNow, $this->comment);
    }
}
