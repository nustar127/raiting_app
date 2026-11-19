<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

it('возвращает текущего пользователя', function () {
    $user = createUserWithRole('dean');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.login', $user->login)
        ->assertJsonPath('user.role.code', 'dean');
});

it('возвращает 401 без токена', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

function createUserWithRole(string $roleCode, array $overrides = []): User
{
    $role = Role::where('code', $roleCode)->firstOrFail();

    return User::create(array_merge([
        'login' => 'user_' . uniqid(),
        'password_hash' => Hash::make('Password1!'),
        'last_name' => 'Фамилия',
        'first_name' => 'Имя',
        'role_id' => $role->id,
    ], $overrides));
}
