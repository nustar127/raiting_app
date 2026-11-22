<?php

namespace App\Services\Rating\Calculator\DTO;

/**
 * Модуль: набор работ + вес модуля в дисциплине.
 */
final readonly class ModuleInput
{
    /**
     * @param WorkGrade[] $works
     */
    public function __construct(
        public array $works,
        public float $weight = 1.0, // вес модуля в ИР
    ) {}
}
