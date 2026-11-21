<?php

namespace App\Http\Requests\WorkType;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:work_types,code'],
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
