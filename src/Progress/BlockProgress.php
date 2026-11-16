<?php

namespace App\Progress;

use App\Entity\CompetenceBlock;

/** Progression d'un bloc : moyenne des niveaux courants, les compétences non évaluées comptant pour 0. */
final readonly class BlockProgress
{
    /** @param list<CompetenceProgress> $aCompetences */
    public function __construct(
        public CompetenceBlock $aBlock,
        public array $aCompetences,
    ) {
    }

    public function average(): float
    {
        return Average::of(array_map(static fn (CompetenceProgress $pProgress): int => $pProgress->value(), $this->aCompetences));
    }

    /** @return list<CompetenceProgress> */
    public function unevaluated(): array
    {
        return array_values(array_filter($this->aCompetences, static fn (CompetenceProgress $pProgress): bool => !$pProgress->isEvaluated()));
    }

    public function percentage(): int
    {
        return Average::percentage($this->average());
    }
}
