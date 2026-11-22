<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalRating extends Model
{
    protected $fillable = [
        'student_id',
        'curriculum_id',
        'period_id',
        'calculated_value',
        'is_admitted',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_value' => 'decimal:4',
        'is_admitted' => 'boolean',
        'calculated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class, 'period_id');
    }
}
