<?php

namespace App\Services\Rating\Calculator\DTO;

/**
 * Полный результат расчёта по студенту за период.
 */
final readonly class StudentRatingResult
{
    /**
     * @param ModuleRating[] $moduleRatings   MP по каждому модулю (в порядке модулей)
     * @param float $finalRating              ИР по дисциплине (0..90)
     * @param bool $isAdmitted                Допущен ли к аттестации (нет MP=0)
     */
    public function __construct(
        public array $moduleRatings,
        public float $finalRating,
        public bool $isAdmitted,
    ) {}
}
