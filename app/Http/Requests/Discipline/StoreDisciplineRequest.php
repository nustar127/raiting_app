<?php

namespace App\Http\Requests\Discipline;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisciplineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:disciplines,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.required' => 'Кафедра обязательна.',
            'department_id.exists' => 'Указанная кафедра не существует.',
            'name.required' => 'Наименование дисциплины обязательно.',
            'code.required' => 'Шифр дисциплины обязателен.',
            'code.unique' => 'Дисциплина с таким шифром уже существует.',
        ];
    }
}
