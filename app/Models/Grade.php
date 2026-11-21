<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $fillable = [
        'student_id',
        'module_id',
        'work_type_id',
        'teacher_id',
        'period_id',
        'value',
        'date_assigned',
        'weeks_late',
    ];

    protected $casts = [
        'value' => 'integer',
        'weeks_late' => 'integer',
        'date_assigned' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class, 'period_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(GradeComment::class);
    }
}
