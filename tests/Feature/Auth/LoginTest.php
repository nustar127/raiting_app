<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

it('логинит пользователя с верными данными', function () {
    $role = Role::where('code', 'teacher')->first();
    $user = User::create([
        'login' => 'teacher1',
        'password_hash' => Hash::make('Password1!'),
        'last_name' => 'Иванов',
        'first_name' => 'Иван',
        'role_id' => $role->id,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'teacher1',
        'password' => 'Password1!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'login', 'role']])
        ->assertJsonPath('user.login', 'teacher1')
        ->assertJsonPath('user.role.code', 'teacher');
});

it('не логинит с неверным паролем', function () {
    $role = Role::where('code', 'student')->first();
    User::create([
        'login' => 'student1',
        'password_hash' => Hash::make('Password1!'),
        'last_name' => 'Петров',
        'first_name' => 'Пётр',
        'role_id' => $role->id,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'student1',
        'password' => 'WrongPass1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});

it('не логинит заблокированного пользователя', function () {
    $role = Role::where('code', 'student')->first();
    User::create([
        'login' => 'blocked',
        'password_hash' => Hash::make('Password1!'),
        'last_name' => 'Сидоров',
        'first_name' => 'Сидор',
        'role_id' => $role->id,
        'is_blocked' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'blocked',
        'password' => 'Password1!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});
