<?php

namespace App\Console\Commands;

use App\Models\RatingPeriod;
use App\Models\Student;
use App\Services\Rating\RatingRecalculator;
use Illuminate\Console\Command;

class RecalculateRatingsCommand extends Command
{
    protected $signature = 'ratings:recalculate
        {--period= : ID периода (обязательно)}
        {--student= : ID студента}
        {--group= : ID группы}
        {--faculty= : ID факультета}
        {--all : Все студенты периода}';

    protected $description = 'Пересчитать рейтинги (MP, ИР, R_сов)';

    public function handle(RatingRecalculator $recalculator): int
    {
        $periodId = $this->option('period');
        if (!$periodId) {
            $this->error('Укажите --period=<id>.');
            return self::FAILURE;
        }

        $period = RatingPeriod::find($periodId);
        if (!$period) {
            $this->error("Период ID {$periodId} не найден.");
            return self::FAILURE;
        }

        $this->info("Пересчёт для периода: {$period->name} (ID {$period->id}, {$period->status})");

        if ($studentId = $this->option('student')) {
            $student = Student::find($studentId);
            if (!$student) {
                $this->error("Студент ID {$studentId} не найден.");
                return self::FAILURE;
            }
            $recalculator->recalculateForStudent($student, $period);
            $this->info("Пересчитан студент ID {$studentId}.");
            return self::SUCCESS;
        }

        if ($groupId = $this->option('group')) {
            $recalculator->recalculateForGroup((int) $groupId, $period);
            $this->info("Пересчитана группа ID {$groupId}.");
            return self::SUCCESS;
        }

        if ($facultyId = $this->option('faculty')) {
            $recalculator->recalculateForFaculty((int) $facultyId, $period);
            $this->info("Пересчитан факультет ID {$facultyId}.");
            return self::SUCCESS;
        }

        if ($this->option('all')) {
            $recalculator->recalculateForFaculty($period->faculty_id, $period);
            $this->info("Пересчитаны все студенты факультета ID {$period->faculty_id}.");
            return self::SUCCESS;
        }

        $this->error('Укажите --student, --group, --faculty или --all.');
        return self::FAILURE;
    }
}
