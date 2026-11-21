<?php

namespace App\Http\Requests\RatingPeriod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRatingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'name' => ['required', 'string', 'max:150'],
            'academic_year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'faculty_id.required' => 'Факультет обязателен.',
            'faculty_id.exists' => 'Указанный факультет не существует.',
            'name.required' => 'Наименование периода обязательно.',
            'academic_year.required' => 'Учебный год обязателен.',
            'academic_year.min' => 'Учебный год должен быть не раньше 2020.',
            'semester.in' => 'Семестр должен быть 1 или 2.',
            'start_date.required' => 'Дата начала обязательна.',
            'end_date.required' => 'Дата окончания обязательна.',
            'end_date.after' => 'Дата окончания должна быть позже даты начала.',
        ];
    }

    /**
     * Дополнительная проверка: нельзя пересекать периоды того же факультета.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $facultyId = $this->input('faculty_id');
            $start = $this->input('start_date');
            $end = $this->input('end_date');

            if (!$facultyId || !$start || !$end) {
                return;
            }

            $overlap = \App\Models\RatingPeriod::where('faculty_id', $facultyId)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                        ->orWhereBetween('end_date', [$start, $end])
                        ->orWhere(function ($q) use ($start, $end) {
                            $q->where('start_date', '<=', $start)
                                ->where('end_date', '>=', $end);
                        });
                })
                ->exists();

            if ($overlap) {
                $v->errors()->add('start_date', 'Период пересекается с уже существующим периодом этого факультета.');
            }
        });
    }
}
