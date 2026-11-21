<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'module_id' => $this->module_id,
            'work_type_id' => $this->work_type_id,
            'teacher_id' => $this->teacher_id,
            'period_id' => $this->period_id,
            'value' => $this->value,
            'date_assigned' => $this->date_assigned?->toDateString(),
            'weeks_late' => $this->weeks_late,
            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'full_name' => trim(
                    "{$this->student->user->last_name} {$this->student->user->first_name} {$this->student->user->middle_name}"
                ),
                'record_book_number' => $this->student->record_book_number,
                'group_id' => $this->student->group_id,
            ]),
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
            'teacher' => $this->whenLoaded('teacher', fn() => [
                'id' => $this->teacher->id,
                'full_name' => trim(
                    "{$this->teacher->user->last_name} {$this->teacher->user->first_name} {$this->teacher->user->middle_name}"
                ),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
