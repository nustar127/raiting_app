<?php

namespace App\Services\Rating;

use App\Models\CumulativeRating;
use App\Models\Curriculum;
use App\Models\FinalRating;
use App\Models\Grade;
use App\Models\Module;
use App\Models\ModuleRating;
use App\Models\RatingPeriod;
use App\Models\Student;
use App\Models\WeightCoefficient;
use App\Services\Rating\Calculator\DTO\ModuleInput;
use App\Services\Rating\Calculator\DTO\WorkGrade;
use App\Services\Rating\Calculator\RatingCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RatingRecalculator
{
    public function __construct(private readonly RatingCalculator $calculator) {}

    /**
     * Пересчитать всё по студенту в рамках периода.
     */
    public function recalculateForStudent(Student $student, RatingPeriod $period): void
    {
        DB::transaction(function () use ($student, $period) {
            $this->recalculateModules($student, $period);
            $this->recalculateFinal($student, $period);
            $this->recalculateCumulative($student, $period);
        });
    }

    /**
     * Пересчитать всех студентов группы в рамках периода.
     */
    public function recalculateForGroup(int $groupId, RatingPeriod $period): void
    {
        $students = Student::where('group_id', $groupId)
            ->where('status', Student::STATUS_ACTIVE)
            ->get();

        foreach ($students as $student) {
            $this->recalculateForStudent($student, $period);
        }
    }

    /**
     * Пересчитать всех активных студентов факультета в рамках периода.
     */
    public function recalculateForFaculty(int $facultyId, RatingPeriod $period): void
    {
        $students = Student::whereHas('group', fn($q) => $q->where('faculty_id', $facultyId))
            ->where('status', Student::STATUS_ACTIVE)
            ->get();

        foreach ($students as $student) {
            $this->recalculateForStudent($student, $period);
        }
    }

    // -----------------------------------------------------------------
    // Модульные рейтинги
    // -----------------------------------------------------------------

    private function recalculateModules(Student $student, RatingPeriod $period): void
    {
        // Все модули, где у студента есть оценки в этом периоде
        $moduleIds = Grade::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->distinct()
            ->pluck('module_id')
            ->all();

        // Плюс модули из curriculum группы студента (даже без оценок —
        // они получат MP = 0 и hasZeroWork = true)
        $curriculumModuleIds = Module::whereHas(
            'curriculum',
            fn($q) => $q->where('group_id', $student->group_id)
        )
            ->whereHas('grades', fn($q) => $q->where('period_id', $period->id))
            ->pluck('id')
            ->all();

        $allModuleIds = array_unique(array_merge($moduleIds, $curriculumModuleIds));

        foreach ($allModuleIds as $moduleId) {
            $module = Module::with('curriculum')->find($moduleId);
            if (!$module) {
                continue;
            }

            $input = $this->buildModuleInput($student, $module, $period);

            $strategy = app(\App\Services\Rating\Calculator\Strategies\WeightedStrategy::class);
            $rating = $strategy->calculateModuleRating($input);

            ModuleRating::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'module_id' => $module->id,
                    'period_id' => $period->id,
                ],
                [
                    'calculated_value' => $rating->value,
                    'calculated_at' => now(),
                ]
            );
        }
    }

    private function buildModuleInput(Student $student, Module $module, RatingPeriod $period): ModuleInput
    {
        $grades = Grade::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->where('period_id', $period->id)
            ->get();

        $works = [];
        $workWeights = WeightCoefficient::where('period_id', $period->id)
            ->where('curriculum_id', $module->curriculum_id)
            ->where('module_id', $module->id)
            ->where('level', WeightCoefficient::LEVEL_WORK)
            ->get()
            ->keyBy('work_type_id');

        foreach ($grades as $grade) {
            $weight = isset($workWeights[$grade->work_type_id])
                ? (float) $workWeights[$grade->work_type_id]->weight_value
                : 1.0;

            $works[] = new WorkGrade(
                value: $grade->value,
                weight: $weight,
                weeksLate: $grade->weeks_late,
            );
        }

        return new ModuleInput(works: $works, weight: 1.0);
    }

    // -----------------------------------------------------------------
    // Итоговые рейтинги (ИР)
    // -----------------------------------------------------------------

    private function recalculateFinal(Student $student, RatingPeriod $period): void
    {
        // Все curricula, по которым у студента есть модульные рейтинги
        $curricula = Curriculum::where('group_id', $student->group_id)
            ->whereHas('modules.moduleRatings', function ($q) use ($student, $period) {
                $q->where('student_id', $student->id)->where('period_id', $period->id);
            })
            ->get();

        foreach ($curricula as $curriculum) {
            $moduleRatings = ModuleRating::where('student_id', $student->id)
                ->where('period_id', $period->id)
                ->whereIn(
                    'module_id',
                    Module::where('curriculum_id', $curriculum->id)->pluck('id')
                )
                ->get();

            if ($moduleRatings->isEmpty()) {
                continue;
            }

            $moduleWeightMap = WeightCoefficient::where('period_id', $period->id)
                ->where('curriculum_id', $curriculum->id)
                ->where('level', WeightCoefficient::LEVEL_MODULE)
                ->get()
                ->keyBy('module_id');

            // Строим disciplineInput
            $modules = [];
            $isAdmitted = true;
            foreach ($moduleRatings as $mr) {
                $weight = isset($moduleWeightMap[$mr->module_id])
                    ? (float) $moduleWeightMap[$mr->module_id]->weight_value
                    : 1.0;

                $modules[] = new ModuleInput(works: [], weight: $weight);
            }

            // Для ИР нужно знать, был ли хоть один MP = 0
            $hasZeroModule = $moduleRatings->contains(fn($mr) => (float) $mr->calculated_value === 0.0);
            $isAdmitted = !$hasZeroModule;

            $strategy = app(\App\Services\Rating\Calculator\Strategies\WeightedStrategy::class);
            $moduleRatingDTOs = $moduleRatings
                ->map(fn($mr) => new \App\Services\Rating\Calculator\DTO\ModuleRating((float) $mr->calculated_value, false))
                ->values()
                ->all();

            $ir = $strategy->calculateFinalRating($moduleRatingDTOs, $modules);

            FinalRating::updateOrCreate(
                ['student_id' => $student->id, 'curriculum_id' => $curriculum->id, 'period_id' => $period->id],
                [
                    'calculated_value' => $ir,
                    'is_admitted' => $isAdmitted,
                    'calculated_at' => now(),
                ]
            );
        }
    }

    // -----------------------------------------------------------------
    // Совокупный рейтинг (R_сов)
    // -----------------------------------------------------------------

    private function recalculateCumulative(Student $student, RatingPeriod $period): void
    {
        $finals = FinalRating::where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->get();

        if ($finals->isEmpty()) {
            CumulativeRating::updateOrCreate(
                ['student_id' => $student->id, 'period_id' => $period->id],
                [
                    'calculated_value' => 0.0,
                    'disciplines_count' => 0,
                    'calculated_at' => now(),
                ]
            );
            return;
        }

        $sum = $finals->sum(fn($f) => (float) $f->calculated_value);
        $count = $finals->count();
        $value = round($sum / $count, 4);

        CumulativeRating::updateOrCreate(
            ['student_id' => $student->id, 'period_id' => $period->id],
            [
                'calculated_value' => $value,
                'disciplines_count' => $count,
                'calculated_at' => now(),
            ]
        );
    }
}
