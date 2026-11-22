<?php

namespace App\Services\Rating\Calculator\DTO;

/**
 * Оценка за один вид работы в модуле.
 */
final readonly class WorkGrade
{
    public function __construct(
        public int $value,          // 0..10
        public float $weight,       // 0..1
        public int $weeksLate = 0,  // 0..N
    ) {}
}
