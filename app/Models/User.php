<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function deanEmployee(): HasOne
    {
        return $this->hasOne(DeanEmployee::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->code === 'admin';
    }

    public function isDean(): bool
    {
        return $this->role?->code === 'dean';
    }

    public function isTeacher(): bool
    {
        return $this->role?->code === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role?->code === 'student';
    }
}
