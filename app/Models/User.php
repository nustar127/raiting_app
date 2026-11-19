<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'login',
        'password_hash',
        'last_name',
        'first_name',
        'middle_name',
        'role_id',
        'is_blocked',
        'must_change_password',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Переопределяем стандартное поле пароля Laravel
     * (по умолчанию 'password', у нас 'password_hash').
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function hasRole(string $code): bool
    {
        return $this->role?->code === $code;
    }
}
