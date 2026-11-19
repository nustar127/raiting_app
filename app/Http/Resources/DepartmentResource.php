<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'faculty_id' => $this->faculty_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'faculty' => $this->whenLoaded('faculty', fn() => [
                'id' => $this->faculty->id,
                'short_name' => $this->faculty->short_name,
            ]),
            'teachers_count' => $this->whenCounted('teachers'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
