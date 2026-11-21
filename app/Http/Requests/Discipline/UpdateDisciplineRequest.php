<?php

namespace App\Http\Requests\Discipline;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDisciplineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $disciplineId = $this->route('discipline')?->id;

        return [
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('disciplines', 'code')->ignore($disciplineId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.exists' => 'Указанная кафедра не существует.',
            'name.required' => 'Наименование дисциплины обязательно.',
            'code.required' => 'Шифр дисциплины обязателен.',
            'code.unique' => 'Дисциплина с таким шифром уже существует.',
        ];
    }
}
