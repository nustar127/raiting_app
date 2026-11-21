<?php

namespace App\Services\Grade;

use App\Models\Grade;
use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\WorkType;
use Illuminate\Validation\ValidationException;

class GradeAccessValidator
{
    /**
     * Проверить, что преподаватель имеет право выставить оценку.
     *
     * @throws ValidationException
     */
    public function validate(
        Teacher $teacher,
        Student $student,
        Module $module,
        WorkType $workType,
        RatingPeriod $period,
    ): void {
        $errors = [];

        // 1. Период открыт
        if (!$period->isOpen()) {
            $errors['period_id'] = 'Период закрыт — изменение оценок запрещено.';
        }

        // 2. Привязка teacher + curriculum + period
        $assignment = TeacherAssignment::where('teacher_id', $teacher->id)
            ->where('curriculum_id', $module->curriculum_id)
            ->where('period_id', $period->id)
            ->first();

        if (!$assignment) {
            $errors['teacher_id'] = 'Преподаватель не привязан к этой дисциплине в этом периоде.';
        }

        // 3. Студент в группе из curriculum
        $curriculum = $module->curriculum;
        if ($curriculum && $student->group_id !== $curriculum->group_id) {
            $errors['student_id'] = 'Студент не входит в группу, для которой читается эта дисциплина.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
