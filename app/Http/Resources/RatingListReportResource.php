<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingListReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'report_type' => 'rating_list',
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'id' => $this->period->id,
                'name' => $this->period->name,
                'academic_year' => $this->period->academic_year,
                'semester' => $this->period->semester,
            ],
            'filters' => $this->filters,
            'items' => $this->items,
            'pagination' => $this->pagination,
        ];
    }
}
