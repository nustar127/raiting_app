<?php

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Discipline;
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

function makeUserWithRoleС(string $roleCode): User
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

function makeDisciplineС(): Discipline
{
    $faculty = Faculty::create(['name' => 'Ф' . uniqid(), 'short_name' => 'Ф' . rand(100, 999)]);
    $dept = Department::create([
        'faculty_id' => $faculty->id,
        'name' => 'К' . uniqid(),
        'short_name' => 'К' . rand(100, 999),
    ]);

    return Discipline::create([
        'department_id' => $dept->id,
        'name' => 'Дисциплина ' . uniqid(),
        'code' => 'D' . rand(1000, 9999),
    ]);
}

function makeGroupС(?Faculty $faculty = null): Group
{
    $faculty ??= Faculty::create([
        'name' => 'Ф' . uniqid(),
        'short_name' => 'Ф' . rand(100, 999),
    ]);

    return Group::create([
        'faculty_id' => $faculty->id,
        'name' => 'G' . rand(1000, 9999),
        'enrollment_year' => (int) date('Y') - 1,
    ]);
}

it('админ создаёт запись учебного плана', function () {
    $admin = makeUserWithRoleС('admin');
    $disc = makeDisciplineС();
    $group = makeGroupС();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/curricula', [
            'discipline_id' => $disc->id,
            'group_id' => $group->id,
            'course_number' => 2,
            'semester_number' => 4,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.discipline_id', $disc->id)
        ->assertJsonPath('data.course_number', 2)
        ->assertJsonPath('data.semester_number', 4);
});

it('не создаёт дубль по (discipline, group, semester)', function () {
    $admin = makeUserWithRoleС('admin');
    $disc = makeDisciplineС();
    $group = makeGroupС();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/curricula', [
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 4,
    ])->assertCreated();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/curricula', [
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 4,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['semester_number']);
});

it('не создаёт с несуществующими discipline_id или group_id', function () {
    $admin = makeUserWithRoleС('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/curricula', [
            'discipline_id' => 999999,
            'group_id' => 999999,
            'course_number' => 1,
            'semester_number' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['discipline_id', 'group_id']);
});

it('проверяет диапазон course_number', function () {
    $admin = makeUserWithRoleС('admin');
    $disc = makeDisciplineС();
    $group = makeGroupС();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/curricula', [
            'discipline_id' => $disc->id,
            'group_id' => $group->id,
            'course_number' => 7,
            'semester_number' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['course_number']);
});

it('фильтрует по group_id и discipline_id', function () {
    $admin = makeUserWithRoleС('admin');
    $d1 = makeDisciplineС();
    $d2 = makeDisciplineС();
    $g1 = makeGroupС();
    $g2 = makeGroupС();

    Curriculum::create(['discipline_id' => $d1->id, 'group_id' => $g1->id, 'course_number' => 1, 'semester_number' => 1]);
    Curriculum::create(['discipline_id' => $d2->id, 'group_id' => $g2->id, 'course_number' => 1, 'semester_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/curricula?group_id={$g1->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/curricula?discipline_id={$d2->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('декан видит только планы групп своего факультета', function () {
    $admin = makeUserWithRoleС('admin');
    $f1 = Faculty::create(['name' => 'Ф1', 'short_name' => 'Ф1']);
    $f2 = Faculty::create(['name' => 'Ф2', 'short_name' => 'Ф2']);
    $d1 = makeDisciplineС();
    $g1 = makeGroupС($f1);
    $g2 = makeGroupС($f2);

    Curriculum::create(['discipline_id' => $d1->id, 'group_id' => $g1->id, 'course_number' => 1, 'semester_number' => 1]);
    Curriculum::create(['discipline_id' => $d1->id, 'group_id' => $g2->id, 'course_number' => 1, 'semester_number' => 1]);

    $deanUser = makeUserWithRoleС('dean');
    \App\Models\DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $f1->id]);

    $this->actingAs($deanUser, 'sanctum')
        ->getJson('/api/v1/curricula')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.group_id', $g1->id);
});

it('админ обновляет запись плана', function () {
    $admin = makeUserWithRoleС('admin');
    $disc = makeDisciplineС();
    $group = makeGroupС();
    $c = Curriculum::create(['discipline_id' => $disc->id, 'group_id' => $group->id, 'course_number' => 1, 'semester_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/curricula/{$c->id}", ['course_number' => 2])
        ->assertOk()
        ->assertJsonPath('data.course_number', 2);
});

it('админ удаляет запись плана', function () {
    $admin = makeUserWithRoleС('admin');
    $disc = makeDisciplineС();
    $group = makeGroupС();
    $c = Curriculum::create(['discipline_id' => $disc->id, 'group_id' => $group->id, 'course_number' => 1, 'semester_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/curricula/{$c->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('curricula', ['id' => $c->id]);
});

it('не удаляет дисциплину, если она используется в учебном плане', function () {
    $admin = makeUserWithRoleС('admin');
    $disc = makeDisciplineС();
    $group = makeGroupС();
    Curriculum::create(['discipline_id' => $disc->id, 'group_id' => $group->id, 'course_number' => 1, 'semester_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/disciplines/{$disc->id}")
        ->assertStatus(409);
});

it('преподаватель не может создать запись плана', function () {
    $teacher = makeUserWithRoleС('teacher');
    $disc = makeDisciplineС();
    $group = makeGroupС();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/curricula', [
            'discipline_id' => $disc->id,
            'group_id' => $group->id,
            'course_number' => 1,
            'semester_number' => 1,
        ])
        ->assertStatus(403);
});
