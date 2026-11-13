<?php

namespace App\Progress;

use App\Entity\Cohort;

/** Progression des apprentis d'une promotion et moyenne de la promotion. */
final readonly class CohortProgress
{
    /** @param list<ApprenticeProgress> $aApprentices */
    public function __construct(
        public Cohort $aCohort,
        public array $aApprentices,
    ) {
    }

    public function average(): float
    {
        return Average::of(array_map(static fn (ApprenticeProgress $pProgress): float => $pProgress->average(), $this->aApprentices));
    }

    public function percentage(): int
    {
        return Average::percentage($this->average());
    }
}
