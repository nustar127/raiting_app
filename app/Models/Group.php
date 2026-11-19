<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = ['faculty_id', 'name', 'enrollment_year'];

    protected $casts = [
        'enrollment_year' => 'integer',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Текущий курс группы: год_сейчас − год_поступления + 1.
     * Если группа ещё не набрана (год > текущего) — 0.
     */
    public function getCurrentCourseAttribute(): int
    {
        $currentYear = (int) date('Y');
        if ($this->enrollment_year > $currentYear) {
            return 0;
        }
        return $currentYear - $this->enrollment_year + 1;
    }
}
