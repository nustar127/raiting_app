<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacultyDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'faculty' => [
                'id' => $this->faculty->id,
                'name' => $this->faculty->name,
                'short_name' => $this->faculty->short_name,
            ],
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'academic_year' => $this->period->academic_year,
                'semester' => $this->period->semester,
                'status' => $this->period->status,
            ],
            'stats' => $this->stats,
            'groups' => $this->groups,
        ];
    }
}
