<?php

namespace App\Http\Requests\Faculty;

use Illuminate\Foundation\Http\FormRequest;

class StoreFacultyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:50', 'unique:faculties,short_name'],
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
