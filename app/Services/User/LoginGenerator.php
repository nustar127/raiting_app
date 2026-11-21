<?php

namespace App\Services\User;

use App\Models\User;

class LoginGenerator
{
    /**
     * Логин по номеру зачётки. Если такой уже есть — добавляет суффикс _1, _2...
     */
    public function fromRecordBook(string $recordBook): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($recordBook)) ?: 'student';
        $login = $base;
        $i = 1;

        while (User::where('login', $login)->exists()) {
            $login = $base . '_' . $i;
            $i++;
        }

        return $login;
    }
}
