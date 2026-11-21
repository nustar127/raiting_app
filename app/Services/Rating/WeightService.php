<?php

namespace App\Services\Rating;

use App\Models\Curriculum;
use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\WeightCoefficient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeightService
{
    /**
     * Утвердить все веса периода. Проверяет суммы на всех уровнях.
     *
     * @throws ValidationException
     */
    public function approveAll(RatingPeriod $period, int $approverId): void
    {
        $problems = $this->validateWeights($period);

        if (!empty($problems)) {
            throw ValidationException::withMessages([
                'weights' => $problems,
            ]);
        }

        DB::transaction(function () use ($period, $approverId) {
            WeightCoefficient::where('period_id', $period->id)
                ->whereNull('approved_by')
                ->update([
                    'approved_by' => $approverId,
                    'approved_at' => now(),
                ]);
        });
    }

    /**
     * @return array<string> Список проблем
     */
    private function validateWeights(RatingPeriod $period): array
    {
        $problems = [];

        $curricula = Curriculum::query()
            ->whereHas('group', fn($q) => $q->where('faculty_id', $period->faculty_id))
            ->with('modules')
            ->get();

        foreach ($curricula as $curriculum) {
            $weights = WeightCoefficient::where('period_id', $period->id)
                ->where('curriculum_id', $curriculum->id)
                ->get();

            // Группируем по уровням
            $disciplineWeights = $weights->where('level', WeightCoefficient::LEVEL_DISCIPLINE);
            $moduleWeights = $weights->where('level', WeightCoefficient::LEVEL_MODULE);
            $workWeights = $weights->where('level', WeightCoefficient::LEVEL_WORK);

            // 1. Discipline-level: если есть — сумма = 1
            if ($disciplineWeights->isNotEmpty()) {
                $sum = (float) $disciplineWeights->sum('weight_value');
                if (abs($sum - 1.0) > 0.0001) {
                    $problems[] = sprintf(
                        'Curriculum ID %d: сумма весов уровня «дисциплина» = %.4f, требуется 1.0000.',
                        $curriculum->id,
                        $sum
                    );
                }
            }

            // 2. Module-level: если есть — сумма = 1, и все модули покрыты
            if ($moduleWeights->isNotEmpty()) {
                $sum = (float) $moduleWeights->sum('weight_value');
                if (abs($sum - 1.0) > 0.0001) {
                    $problems[] = sprintf(
                        'Curriculum ID %d: сумма весов уровня «модуль» = %.4f, требуется 1.0000.',
                        $curriculum->id,
                        $sum
                    );
                }

                $weightedModuleIds = $moduleWeights->pluck('module_id')->unique()->all();
                $allModuleIds = $curriculum->modules->pluck('id')->all();
                $missing = array_diff($allModuleIds, $weightedModuleIds);
                if (!empty($missing)) {
                    $problems[] = sprintf(
                        'Curriculum ID %d: не заданы веса для модулей: %s.',
                        $curriculum->id,
                        implode(', ', $missing)
                    );
                }
            }

            // 3. Work-level: если есть — по каждому модулю сумма = 1
            if ($workWeights->isNotEmpty()) {
                foreach ($workWeights->groupBy('module_id') as $moduleId => $ws) {
                    $sum = (float) $ws->sum('weight_value');
                    if (abs($sum - 1.0) > 0.0001) {
                        $problems[] = sprintf(
                            'Curriculum ID %d, модуль ID %d: сумма весов работ = %.4f, требуется 1.0000.',
                            $curriculum->id,
                            $moduleId,
                            $sum
                        );
                    }
                }
            }
        }

        return $problems;
    }
}
