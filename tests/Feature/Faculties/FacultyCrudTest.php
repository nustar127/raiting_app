<?php

use App\Models\Faculty;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRole(string $roleCode): User
{
    $role = Role::where('code', $roleCode)->firstOrFail();

    return User::create([
        'login' => 'u_' . uniqid(),
        'password_hash' => Hash::make('Password1!'),
        'last_name' => 'Ф',
        'first_name' => 'И',
        'role_id' => $role->id,
    ]);
}

it('админ создаёт факультет', function () {
    $admin = makeUserWithRole('admin');

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/faculties', [
            'name' => 'Факультет компьютерного проектирования',
            'short_name' => 'ФКП',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Факультет компьютерного проектирования')
        ->assertJsonPath('data.short_name', 'ФКП');

    $this->assertDatabaseHas('faculties', ['short_name' => 'ФКП']);
});

it('не создаёт факультет с дублирующейся аббревиатурой', function () {
    $admin = makeUserWithRole('admin');
    Faculty::create(['name' => 'Старый', 'short_name' => 'ФКП']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/faculties', [
            'name' => 'Новый',
            'short_name' => 'ФКП',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['short_name']);
});

it('не создаёт факультет без обязательных полей', function () {
    $admin = makeUserWithRole('admin');

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/faculties', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'short_name']);
});

it('преподаватель не может создать факультет', function () {
    $teacher = makeUserWithRole('teacher');

    $response = $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/faculties', [
            'name' => 'Новый',
            'short_name' => 'НОВ',
        ]);

    $response->assertStatus(403);
});

it('студент видит список факультетов', function () {
    $student = makeUserWithRole('student');
    Faculty::create(['name' => 'ФКП', 'short_name' => 'ФКП']);
    Faculty::create(['name' => 'ФРЭ', 'short_name' => 'ФРЭ']);

    $response = $this->actingAs($student, 'sanctum')
        ->getJson('/api/v1/faculties');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('поиск по name работает', function () {
    $admin = makeUserWithRole('admin');
    Faculty::create(['name' => 'Факультет компьютерного проектирования', 'short_name' => 'ФКП']);
    Faculty::create(['name' => 'Факультет радиоэлектроники', 'short_name' => 'ФРЭ']);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/faculties?search=компьютерного');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.short_name', 'ФКП');
});

it('сортировка работает', function () {
    $admin = makeUserWithRole('admin');
    Faculty::create(['name' => 'А', 'short_name' => 'ZZZ']);
    Faculty::create(['name' => 'Б', 'short_name' => 'AAA']);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/faculties?sort_by=short_name&sort_dir=asc');

    $response->assertOk()
        ->assertJsonPath('data.0.short_name', 'AAA')
        ->assertJsonPath('data.1.short_name', 'ZZZ');
});

it('админ обновляет факультет', function () {
    $admin = makeUserWithRole('admin');
    $faculty = Faculty::create(['name' => 'Старое', 'short_name' => 'СТ']);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/faculties/{$faculty->id}", [
            'name' => 'Новое',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Новое');

    $this->assertDatabaseHas('faculties', ['id' => $faculty->id, 'name' => 'Новое']);
});

it('админ удаляет факультет', function () {
    $admin = makeUserWithRole('admin');
    $faculty = Faculty::create(['name' => 'Удаляемый', 'short_name' => 'УД']);

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/faculties/{$faculty->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('faculties', ['id' => $faculty->id]);
});

it('не удаляет факультет с кафедрами', function () {
    $admin = makeUserWithRole('admin');
    $faculty = Faculty::create(['name' => 'С кафедрой', 'short_name' => 'СК']);

    \App\Models\Department::create([
        'faculty_id' => $faculty->id,
        'name' => 'ПИКС',
        'short_name' => 'ПИКС',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/faculties/{$faculty->id}");

    $response->assertStatus(409);
    $this->assertDatabaseHas('faculties', ['id' => $faculty->id]);
});

it('возвращает 401 без токена', function () {
    $this->getJson('/api/v1/faculties')->assertStatus(401);
});
