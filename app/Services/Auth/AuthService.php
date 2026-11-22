<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Проверяет логин и пароль, возвращает пользователя.
     *
     * @throws ValidationException
     */
    public function attempt(string $login, string $password): User
    {
        /** @var User|null $user */
        $user = User::with('role')->where('login', $login)->first();

        if (!$user || !Hash::check($password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'login' => ['Неверный логин или пароль.'],
            ]);
        }

        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'login' => ['Учётная запись заблокирована.'],
            ]);
        }

        return $user;
    }

    public function changePassword(User $user, string $current, string $new): void
    {
        if (!Hash::check($current, $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => ['Текущий пароль указан неверно.'],
            ]);
        }

        if (Hash::check($new, $user->password_hash)) {
            throw ValidationException::withMessages([
                'new_password' => ['Новый пароль должен отличаться от текущего.'],
            ]);
        }

        $user->password_hash = Hash::make($new);
        $user->must_change_password = false;
        $user->save();

        // Сносим все токены кроме текущего, если нужно форсировать перелогин.
        // Пока оставим простую логику: инвалидируем все токены.
        $user->tokens()->delete();
    }
}
