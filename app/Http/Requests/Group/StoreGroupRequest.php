<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('groups', 'name')->where('faculty_id', $this->input('faculty_id')),
            ],
            'enrollment_year' => ['required', 'integer', 'min:2000', 'max:' . ((int) date('Y') + 1)],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.required' => 'Факультет обязателен.',
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'name.required' => 'Номер группы обязателен.',
            'name.unique' => 'Группа с таким номером уже есть на этом факультете.',
            'enrollment_year.required' => 'Год поступления обязателен.',
            'enrollment_year.min' => 'Год поступления не может быть раньше 2000.',
            'enrollment_year.max' => 'Год поступления не может быть позже следующего года.',
        ];
    }
}
