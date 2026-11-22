<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentRatingCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student' => [
                'id' => $this->student->id,
                'full_name' => trim(
                    "{$this->student->user->last_name} {$this->student->user->first_name} {$this->student->user->middle_name}"
                ),
                'record_book_number' => $this->student->record_book_number,
                'group' => [
                    'id' => $this->student->group->id,
                    'name' => $this->student->group->name,
                    'current_course' => $this->student->group->current_course,
                ],
            ],
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'academic_year' => $this->period->academic_year,
                'semester' => $this->period->semester,
                'status' => $this->period->status,
            ],
            'cumulative_rating' => $this->cumulative
                ? (float) $this->cumulative->calculated_value
                : null,
            'disciplines_count' => $this->cumulative?->disciplines_count ?? 0,
            'is_admitted_anywhere' => $this->finals->every(fn($f) => $f->is_admitted),
            'places' => $this->places,
            'disciplines' => $this->finals->map(fn($f) => [
                'curriculum_id' => $f->curriculum_id,
                'discipline_name' => $f->curriculum->discipline->name,
                'discipline_code' => $f->curriculum->discipline->code,
                'final_rating' => (float) $f->calculated_value,
                'is_admitted' => $f->is_admitted,
            ])->values(),
            'modules' => $this->modules->map(fn($mr) => [
                'module_id' => $mr->module_id,
                'module_name' => $mr->module->name,
                'order_number' => $mr->module->order_number,
                'module_rating' => (float) $mr->calculated_value,
            ])->values(),
        ];
    }
}
