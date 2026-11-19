<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'regex:/[A-Za-z]/',      // хотя бы одна буква
                'regex:/[0-9]/',         // хотя бы одна цифра
                'regex:/[^A-Za-z0-9]/',  // хотя бы один спецсимвол
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.min' => 'Пароль должен быть не короче 8 символов.',
            'new_password.regex' => 'Пароль должен содержать буквы, цифры и спецсимвол.',
            'new_password.confirmed' => 'Пароли не совпадают.',
            'current_password.required' => 'Укажите текущий пароль.',
        ];
    }
}
