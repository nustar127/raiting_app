<?php

namespace App\Services\User;

use App\Models\User;

class TranslitLoginGenerator
{
    private array $map = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
    ];

    public function fromFullName(string $lastName, string $firstName, ?string $middleName = null): string
    {
        $last = $this->translit($lastName);
        $firstInitial = mb_substr($this->translit($firstName), 0, 1);
        $middleInitial = $middleName ? mb_substr($this->translit($middleName), 0, 1) : '';

        $base = $last . '_' . $firstInitial . $middleInitial;
        $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'teacher';

        $login = $base;
        $i = 1;
        while (User::where('login', $login)->exists()) {
            $login = $base . '_' . $i;
            $i++;
        }

        return $login;
    }

    private function translit(string $text): string
    {
        $text = mb_strtolower(trim($text));
        return strtr($text, $this->map);
    }
}
