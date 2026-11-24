<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeriodSummaryReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'report_type' => 'period_summary',
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'academic_year' => $this->period->academic_year,
                'semester' => $this->period->semester,
                'status' => $this->period->status,
            ],
            'faculty_id' => $this->faculty_id,
            'totals' => $this->totals,
            'distribution' => $this->distribution,
            'groups' => $this->groups,
            'disciplines' => $this->disciplines,
        ];
    }
}
