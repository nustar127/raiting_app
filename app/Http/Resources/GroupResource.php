<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'faculty_id' => $this->faculty_id,
            'name' => $this->name,
            'enrollment_year' => $this->enrollment_year,
            'current_course' => $this->current_course,
            'faculty' => $this->whenLoaded('faculty', fn() => [
                'id' => $this->faculty->id,
                'short_name' => $this->faculty->short_name,
            ]),
            'students_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
