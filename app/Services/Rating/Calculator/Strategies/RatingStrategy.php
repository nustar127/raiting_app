<?php

namespace App\Services\Rating\Calculator\Strategies;

use App\Services\Rating\Calculator\DTO\ModuleInput;
use App\Services\Rating\Calculator\DTO\ModuleRating;

interface RatingStrategy
{
    /**
     * Рассчитать модульный рейтинг (MP) по формуле 1.1 или 1.3.
     */
    public function calculateModuleRating(ModuleInput $module): ModuleRating;

    /**
     * Рассчитать итоговый рейтинг (ИР) по дисциплине по формуле 1.2 или 1.4.
     *
     * @param ModuleRating[] $moduleRatings
     * @param ModuleInput[] $moduleInputs
     */
    public function calculateFinalRating(array $moduleRatings, array $moduleInputs): float;
}
