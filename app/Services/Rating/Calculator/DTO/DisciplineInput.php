<?php

namespace App\Services\Rating\Calculator\DTO;

/**
 * Дисциплина: набор модулей.
 */
final readonly class DisciplineInput
{
    /**
     * @param ModuleInput[] $modules
     */
    public function __construct(
        public array $modules,
    ) {}
}
