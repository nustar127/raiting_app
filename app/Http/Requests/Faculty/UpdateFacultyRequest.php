<?php

namespace App\Http\Requests\Faculty;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacultyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $facultyId = $this->route('faculty')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('faculties', 'short_name')->ignore($facultyId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Полное наименование обязательно.',
            'short_name.required' => 'Аббревиатура обязательна.',
            'short_name.unique' => 'Факультет с такой аббревиатурой уже существует.',
        ];
    }
}
