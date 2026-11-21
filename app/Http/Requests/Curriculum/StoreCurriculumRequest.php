<?php

namespace App\Http\Requests\Curriculum;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'discipline_id' => ['required', 'integer', 'exists:disciplines,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'course_number' => ['required', 'integer', 'min:1', 'max:6'],
            'semester_number' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('curricula', 'semester_number')
                    ->where('discipline_id', $this->input('discipline_id'))
                    ->where('group_id', $this->input('group_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'discipline_id.required' => 'Дисциплина обязательна.',
            'discipline_id.exists' => 'Указанная дисциплина не существует.',
            'group_id.required' => 'Группа обязательна.',
            'group_id.exists' => 'Указанная группа не существует.',
            'course_number.required' => 'Номер курса обязателен.',
            'course_number.min' => 'Номер курса должен быть от 1 до 6.',
            'course_number.max' => 'Номер курса должен быть от 1 до 6.',
            'semester_number.required' => 'Номер семестра обязателен.',
            'semester_number.unique' => 'Эта дисциплина уже есть в плане этой группы в этом семестре.',
        ];
    }
}
