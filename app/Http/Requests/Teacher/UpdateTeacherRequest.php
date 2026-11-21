<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.exists' => 'Указанная кафедра не существует.',
            'last_name.required' => 'Фамилия обязательна.',
            'first_name.required' => 'Имя обязательно.',
        ];
    }
}
