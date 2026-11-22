<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SummaryDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'academic_year' => $this->period->academic_year,
                'semester' => $this->period->semester,
                'status' => $this->period->status,
            ],
            'faculty_id' => $this->faculty_id,
            'stats' => $this->stats,
        ];
    }
}
