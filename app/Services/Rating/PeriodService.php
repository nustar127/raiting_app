<?php

namespace App\Services\Rating;

use App\Models\Curriculum;
use App\Models\Module;
use App\Models\RatingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodService
{
    /**
     * Открыть период. Разрешает ввод оценок.
     *
     * @throws ValidationException
     */
    public function open(RatingPeriod $period): RatingPeriod
    {
        if ($period->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['Период уже открыт.'],
            ]);
        }

        $period->update(['status' => RatingPeriod::STATUS_OPEN]);

        return $period->fresh();
    }

    /**
     * Закрыть период. Блокирует изменение оценок.
     *
     * Проверяет BR-02: в каждом учебном плане факультета, у которого
     * есть модули, должно быть 2–4 модуля.
     *
     * @throws ValidationException
     */
    public function close(RatingPeriod $period): RatingPeriod
    {
        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'status' => ['Период уже закрыт.'],
            ]);
        }

        $problems = $this->validateModulesCount($period);
        if (!empty($problems)) {
            throw ValidationException::withMessages([
                'modules' => $problems,
            ]);
        }

        $period->update(['status' => RatingPeriod::STATUS_CLOSED]);

        return $period->fresh();
    }

    /**
     * @return array<string> Список человекочитаемых проблемных дисциплин
     */
    private function validateModulesCount(RatingPeriod $period): array
    {
        $curricula = Curriculum::query()
            ->whereHas('group', fn($q) => $q->where('faculty_id', $period->faculty_id))
            ->withCount('modules')
            ->with('discipline')
            ->get();

        $problems = [];

        foreach ($curricula as $curriculum) {
            $count = $curriculum->modules_count;

            // Если модулей нет вовсе — дисциплина могла быть не задействована в этом периоде.
            // Считаем это нормой, не блокируем.
            if ($count === 0) {
                continue;
            }

            if ($count < 2 || $count > 4) {
                $problems[] = sprintf(
                    'Дисциплина "%s" (план ID %d): модулей %d, требуется от 2 до 4.',
                    $curriculum->discipline?->name ?? '—',
                    $curriculum->id,
                    $count
                );
            }
        }

        return $problems;
    }
}
