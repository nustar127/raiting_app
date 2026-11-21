<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleTh(string $roleCode): User
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

function makeDepartmentTh(): Department
{
    $faculty = Faculty::create([
        'name' => 'Факультет ' . uniqid(),
        'short_name' => 'Ф' . rand(100, 999),
    ]);

    return Department::create([
        'faculty_id' => $faculty->id,
        'name' => 'Кафедра ' . uniqid(),
        'short_name' => 'К' . rand(100, 999),
    ]);
}

it('админ создаёт преподавателя и возвращает credentials', function () {
    $admin = makeUserWithRoleTh('admin');
    $dept = makeDepartmentTh();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/teachers', [
            'department_id' => $dept->id,
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'middle_name' => 'Иванович',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.last_name', 'Иванов')
        ->assertJsonPath('data.department_id', $dept->id)
        ->assertJsonStructure(['credentials' => ['login', 'temporary_password']]);

    expect($response->json('credentials.login'))->toBe('ivanov_ii');

    $user = User::where('login', 'ivanov_ii')->first();
    expect($user->must_change_password)->toBeTrue();
    expect($user->role->code)->toBe('teacher');
});

it('генерирует уникальный логин при совпадении ФИО', function () {
    $admin = makeUserWithRoleTh('admin');
    $dept = makeDepartmentTh();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/teachers', [
            'department_id' => $dept->id,
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'middle_name' => 'Иванович',
        ])
        ->assertCreated()
        ->assertJsonPath('credentials.login', 'ivanov_ii');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/teachers', [
            'department_id' => $dept->id,
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'middle_name' => 'Иванович',
        ])
        ->assertCreated()
        ->assertJsonPath('credentials.login', 'ivanov_ii_1');
});

it('не создаёт преподавателя с несуществующим department_id', function () {
    $admin = makeUserWithRoleTh('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/teachers', [
            'department_id' => 999999,
            'last_name' => 'A',
            'first_name' => 'B',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['department_id']);
});

it('декан видит только преподавателей своего факультета', function () {
    $admin = makeUserWithRoleTh('admin');
    $d1 = makeDepartmentTh();
    $d2 = makeDepartmentTh();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $d1->id,
        'last_name' => 'Первый',
        'first_name' => 'П',
    ])->assertCreated();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $d2->id,
        'last_name' => 'Второй',
        'first_name' => 'В',
    ])->assertCreated();

    $deanUser = makeUserWithRoleTh('dean');
    \App\Models\DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $d1->faculty_id]);

    $this->actingAs($deanUser, 'sanctum')
        ->getJson('/api/v1/teachers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.last_name', 'Первый');
});

it('преподаватель видит только свой профиль', function () {
    $admin = makeUserWithRoleTh('admin');
    $d1 = makeDepartmentTh();
    $d2 = makeDepartmentTh();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $d1->id,
        'last_name' => 'Первый',
        'first_name' => 'П',
    ])->assertCreated();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $d2->id,
        'last_name' => 'Второй',
        'first_name' => 'В',
    ])->assertCreated();

    $teacher1 = Teacher::whereHas('user', fn($q) => $q->where('last_name', 'Первый'))->first();
    $teacher2 = Teacher::whereHas('user', fn($q) => $q->where('last_name', 'Второй'))->first();

    $teacherUser1 = $teacher1->user;

    $this->actingAs($teacherUser1, 'sanctum')
        ->getJson("/api/v1/teachers/{$teacher1->id}")
        ->assertOk();

    $this->actingAs($teacherUser1, 'sanctum')
        ->getJson("/api/v1/teachers/{$teacher2->id}")
        ->assertStatus(403);
});

it('админ обновляет ФИО преподавателя', function () {
    $admin = makeUserWithRoleTh('admin');
    $dept = makeDepartmentTh();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $dept->id,
        'last_name' => 'Старое',
        'first_name' => 'Имя',
    ])->assertCreated();

    $teacher = Teacher::whereHas('user', fn($q) => $q->where('last_name', 'Старое'))->first();

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/teachers/{$teacher->id}", ['last_name' => 'Новое'])
        ->assertOk()
        ->assertJsonPath('data.last_name', 'Новое');
});

it('админ удаляет преподавателя вместе с user', function () {
    $admin = makeUserWithRoleTh('admin');
    $dept = makeDepartmentTh();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $dept->id,
        'last_name' => 'Удал',
        'first_name' => 'У',
    ])->assertCreated();

    $teacher = Teacher::whereHas('user', fn($q) => $q->where('last_name', 'Удал'))->first();
    $userId = $teacher->user_id;

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/teachers/{$teacher->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    $this->assertDatabaseMissing('users', ['id' => $userId]);
});

it('поиск по ФИО работает', function () {
    $admin = makeUserWithRoleTh('admin');
    $dept = makeDepartmentTh();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $dept->id,
        'last_name' => 'Сидоров',
        'first_name' => 'С',
    ]);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teachers', [
        'department_id' => $dept->id,
        'last_name' => 'Петров',
        'first_name' => 'П',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/teachers?search=сидоров')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('студент не может создать преподавателя', function () {
    $student = makeUserWithRoleTh('student');
    $dept = makeDepartmentTh();

    $this->actingAs($student, 'sanctum')
        ->postJson('/api/v1/teachers', [
            'department_id' => $dept->id,
            'last_name' => 'A',
            'first_name' => 'B',
        ])
        ->assertStatus(403);
});
