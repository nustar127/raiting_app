<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => [
                'required',
                'string',
                'max:50',
                // уникальность short_name в рамках факультета
                \Illuminate\Validation\Rule::unique('departments', 'short_name')
                    ->where('faculty_id', $this->input('faculty_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.required' => 'Факультет обязателен.',
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'name.required' => 'Наименование кафедры обязательно.',
            'short_name.required' => 'Аббревиатура обязательна.',
            'short_name.unique' => 'Кафедра с такой аббревиатурой уже есть на этом факультете.',
        ];
    }
}
