<?php

namespace App\Http\Requests\DeanEmployee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeanEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'faculty_id' => ['sometimes', 'required', 'integer', 'exists:faculties,id'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'last_name.required' => 'Фамилия обязательна.',
            'first_name.required' => 'Имя обязательно.',
        ];
    }
}
