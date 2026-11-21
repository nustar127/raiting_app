<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'curriculum_id' => $this->curriculum_id,
            'period_id' => $this->period_id,
            'teacher' => $this->whenLoaded('teacher', fn() => [
                'id' => $this->teacher->id,
                'full_name' => trim(
                    "{$this->teacher->user->last_name} {$this->teacher->user->first_name} {$this->teacher->user->middle_name}"
                ),
                'department_id' => $this->teacher->department_id,
            ]),
            'curriculum' => $this->whenLoaded('curriculum', fn() => [
                'id' => $this->curriculum->id,
                'discipline_id' => $this->curriculum->discipline_id,
                'group_id' => $this->curriculum->group_id,
                'course_number' => $this->curriculum->course_number,
                'semester_number' => $this->curriculum->semester_number,
            ]),
            'period' => $this->whenLoaded('period', fn() => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'status' => $this->period->status,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
