<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCardReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $student = $this->student;

        return [
            'report_type' => 'student_card',
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'academic_year' => $this->period->academic_year,
                'semester' => $this->period->semester,
            ],
            'student' => [
                'id' => $student->id,
                'full_name' => trim(
                    "{$student->user->last_name} {$student->user->first_name} {$student->user->middle_name}"
                ),
                'record_book_number' => $student->record_book_number,
                'group' => [
                    'id' => $student->group->id,
                    'name' => $student->group->name,
                    'current_course' => $student->group->current_course,
                ],
                'faculty' => [
                    'id' => $student->group->faculty->id,
                    'short_name' => $student->group->faculty->short_name,
                ],
            ],
            'cumulative_rating' => $this->cumulative
                ? round((float) $this->cumulative->calculated_value, 2)
                : null,
            'disciplines_count' => $this->cumulative?->disciplines_count ?? 0,
            'places' => $this->places,
            'disciplines' => $this->finals->map(function ($final) {
                $currId = $final->curriculum_id;
                $modules = $this->moduleRatings->get($currId) ?? collect();

                return [
                    'curriculum_id' => $currId,
                    'discipline_name' => $final->curriculum->discipline->name,
                    'discipline_code' => $final->curriculum->discipline->code,
                    'final_rating' => round((float) $final->calculated_value, 2),
                    'is_admitted' => $final->is_admitted,
                    'modules' => $modules->map(fn($mr) => [
                        'module_id' => $mr->module_id,
                        'module_name' => $mr->module->name,
                        'order_number' => $mr->module->order_number,
                        'module_rating' => round((float) $mr->calculated_value, 2),
                    ])->values()->all(),
                ];
            })->values()->all(),
            'grades' => $this->grades->map(fn($g) => [
                'id' => $g->id,
                'module_name' => $g->module->name,
                'work_type_name' => $g->workType->name,
                'value' => $g->value,
                'weeks_late' => $g->weeks_late,
                'date_assigned' => $g->date_assigned?->toDateString(),
                'teacher_full_name' => trim(
                    "{$g->teacher->user->last_name} {$g->teacher->user->first_name} {$g->teacher->user->middle_name}"
                ),
            ])->values()->all(),
        ];
    }
}
