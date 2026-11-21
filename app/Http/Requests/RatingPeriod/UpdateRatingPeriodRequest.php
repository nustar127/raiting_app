<?php

namespace App\Http\Requests\RatingPeriod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRatingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $periodId = $this->route('period')?->id ?? $this->route('rating_period')?->id;

        return [
            'faculty_id' => ['sometimes', 'required', 'integer', 'exists:faculties,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'academic_year' => ['sometimes', 'required', 'integer', 'min:2020', 'max:2050'],
            'semester' => ['sometimes', 'required', 'integer', 'in:1,2'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'name.required' => 'Наименование периода обязательно.',
            'semester.in' => 'Семестр должен быть 1 или 2.',
            'end_date.after' => 'Дата окончания должна быть позже даты начала.',
        ];
    }
}
