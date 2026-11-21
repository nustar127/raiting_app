<?php

namespace App\Services\Rating\Calculator\DTO;

/**
 * Рассчитанный модульный рейтинг (MP).
 */
final readonly class ModuleRating
{
    public function __construct(
        public float $value,        // 0..90
        public bool $hasZeroWork,   // есть ли работа с 0 баллов (→ не допущен)
    ) {}
}
