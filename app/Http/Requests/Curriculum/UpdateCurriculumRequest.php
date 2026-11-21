<?php

namespace App\Http\Requests\Curriculum;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $curriculumId = $this->route('curriculum')?->id;
        $disciplineId = $this->input('discipline_id', $this->route('curriculum')?->discipline_id);
        $groupId = $this->input('group_id', $this->route('curriculum')?->group_id);

        return [
            'discipline_id' => ['sometimes', 'required', 'integer', 'exists:disciplines,id'],
            'group_id' => ['sometimes', 'required', 'integer', 'exists:groups,id'],
            'course_number' => ['sometimes', 'required', 'integer', 'min:1', 'max:6'],
            'semester_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('curricula', 'semester_number')
                    ->where('discipline_id', $disciplineId)
                    ->where('group_id', $groupId)
                    ->ignore($curriculumId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'discipline_id.exists' => 'Указанная дисциплина не существует.',
            'group_id.exists' => 'Указанная группа не существует.',
            'course_number.min' => 'Номер курса должен быть от 1 до 6.',
            'course_number.max' => 'Номер курса должен быть от 1 до 6.',
            'semester_number.unique' => 'Эта дисциплина уже есть в плане этой группы в этом семестре.',
        ];
    }
}
