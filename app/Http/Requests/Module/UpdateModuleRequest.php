<?php

namespace App\Http\Requests\Module;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $moduleId = $this->route('module')?->id;
        $curriculumId = $this->input('curriculum_id', $this->route('module')?->curriculum_id);

        return [
            'curriculum_id' => ['sometimes', 'required', 'integer', 'exists:curricula,id'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'order_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:10',
                Rule::unique('modules', 'order_number')
                    ->where('curriculum_id', $curriculumId)
                    ->ignore($moduleId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'curriculum_id.exists' => 'Указанный учебный план не существует.',
            'name.required' => 'Наименование модуля обязательно.',
            'order_number.unique' => 'Модуль с таким порядковым номером уже есть в этом плане.',
        ];
    }
}
