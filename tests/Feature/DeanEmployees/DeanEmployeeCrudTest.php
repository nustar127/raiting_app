<?php

use App\Models\DeanEmployee;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleDE(string $roleCode): User
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

function makeFacultyDE(): Faculty
{
    return Faculty::create([
        'name' => 'Факультет ' . uniqid(),
        'short_name' => 'Ф' . rand(100, 999),
    ]);
}

it('админ создаёт сотрудника деканата и возвращает credentials', function () {
    $admin = makeUserWithRoleDE('admin');
    $faculty = makeFacultyDE();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/dean-employees', [
            'faculty_id' => $faculty->id,
            'last_name' => 'Петров',
            'first_name' => 'Пётр',
            'middle_name' => 'Петрович',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.last_name', 'Петров')
        ->assertJsonPath('data.faculty_id', $faculty->id)
        ->assertJsonStructure(['credentials' => ['login', 'temporary_password']]);

    $user = User::where('login', $response->json('credentials.login'))->first();
    expect($user->must_change_password)->toBeTrue();
    expect($user->role->code)->toBe('dean');
});

it('не создаёт сотрудника с несуществующим faculty_id', function () {
    $admin = makeUserWithRoleDE('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/dean-employees', [
            'faculty_id' => 999999,
            'last_name' => 'A',
            'first_name' => 'B',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['faculty_id']);
});

it('декан видит только сотрудников своего факультета', function () {
    $admin = makeUserWithRole('admin');
    $f1 = makeFaculty();
    $f2 = makeFaculty();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/dean-employees', [
        'faculty_id' => $f1->id,
        'last_name' => 'Первый',
        'first_name' => 'П',
    ])->assertCreated();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/dean-employees', [
        'faculty_id' => $f2->id,
        'last_name' => 'Второй',
        'first_name' => 'В',
    ])->assertCreated();

    $deanUser = makeUserWithRole('dean');
    DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $f1->id]);

    // На f1 — «Первый» и сам декан. «Второй» на f2 не виден.
    $this->actingAs($deanUser, 'sanctum')
        ->getJson('/api/v1/dean-employees')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // И «Второго» точно нет
    $response = $this->actingAs($deanUser, 'sanctum')
        ->getJson('/api/v1/dean-employees');
    $lastNames = collect($response->json('data'))->pluck('last_name')->all();
    expect($lastNames)->toContain('Первый');
    expect($lastNames)->not->toContain('Второй');
});

it('преподаватель не может создать сотрудника деканата', function () {
    $teacher = makeUserWithRoleDE('teacher');
    $faculty = makeFacultyDE();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/dean-employees', [
            'faculty_id' => $faculty->id,
            'last_name' => 'A',
            'first_name' => 'B',
        ])
        ->assertStatus(403);
});

it('админ обновляет faculty_id сотрудника', function () {
    $admin = makeUserWithRoleDE('admin');
    $f1 = makeFacultyDE();
    $f2 = makeFacultyDE();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/dean-employees', [
        'faculty_id' => $f1->id,
        'last_name' => 'Иванов',
        'first_name' => 'И',
    ])->assertCreated();

    $dean = DeanEmployee::whereHas('user', fn($q) => $q->where('last_name', 'Иванов'))->first();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/dean-employees/{$dean->id}", ['faculty_id' => $f2->id])
        ->assertOk()
        ->assertJsonPath('data.faculty_id', $f2->id);
});

it('админ удаляет сотрудника вместе с user', function () {
    $admin = makeUserWithRoleDE('admin');
    $faculty = makeFacultyDE();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/dean-employees', [
        'faculty_id' => $faculty->id,
        'last_name' => 'Удал',
        'first_name' => 'У',
    ])->assertCreated();

    $dean = DeanEmployee::whereHas('user', fn($q) => $q->where('last_name', 'Удал'))->first();
    $userId = $dean->user_id;

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/dean-employees/{$dean->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('dean_employees', ['id' => $dean->id]);
    $this->assertDatabaseMissing('users', ['id' => $userId]);
});

it('поиск по ФИО работает', function () {
    $admin = makeUserWithRoleDE('admin');
    $faculty = makeFacultyDE();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/dean-employees', [
        'faculty_id' => $faculty->id,
        'last_name' => 'Сидоров',
        'first_name' => 'С',
    ]);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/dean-employees', [
        'faculty_id' => $faculty->id,
        'last_name' => 'Петров',
        'first_name' => 'П',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/dean-employees?search=сидоров')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
