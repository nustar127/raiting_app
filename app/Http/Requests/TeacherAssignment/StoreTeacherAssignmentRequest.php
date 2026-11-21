<?php

namespace App\Http\Requests\TeacherAssignment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'curriculum_id' => ['required', 'integer', 'exists:curricula,id'],
            'period_id' => ['required', 'integer', 'exists:rating_periods,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Преподаватель обязателен.',
            'teacher_id.exists' => 'Указанный преподаватель не существует.',
            'curriculum_id.required' => 'Учебный план обязателен.',
            'curriculum_id.exists' => 'Указанный учебный план не существует.',
            'period_id.required' => 'Период обязателен.',
            'period_id.exists' => 'Указанный период не существует.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $teacherId = $this->input('teacher_id');
            $curriculumId = $this->input('curriculum_id');
            $periodId = $this->input('period_id');

            if (!$teacherId || !$curriculumId || !$periodId) {
                return;
            }

            // Дубликат
            $exists = \App\Models\TeacherAssignment::where('teacher_id', $teacherId)
                ->where('curriculum_id', $curriculumId)
                ->where('period_id', $periodId)
                ->exists();

            if ($exists) {
                $v->errors()->add('teacher_id', 'Этот преподаватель уже привязан к данной дисциплине в этом периоде.');
            }

            // Период должен принадлежать тому же факультету, что и группа curriculum
            $period = \App\Models\RatingPeriod::find($periodId);
            $curriculum = \App\Models\Curriculum::with('group')->find($curriculumId);

            if ($period && $curriculum && $period->faculty_id !== $curriculum->group->faculty_id) {
                $v->errors()->add('period_id', 'Период относится к другому факультету, чем группа учебного плана.');
            }
        });
    }
}
