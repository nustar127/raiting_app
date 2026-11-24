<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    public const TYPE_USERS = 'users';
    public const TYPE_GRADES = 'grades';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'import_type',
        'file_name',
        'file_path',
        'file_size',
        'status',
        'saved_count',
        'failed_count',
        'error_details',
    ];

    protected $casts = [
        'error_details' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
