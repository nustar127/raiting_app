<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisciplineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'name' => $this->name,
            'code' => $this->code,
            'department' => $this->whenLoaded('department', fn() => [
                'id' => $this->department->id,
                'short_name' => $this->department->short_name,
                'faculty_id' => $this->department->faculty_id,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
