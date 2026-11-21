<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPELLED = 'expelled';
    public const STATUS_ACADEMIC_LEAVE = 'academic_leave';

    protected $fillable = [
        'user_id',
        'group_id',
        'record_book_number',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
