<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = ['curriculum_id', 'name', 'order_number'];

    protected $casts = [
        'order_number' => 'integer',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function weightCoefficients(): HasMany
    {
        return $this->hasMany(WeightCoefficient::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function moduleRatings(): HasMany
    {
        return $this->hasMany(ModuleRating::class);
    }
}
