<?php

namespace App\Services\Rating\Calculator;

/**
 * Формула 1.5: MP = MP_base × max(0.5; 1 - 0.1 × N)
 * где N — количество недель просрочки.
 */
final class LatePenalty
{
    public const MAX_PENALTY = 0.5;   // минимум 50% от базового
    public const PENALTY_PER_WEEK = 0.1;

    public static function coefficient(int $weeksLate): float
    {
        if ($weeksLate <= 0) {
            return 1.0;
        }

        $raw = 1.0 - self::PENALTY_PER_WEEK * $weeksLate;

        return max(self::MAX_PENALTY, $raw);
    }

    public static function apply(float $baseRating, int $weeksLate): float
    {
        return $baseRating * self::coefficient($weeksLate);
    }
}
