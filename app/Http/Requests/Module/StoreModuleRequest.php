<?php

namespace App\Http\Requests\Module;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'curriculum_id' => ['required', 'integer', 'exists:curricula,id'],
            'name' => ['required', 'string', 'max:100'],
            'order_number' => [
                'required',
                'integer',
                'min:1',
                'max:10',
                Rule::unique('modules', 'order_number')
                    ->where('curriculum_id', $this->input('curriculum_id')),
                function ($attribute, $value, $fail) {
                    // BR-02: не больше 4 модулей на дисциплину
                    $curriculumId = $this->input('curriculum_id');
                    if (!$curriculumId) {
                        return;
                    }
                    $count = Module::where('curriculum_id', $curriculumId)->count();
                    if ($count >= 4) {
                        $fail('На одну дисциплину допускается не более 4 модулей (BR-02).');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'curriculum_id.required' => 'Учебный план обязателен.',
            'curriculum_id.exists' => 'Указанный учебный план не существует.',
            'name.required' => 'Наименование модуля обязательно.',
            'order_number.required' => 'Порядковый номер модуля обязателен.',
            'order_number.unique' => 'Модуль с таким порядковым номером уже есть в этом плане.',
            'order_number.min' => 'Порядковый номер должен быть от 1 до 10.',
            'order_number.max' => 'Порядковый номер должен быть от 1 до 10.',
        ];
    }
}
