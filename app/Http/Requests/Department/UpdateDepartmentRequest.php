<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('department')?->id;
        $facultyId = $this->input('faculty_id', $this->route('department')?->faculty_id);

        return [
            'faculty_id' => ['sometimes', 'required', 'integer', 'exists:faculties,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'short_name')
                    ->where('faculty_id', $facultyId)
                    ->ignore($departmentId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'name.required' => 'Наименование кафедры обязательно.',
            'short_name.required' => 'Аббревиатура обязательна.',
            'short_name.unique' => 'Кафедра с такой аббревиатурой уже есть на этом факультете.',
        ];
    }
}
