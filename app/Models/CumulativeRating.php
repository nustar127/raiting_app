<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CumulativeRating extends Model
{
    protected $fillable = [
        'student_id',
        'period_id',
        'calculated_value',
        'disciplines_count',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_value' => 'decimal:4',
        'disciplines_count' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class, 'period_id');
    }
}
