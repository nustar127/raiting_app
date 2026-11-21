<?php

namespace App\Http\Requests\DeanEmployee;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeanEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.required' => 'Факультет обязателен.',
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'last_name.required' => 'Фамилия обязательна.',
            'first_name.required' => 'Имя обязательно.',
        ];
    }
}
