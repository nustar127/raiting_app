<?php

namespace App\Http\Requests\WeightCoefficient;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeightCoefficientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'weight_value' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:1'],
            'deadline_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'weight_value.gt' => 'Вес должен быть больше 0.',
            'weight_value.max' => 'Вес не может превышать 1.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $weight = $this->route('weight');
            if ($weight?->isApproved()) {
                $v->errors()->add('weight_value', 'Нельзя менять утверждённый вес.');
            }

            if ($weight?->period?->isClosed()) {
                $v->errors()->add('weight_value', 'Нельзя менять веса закрытого периода.');
            }
        });
    }
}
