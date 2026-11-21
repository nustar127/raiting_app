<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightCoefficient extends Model
{
    public const LEVEL_DISCIPLINE = 'discipline';
    public const LEVEL_MODULE = 'module';
    public const LEVEL_WORK = 'work';

    protected $fillable = [
        'period_id',
        'curriculum_id',
        'level',
        'module_id',
        'work_type_id',
        'weight_value',
        'deadline_date',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'weight_value' => 'decimal:4',
        'deadline_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class, 'period_id');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }
}
