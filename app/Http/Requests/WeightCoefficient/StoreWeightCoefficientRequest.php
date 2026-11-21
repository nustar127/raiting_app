<?php

namespace App\Http\Requests\WeightCoefficient;

use App\Models\WeightCoefficient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeightCoefficientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', 'exists:rating_periods,id'],
            'curriculum_id' => ['required', 'integer', 'exists:curricula,id'],
            'level' => ['required', 'string', Rule::in([
                WeightCoefficient::LEVEL_DISCIPLINE,
                WeightCoefficient::LEVEL_MODULE,
                WeightCoefficient::LEVEL_WORK,
            ])],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'work_type_id' => ['nullable', 'integer', 'exists:work_types,id'],
            'weight_value' => ['required', 'numeric', 'gt:0', 'max:1'],
            'deadline_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_id.required' => 'Период обязателен.',
            'period_id.exists' => 'Указанный период не существует.',
            'curriculum_id.required' => 'Учебный план обязателен.',
            'curriculum_id.exists' => 'Указанный учебный план не существует.',
            'level.required' => 'Уровень веса обязателен.',
            'level.in' => 'Уровень должен быть discipline, module или work.',
            'weight_value.required' => 'Значение веса обязательно.',
            'weight_value.gt' => 'Вес должен быть больше 0.',
            'weight_value.max' => 'Вес не может превышать 1.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $level = $this->input('level');
            $moduleId = $this->input('module_id');
            $workTypeId = $this->input('work_type_id');

            if ($level === WeightCoefficient::LEVEL_DISCIPLINE) {
                if ($moduleId !== null || $workTypeId !== null) {
                    $v->errors()->add('level', 'Для уровня «дисциплина» module_id и work_type_id должны быть пустыми.');
                }
            }

            if ($level === WeightCoefficient::LEVEL_MODULE) {
                if ($moduleId === null) {
                    $v->errors()->add('module_id', 'Для уровня «модуль» обязателен module_id.');
                }
                if ($workTypeId !== null) {
                    $v->errors()->add('work_type_id', 'Для уровня «модуль» work_type_id должен быть пустым.');
                }
            }

            if ($level === WeightCoefficient::LEVEL_WORK) {
                if ($moduleId === null) {
                    $v->errors()->add('module_id', 'Для уровня «работа» обязателен module_id.');
                }
                if ($workTypeId === null) {
                    $v->errors()->add('work_type_id', 'Для уровня «работа» обязателен work_type_id.');
                }
            }

            // Период должен быть открыт для добавления весов
            $periodId = $this->input('period_id');
            if ($periodId) {
                $period = \App\Models\RatingPeriod::find($periodId);
                if ($period && $period->isClosed()) {
                    $v->errors()->add('period_id', 'Нельзя менять веса закрытого периода.');
                }
            }
        });
    }
}
