<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group' => [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'current_course' => $this->group->current_course,
                'faculty_id' => $this->group->faculty_id,
            ],
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'academic_year' => $this->period->academic_year,
                'semester' => $this->period->semester,
                'status' => $this->period->status,
            ],
            'stats' => $this->stats,
            'disciplines' => $this->disciplines,
            'top_students' => $this->top_students,
        ];
    }
}
