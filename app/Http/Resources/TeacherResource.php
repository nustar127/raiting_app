<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'department_id' => $this->department_id,
            'last_name' => $this->whenLoaded('user', fn() => $this->user->last_name),
            'first_name' => $this->whenLoaded('user', fn() => $this->user->first_name),
            'middle_name' => $this->whenLoaded('user', fn() => $this->user->middle_name),
            'full_name' => $this->whenLoaded('user', fn() => trim(
                "{$this->user->last_name} {$this->user->first_name} {$this->user->middle_name}"
            )),
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
