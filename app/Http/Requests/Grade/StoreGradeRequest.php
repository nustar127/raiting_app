<?php

namespace App\Http\Requests\Grade;

use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\Student;
use App\Models\WorkType;
use App\Services\Grade\GradeAccessValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() || $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'work_type_id' => ['required', 'integer', 'exists:work_types,id'],
            'period_id' => ['required', 'integer', 'exists:rating_periods,id'],
            'value' => ['required', 'integer', 'min:0', 'max:10'],
            'date_assigned' => ['required', 'date'],
            'weeks_late' => ['sometimes', 'integer', 'min:0', 'max:52'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Студент обязателен.',
            'student_id.exists' => 'Указанный студент не существует.',
            'module_id.required' => 'Модуль обязателен.',
            'module_id.exists' => 'Указанный модуль не существует.',
            'work_type_id.required' => 'Вид работы обязателен.',
            'work_type_id.exists' => 'Указанный вид работы не существует.',
            'period_id.required' => 'Период обязателен.',
            'period_id.exists' => 'Указанный период не существует.',
            'value.required' => 'Оценка обязательна.',
            'value.min' => 'Оценка должна быть от 0 до 10.',
            'value.max' => 'Оценка должна быть от 0 до 10.',
            'date_assigned.required' => 'Дата выставления обязательна.',
            'weeks_late.min' => 'Недель просрочки не может быть отрицательным.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Проверка на дубликат
            $exists = \App\Models\Grade::where('student_id', $this->input('student_id'))
                ->where('module_id', $this->input('module_id'))
                ->where('work_type_id', $this->input('work_type_id'))
                ->where('period_id', $this->input('period_id'))
                ->exists();

            if ($exists) {
                $v->errors()->add('student_id', 'Оценка за эту работу уже выставлена. Используйте PATCH для изменения.');
                return;
            }

            $teacher = $this->user()->teacher;
            if (!$teacher) {
                $v->errors()->add('teacher_id', 'У пользователя нет профиля преподавателя.');
                return;
            }

            try {
                app(GradeAccessValidator::class)->validate(
                    $teacher,
                    Student::findOrFail($this->input('student_id')),
                    Module::findOrFail($this->input('module_id')),
                    WorkType::findOrFail($this->input('work_type_id')),
                    RatingPeriod::findOrFail($this->input('period_id')),
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $msg) {
                        $v->errors()->add($field, $msg);
                    }
                }
            }
        });
    }
}
