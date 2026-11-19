<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $groupId = $this->route('group')?->id;
        $facultyId = $this->input('faculty_id', $this->route('group')?->faculty_id);

        return [
            'faculty_id' => ['sometimes', 'required', 'integer', 'exists:faculties,id'],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('groups', 'name')
                    ->where('faculty_id', $facultyId)
                    ->ignore($groupId),
            ],
            'enrollment_year' => ['sometimes', 'required', 'integer', 'min:2000', 'max:' . ((int) date('Y') + 1)],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'name.required' => 'Номер группы обязателен.',
            'name.unique' => 'Группа с таким номером уже есть на этом факультете.',
            'enrollment_year.min' => 'Год поступления не может быть раньше 2000.',
        ];
    }
}
