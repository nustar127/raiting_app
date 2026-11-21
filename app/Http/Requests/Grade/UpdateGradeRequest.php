<?php

namespace App\Http\Requests\Grade;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->user()?->isTeacher() && !$this->user()?->isAdmin()) {
            return false;
        }

        $grade = $this->route('grade');

        if (!$grade) {
            return false;
        }

        // Период должен быть открыт
        if ($grade->period?->isClosed()) {
            return false;
        }

        // Преподаватель — только свою оценку
        if ($this->user()->isTeacher()) {
            return $grade->teacher_id === $this->user()->teacher?->id;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'value' => ['sometimes', 'required', 'integer', 'min:0', 'max:10'],
            'date_assigned' => ['sometimes', 'required', 'date'],
            'weeks_late' => ['sometimes', 'integer', 'min:0', 'max:52'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.min' => 'Оценка должна быть от 0 до 10.',
            'value.max' => 'Оценка должна быть от 0 до 10.',
            'weeks_late.min' => 'Недель просрочки не может быть отрицательным.',
        ];
    }
}
