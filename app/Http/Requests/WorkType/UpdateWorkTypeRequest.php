<?php

namespace App\Http\Requests\WorkType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $id = $this->route('work_type')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('work_types', 'code')->ignore($id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Наименование вида работы обязательно.',
            'code.required' => 'Код вида работы обязателен.',
            'code.unique' => 'Вид работы с таким кодом уже существует.',
        ];
    }
}
