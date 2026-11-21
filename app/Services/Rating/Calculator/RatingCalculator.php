<?php

namespace App\Services\Rating\Calculator;

use App\Services\Rating\Calculator\DTO\DisciplineInput;
use App\Services\Rating\Calculator\DTO\ModuleInput;
use App\Services\Rating\Calculator\DTO\ModuleRating;
use App\Services\Rating\Calculator\DTO\StudentRatingResult;
use App\Services\Rating\Calculator\Strategies\ArithmeticStrategy;
use App\Services\Rating\Calculator\Strategies\RatingStrategy;
use App\Services\Rating\Calculator\Strategies\WeightedStrategy;

final class RatingCalculator
{
    /**
     * Определяет, есть ли веса в модулях. Если хотя бы у одного модуля
     * weight != 1.0 — считаем по взвешенной стратегии. Иначе — арифметика.
     *
     * На практике: веса задаются в weight_coefficients. Если в БД есть
     * module-level или work-level веса, оркестратор передаст их сюда.
     */
    public function calculateDiscipline(DisciplineInput $discipline): StudentRatingResult
    {
        $strategy = $this->pickStrategy($discipline);

        $moduleRatings = [];
        foreach ($discipline->modules as $module) {
            $moduleRatings[] = $strategy->calculateModuleRating($module);
        }

        $finalRating = $strategy->calculateFinalRating($moduleRatings, $discipline->modules);

        $isAdmitted = true;
        foreach ($moduleRatings as $rating) {
            if ($rating->hasZeroWork) {
                $isAdmitted = false;
                break;
            }
        }

        return new StudentRatingResult($moduleRatings, $finalRating, $isAdmitted);
    }

    /**
     * Выбор стратегии:
     * - если хотя бы у одной работы weight != 1.0 → Weighted
     * - иначе → Arithmetic (все равнозначны)
     *
     * Тонкость: если оркестратор передал веса для модулей, но не для работ,
     * стратегия всё равно Weighted — она обработает работы с weight=1.0.
     */
    private function pickStrategy(DisciplineInput $discipline): RatingStrategy
    {
        $hasWeights = false;

        foreach ($discipline->modules as $module) {
            if (abs($module->weight - 1.0) > 0.0001) {
                $hasWeights = true;
                break;
            }

            foreach ($module->works as $work) {
                if (abs($work->weight - 1.0) > 0.0001) {
                    $hasWeights = true;
                    break 2;
                }
            }
        }

        return $hasWeights
            ? new WeightedStrategy()
            : new ArithmeticStrategy();
    }
}
