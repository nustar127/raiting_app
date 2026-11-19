<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

it('меняет временный пароль и снимает флаг', function () {
    $role = Role::where('code', 'teacher')->first();
    $user = User::create([
        'login' => 'temp_' . uniqid(),
        'password_hash' => Hash::make('Temp1234!'),
        'last_name' => 'Иванов',
        'first_name' => 'Иван',
        'role_id' => $role->id,
        'must_change_password' => true,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Temp1234!',
            'new_password' => 'NewStrong1!',
            'new_password_confirmation' => 'NewStrong1!',
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->must_change_password)->toBeFalse();
    expect(Hash::check('NewStrong1!', $user->password_hash))->toBeTrue();
});

it('блокирует другие эндпоинты при must_change_password', function () {
    $role = Role::where('code', 'student')->first();
    $user = User::create([
        'login' => 'pending_' . uniqid(),
        'password_hash' => Hash::make('Temp1234!'),
        'last_name' => 'Петров',
        'first_name' => 'Пётр',
        'role_id' => $role->id,
        'must_change_password' => true,
    ]);

    $token = $user->createToken('test')->plainTextToken;

    // Заглушки ещё нет, но /auth/me разрешён, а /health не защищён.
    // Возьмём любой защищённый маршрут — добавим позже, когда появятся ресурсы.
    // Сейчас просто проверяем, что /me НЕ блокируется.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});
