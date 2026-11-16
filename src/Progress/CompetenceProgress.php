<?php

namespace App\Progress;

use App\Entity\Competence;
use App\Enum\CompetenceLevel;

/** Niveau courant d'une compétence (dernière évaluation) et niveaux successifs pour la courbe. */
final readonly class CompetenceProgress
{
    /** @param list<CompetenceLevel> $aHistory niveaux du plus ancien au plus récent */
    public function __construct(
        public Competence $aCompetence,
        public array $aHistory,
        public ?\DateTimeImmutable $aLastEvaluatedAt,
    ) {
    }

    public function isEvaluated(): bool
    {
        return [] !== $this->aHistory;
    }

    public function current(): ?CompetenceLevel
    {
        $rLevel = [] === $this->aHistory ? null : $this->aHistory[\count($this->aHistory) - 1];

        return $rLevel;
    }

    /** Valeur retenue pour les moyennes : une compétence non évaluée compte pour 0. */
    public function value(): int
    {
        return $this->current()->value ?? 0;
    }

    public function isValidated(): bool
    {
        return $this->current()?->isValidated() ?? false;
    }

    /** Points de la courbe SVG, dans un cadre de largeur et hauteur données (niveau 4 en haut). */
    public function sparklinePoints(int $pWidth, int $pHeight): string
    {
        $vCount = \count($this->aHistory);
        $vPoints = [];
        foreach ($this->aHistory as $vIndex => $vLevel) {
            $vX = 1 === $vCount ? $pWidth / 2 : $vIndex * $pWidth / ($vCount - 1);
            $vY = (4 - $vLevel->value) * $pHeight / 4;
            $vPoints[] = \sprintf('%s,%s', round($vX, 1), round($vY, 1));
        }

        return implode(' ', $vPoints);
    }
}
