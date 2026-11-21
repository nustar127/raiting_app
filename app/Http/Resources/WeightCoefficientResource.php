<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeightCoefficientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_id' => $this->period_id,
            'curriculum_id' => $this->curriculum_id,
            'level' => $this->level,
            'module_id' => $this->module_id,
            'work_type_id' => $this->work_type_id,
            'weight_value' => (float) $this->weight_value,
            'deadline_date' => $this->deadline_date?->toDateString(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'is_approved' => $this->isApproved(),
            'module' => $this->whenLoaded('module', fn() => [
                'id' => $this->module->id,
                'name' => $this->module->name,
                'order_number' => $this->module->order_number,
            ]),
            'work_type' => $this->whenLoaded('workType', fn() => [
                'id' => $this->workType->id,
                'name' => $this->workType->name,
                'code' => $this->workType->code,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
