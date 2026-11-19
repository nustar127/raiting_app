<?php

use App\Models\Faculty;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleGroup(string $roleCode): User
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

function makeFacultyGroup(): Faculty
{
    return Faculty::create([
        'name' => 'Факультет ' . uniqid(),
        'short_name' => 'Ф' . rand(100, 999),
    ]);
}

it('админ создаёт группу и получает current_course', function () {
    $admin = makeUserWithRoleGroup('admin');
    $faculty = makeFacultyGroup();
    $enrollmentYear = (int) date('Y') - 2; // поступили 2 года назад → 3 курс

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/groups', [
            'faculty_id' => $faculty->id,
            'name' => '514371',
            'enrollment_year' => $enrollmentYear,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', '514371')
        ->assertJsonPath('data.current_course', 3);
});

it('не создаёт группу с несуществующим faculty_id', function () {
    $admin = makeUserWithRoleGroup('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/groups', [
            'faculty_id' => 999999,
            'name' => 'X',
            'enrollment_year' => 2024,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['faculty_id']);
});

it('не создаёт группу с дублирующимся name на том же факультете', function () {
    $admin = makeUserWithRoleGroup('admin');
    $faculty = makeFacultyGroup();
    Group::create(['faculty_id' => $faculty->id, 'name' => '514371', 'enrollment_year' => 2023]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/groups', [
            'faculty_id' => $faculty->id,
            'name' => '514371',
            'enrollment_year' => 2024,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('проверяет границы enrollment_year', function () {
    $admin = makeUserWithRoleGroup('admin');
    $faculty = makeFacultyGroup();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/groups', [
            'faculty_id' => $faculty->id,
            'name' => 'X',
            'enrollment_year' => 1999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['enrollment_year']);
});

it('фильтрует по faculty_id и course', function () {
    $student = makeUserWithRoleGroup('student');
    $f1 = makeFacultyGroup();
    $f2 = makeFacultyGroup();
    $thisYear = (int) date('Y');

    Group::create(['faculty_id' => $f1->id, 'name' => '111', 'enrollment_year' => $thisYear - 2]); // 3 курс
    Group::create(['faculty_id' => $f1->id, 'name' => '222', 'enrollment_year' => $thisYear]);     // 1 курс
    Group::create(['faculty_id' => $f2->id, 'name' => '333', 'enrollment_year' => $thisYear - 2]);

    $this->actingAs($student, 'sanctum')
        ->getJson("/api/v1/groups?faculty_id={$f1->id}&course=3")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '111');
});

it('студент видит список групп', function () {
    $student = makeUserWithRoleGroup('student');
    $faculty = makeFacultyGroup();
    Group::create(['faculty_id' => $faculty->id, 'name' => 'A', 'enrollment_year' => 2023]);
    Group::create(['faculty_id' => $faculty->id, 'name' => 'B', 'enrollment_year' => 2023]);

    $this->actingAs($student, 'sanctum')
        ->getJson('/api/v1/groups')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('преподаватель не может создать группу', function () {
    $teacher = makeUserWithRoleGroup('teacher');
    $faculty = makeFacultyGroup();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/groups', [
            'faculty_id' => $faculty->id,
            'name' => 'X',
            'enrollment_year' => 2024,
        ])
        ->assertStatus(403);
});

it('админ обновляет группу', function () {
    $admin = makeUserWithRoleGroup('admin');
    $faculty = makeFacultyGroup();
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => '111', 'enrollment_year' => 2023]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/groups/{$group->id}", ['name' => '222'])
        ->assertOk()
        ->assertJsonPath('data.name', '222');
});

it('админ удаляет группу без студентов', function () {
    $admin = makeUserWithRoleGroup('admin');
    $faculty = makeFacultyGroup();
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => '111', 'enrollment_year' => 2023]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/groups/{$group->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('groups', ['id' => $group->id]);
});

it('возвращает 401 без токена', function () {
    $this->getJson('/api/v1/groups')->assertStatus(401);
});
