<?php

namespace App\Progress;

use App\Entity\Apprenticeship;

/** Progression complète d'un apprenti sur son contrat, bloc par bloc. */
final readonly class ApprenticeProgress
{
    /** @param list<BlockProgress> $aBlocks */
    public function __construct(
        public Apprenticeship $aApprenticeship,
        public array $aBlocks,
    ) {
    }

    /** @return list<CompetenceProgress> */
    public function competences(): array
    {
        $rCompetences = [];
        foreach ($this->aBlocks as $vBlock) {
            array_push($rCompetences, ...$vBlock->aCompetences);
        }

        return $rCompetences;
    }

    public function average(): float
    {
        return Average::of(array_map(static fn (CompetenceProgress $pProgress): int => $pProgress->value(), $this->competences()));
    }

    public function percentage(): int
    {
        return Average::percentage($this->average());
    }

    public function validatedCount(): int
    {
        return \count(array_filter($this->competences(), static fn (CompetenceProgress $pProgress): bool => $pProgress->isValidated()));
    }

    public function unevaluatedCount(): int
    {
        return \count(array_filter($this->competences(), static fn (CompetenceProgress $pProgress): bool => !$pProgress->isEvaluated()));
    }

    /**
     * Compétences qui restent à valider (niveau courant sous le seuil, ou jamais évaluées).
     *
     * @return list<CompetenceProgress>
     */
    public function remaining(): array
    {
        return array_values(array_filter($this->competences(), static fn (CompetenceProgress $pProgress): bool => !$pProgress->isValidated()));
    }
}
