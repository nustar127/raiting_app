<?php

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\Group;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleM(string $roleCode): User
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

function makeCurriculum(): Curriculum
{
    $faculty = Faculty::create(['name' => 'Ф' . uniqid(), 'short_name' => 'Ф' . rand(100, 999)]);
    $dept = Department::create([
        'faculty_id' => $faculty->id,
        'name' => 'К' . uniqid(),
        'short_name' => 'К' . rand(100, 999),
    ]);
    $disc = Discipline::create([
        'department_id' => $dept->id,
        'name' => 'Д ' . uniqid(),
        'code' => 'D' . rand(10000, 99999),
    ]);
    $group = Group::create([
        'faculty_id' => $faculty->id,
        'name' => 'G' . rand(1000, 9999),
        'enrollment_year' => (int) date('Y') - 1,
    ]);

    return Curriculum::create([
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 4,
    ]);
}

it('админ создаёт модуль', function () {
    $admin = makeUserWithRoleM('admin');
    $curr = makeCurriculum();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/modules', [
            'curriculum_id' => $curr->id,
            'name' => 'Модуль 1',
            'order_number' => 1,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.order_number', 1);
});

it('не создаёт модуль с дублирующимся order_number в рамках плана', function () {
    $admin = makeUserWithRoleM('admin');
    $curr = makeCurriculum();
    Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/modules', [
            'curriculum_id' => $curr->id,
            'name' => 'М2',
            'order_number' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order_number']);
});

it('не позволяет создать более 4 модулей на дисциплину', function () {
    $admin = makeUserWithRoleM('admin');
    $curr = makeCurriculum();

    for ($i = 1; $i <= 4; $i++) {
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/modules', [
                'curriculum_id' => $curr->id,
                'name' => "М{$i}",
                'order_number' => $i,
            ])
            ->assertCreated();
    }

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/modules', [
            'curriculum_id' => $curr->id,
            'name' => 'М5',
            'order_number' => 5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order_number']);
});

it('не создаёт модуль с несуществующим curriculum_id', function () {
    $admin = makeUserWithRoleM('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/modules', [
            'curriculum_id' => 999999,
            'name' => 'X',
            'order_number' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['curriculum_id']);
});

it('фильтрует по curriculum_id', function () {
    $admin = makeUserWithRoleM('admin');
    $c1 = makeCurriculum();
    $c2 = makeCurriculum();
    Module::create(['curriculum_id' => $c1->id, 'name' => 'A', 'order_number' => 1]);
    Module::create(['curriculum_id' => $c2->id, 'name' => 'B', 'order_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/modules?curriculum_id={$c1->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('декан видит только модули своего факультета', function () {
    $admin = makeUserWithRoleM('admin');
    $c1 = makeCurriculum();
    $c2 = makeCurriculum();
    Module::create(['curriculum_id' => $c1->id, 'name' => 'A', 'order_number' => 1]);
    Module::create(['curriculum_id' => $c2->id, 'name' => 'B', 'order_number' => 1]);

    $faculty = $c1->group->faculty;

    $deanUser = makeUserWithRoleM('dean');
    \App\Models\DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $faculty->id]);

    $this->actingAs($deanUser, 'sanctum')
        ->getJson('/api/v1/modules')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.curriculum_id', $c1->id);
});

it('админ обновляет модуль', function () {
    $admin = makeUserWithRoleM('admin');
    $curr = makeCurriculum();
    $module = Module::create(['curriculum_id' => $curr->id, 'name' => 'Старое', 'order_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/modules/{$module->id}", ['name' => 'Новое'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Новое');
});

it('админ удаляет модуль', function () {
    $admin = makeUserWithRoleM('admin');
    $curr = makeCurriculum();
    $module = Module::create(['curriculum_id' => $curr->id, 'name' => 'Удал', 'order_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/modules/{$module->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('modules', ['id' => $module->id]);
});

it('не удаляет curriculum с модулями', function () {
    $admin = makeUserWithRoleM('admin');
    $curr = makeCurriculum();
    Module::create(['curriculum_id' => $curr->id, 'name' => 'М', 'order_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/curricula/{$curr->id}")
        ->assertStatus(409);
});

it('преподаватель не может создать модуль', function () {
    $teacher = makeUserWithRoleM('teacher');
    $curr = makeCurriculum();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/modules', [
            'curriculum_id' => $curr->id,
            'name' => 'X',
            'order_number' => 1,
        ])
        ->assertStatus(403);
});
