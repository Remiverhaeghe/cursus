<?php

namespace App\Progress;

/** Calculs de moyenne partagés par les blocs, les apprentis et les promotions. */
final class Average
{
    /** @param list<int|float> $pValues */
    public static function of(array $pValues): float
    {
        if ([] === $pValues) {
            return 0.0;
        }

        return round(array_sum($pValues) / \count($pValues), 2);
    }

    /** Moyenne sur 4 ramenée en pourcentage, pour les barres de progression. */
    public static function percentage(float $pAverage): int
    {
        return (int) round($pAverage * 25);
    }
}
