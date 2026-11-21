<?php

namespace App\Http\Requests\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id;

        return [
            'group_id' => ['sometimes', 'required', 'integer', 'exists:groups,id'],
            'record_book_number' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('students', 'record_book_number')->ignore($studentId),
            ],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in([
                Student::STATUS_ACTIVE,
                Student::STATUS_EXPELLED,
                Student::STATUS_ACADEMIC_LEAVE,
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'group_id.exists' => 'Указанная группа не существует.',
            'record_book_number.unique' => 'Студент с таким номером зачётки уже существует.',
            'last_name.required' => 'Фамилия обязательна.',
            'first_name.required' => 'Имя обязательно.',
            'status.in' => 'Недопустимый статус студента.',
        ];
    }
}
