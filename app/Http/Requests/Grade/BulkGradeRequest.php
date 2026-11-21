<?php

namespace App\Http\Requests\Grade;

use Illuminate\Foundation\Http\FormRequest;

class BulkGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() || $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'work_type_id' => ['required', 'integer', 'exists:work_types,id'],
            'period_id' => ['required', 'integer', 'exists:rating_periods,id'],
            'date_assigned' => ['required', 'date'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'grades.*.value' => ['required', 'integer', 'min:0', 'max:10'],
            'grades.*.weeks_late' => ['sometimes', 'integer', 'min:0', 'max:52'],
        ];
    }

    public function messages(): array
    {
        return [
            'grades.required' => 'Список оценок обязателен.',
            'grades.min' => 'Нужна хотя бы одна оценка.',
            'grades.*.student_id.exists' => 'Один из студентов не найден.',
            'grades.*.value.min' => 'Оценка должна быть от 0 до 10.',
            'grades.*.value.max' => 'Оценка должна быть от 0 до 10.',
        ];
    }
}
