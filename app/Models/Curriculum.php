<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    protected $fillable = [
        'discipline_id',
        'group_id',
        'course_number',
        'semester_number',
    ];

    protected $casts = [
        'course_number' => 'integer',
        'semester_number' => 'integer',
    ];

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }
}
