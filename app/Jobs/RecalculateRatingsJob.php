<?php

namespace App\Jobs;

use App\Models\RatingPeriod;
use App\Models\Student;
use App\Services\Rating\RatingRecalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateRatingsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * @param string $scope  student|group|faculty
     * @param int $targetId  ID студента/группы/факультета
     * @param int $periodId  ID периода
     */
    public function __construct(
        public readonly string $scope,
        public readonly int $targetId,
        public readonly int $periodId,
    ) {}

    public function handle(RatingRecalculator $recalculator): void
    {
        $period = RatingPeriod::find($this->periodId);
        if (!$period) {
            return;
        }

        match ($this->scope) {
            'student' => $this->handleStudent($recalculator, $period),
            'group' => $recalculator->recalculateForGroup($this->targetId, $period),
            'faculty' => $recalculator->recalculateForFaculty($this->targetId, $period),
            default => null,
        };
    }

    private function handleStudent(RatingRecalculator $recalculator, RatingPeriod $period): void
    {
        $student = Student::find($this->targetId);
        if ($student) {
            $recalculator->recalculateForStudent($student, $period);
        }
    }
}
