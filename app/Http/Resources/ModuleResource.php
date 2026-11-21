<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'curriculum_id' => $this->curriculum_id,
            'name' => $this->name,
            'order_number' => $this->order_number,
            'curriculum' => $this->whenLoaded('curriculum', fn() => [
                'id' => $this->curriculum->id,
                'discipline_id' => $this->curriculum->discipline_id,
                'group_id' => $this->curriculum->group_id,
                'course_number' => $this->curriculum->course_number,
                'semester_number' => $this->curriculum->semester_number,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
