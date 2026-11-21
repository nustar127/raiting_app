<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discipline_id' => $this->discipline_id,
            'group_id' => $this->group_id,
            'course_number' => $this->course_number,
            'semester_number' => $this->semester_number,
            'discipline' => $this->whenLoaded('discipline', fn() => [
                'id' => $this->discipline->id,
                'name' => $this->discipline->name,
                'code' => $this->discipline->code,
                'department_id' => $this->discipline->department_id,
            ]),
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'current_course' => $this->group->current_course,
                'faculty_id' => $this->group->faculty_id,
            ]),
            'modules_count' => $this->whenCounted('modules'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
