<?php

namespace App\Services\Rating\Calculator\Strategies;

use App\Services\Rating\Calculator\DTO\ModuleInput;
use App\Services\Rating\Calculator\DTO\ModuleRating;
use App\Services\Rating\Calculator\LatePenalty;

/**
 * Формулы 1.3, 1.4:
 *   MP = (ΣOᵢ / n) × 9
 *   ИР = ΣMⱼ / m
 * с применением штрафа 1.5 к MP.
 *
 * Используется, когда веса в рейтинг-плане не заданы.
 */
final class ArithmeticStrategy implements RatingStrategy
{
    private const MULTIPLIER = 9.0;
    private const MAX_MP = 90.0;
    private const MAX_IR = 90.0;

    public function calculateModuleRating(ModuleInput $module): ModuleRating
    {
        $n = count($module->works);

        if ($n === 0) {
            return new ModuleRating(0.0, true);
        }

        $sum = 0.0;
        $hasZero = false;

        foreach ($module->works as $work) {
            $penalty = LatePenalty::coefficient($work->weeksLate);
            $sum += $work->value * $penalty;

            if ($work->value === 0) {
                $hasZero = true;
            }
        }

        $base = ($sum / $n) * self::MULTIPLIER;
        $mp = min($base, self::MAX_MP);

        return new ModuleRating(round($mp, 4), $hasZero);
    }

    public function calculateFinalRating(array $moduleRatings, array $moduleInputs): float
    {
        $m = count($moduleRatings);

        if ($m === 0) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($moduleRatings as $rating) {
            $sum += $rating->value;
        }

        return round(min($sum / $m, self::MAX_IR), 4);
    }
}
