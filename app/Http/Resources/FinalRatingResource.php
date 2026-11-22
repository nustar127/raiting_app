<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinalRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'curriculum_id' => $this->curriculum_id,
            'period_id' => $this->period_id,
            'calculated_value' => (float) $this->calculated_value,
            'is_admitted' => $this->is_admitted,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
        ];
    }
}
