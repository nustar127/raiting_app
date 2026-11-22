<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'module_id' => $this->module_id,
            'period_id' => $this->period_id,
            'calculated_value' => (float) $this->calculated_value,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
        ];
    }
}
