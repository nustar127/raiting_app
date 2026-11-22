<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleRating extends Model
{
    protected $fillable = [
        'student_id',
        'module_id',
        'period_id',
        'calculated_value',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_value' => 'decimal:4',
        'calculated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class, 'period_id');
    }
}
