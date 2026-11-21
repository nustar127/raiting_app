<?php

namespace App\Services\Rating\Calculator\Strategies;

use App\Services\Rating\Calculator\DTO\ModuleInput;
use App\Services\Rating\Calculator\DTO\ModuleRating;
use App\Services\Rating\Calculator\LatePenalty;

/**
 * Формулы 1.1, 1.2:
 *   MP = (Σ(Oᵢ × Bᵢ) / ΣBᵢ) × 9
 *   ИР = Σ(Mⱼ × Bⱼ) / ΣBⱼ
 * с применением штрафа 1.5 к MP.
 */
final class WeightedStrategy implements RatingStrategy
{
    private const MULTIPLIER = 9.0;
    private const MAX_MP = 90.0;
    private const MAX_IR = 90.0;

    public function calculateModuleRating(ModuleInput $module): ModuleRating
    {
        if (empty($module->works)) {
            return new ModuleRating(0.0, true);
        }

        $weightedSum = 0.0;
        $weightSum = 0.0;
        $hasZero = false;

        foreach ($module->works as $work) {
            // Учитываем штраф за просрочку на уровне работы
            $penalty = LatePenalty::coefficient($work->weeksLate);
            $effectiveValue = $work->value * $penalty;

            $weightedSum += $effectiveValue * $work->weight;
            $weightSum += $work->weight;

            if ($work->value === 0) {
                $hasZero = true;
            }
        }

        if ($weightSum <= 0.0) {
            return new ModuleRating(0.0, true);
        }

        $base = ($weightedSum / $weightSum) * self::MULTIPLIER;
        $mp = min($base, self::MAX_MP);

        return new ModuleRating(round($mp, 4), $hasZero);
    }

    public function calculateFinalRating(array $moduleRatings, array $moduleInputs): float
    {
        if (empty($moduleRatings)) {
            return 0.0;
        }

        $weightedSum = 0.0;
        $weightSum = 0.0;

        foreach ($moduleInputs as $i => $module) {
            $mp = $moduleRatings[$i]->value;
            $weightedSum += $mp * $module->weight;
            $weightSum += $module->weight;
        }

        if ($weightSum <= 0.0) {
            return 0.0;
        }

        $ir = $weightedSum / $weightSum;

        return round(min($ir, self::MAX_IR), 4);
    }
}
