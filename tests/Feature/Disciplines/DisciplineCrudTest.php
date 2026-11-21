<?php

use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleDis(string $roleCode): User
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

function makeDepartment(): Department
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

it('админ создаёт дисциплину', function () {
    $admin = makeUserWithRoleDis('admin');
    $dept = makeDepartment();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/disciplines', [
            'department_id' => $dept->id,
            'name' => 'Разработка программных приложений для бизнес-анализа',
            'code' => 'РППБА',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'РППБА')
        ->assertJsonPath('data.department_id', $dept->id);
});

it('не создаёт дисциплину с дублирующимся code', function () {
    $admin = makeUserWithRoleDis('admin');
    $dept = makeDepartment();
    Discipline::create(['department_id' => $dept->id, 'name' => 'A', 'code' => 'РППБА']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/disciplines', [
            'department_id' => $dept->id,
            'name' => 'B',
            'code' => 'РППБА',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('не создаёт дисциплину с несуществующим department_id', function () {
    $admin = makeUserWithRoleDis('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/disciplines', [
            'department_id' => 999999,
            'name' => 'A',
            'code' => 'XYZ',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['department_id']);
});

it('фильтрует по department_id', function () {
    $student = makeUserWithRoleDis('student');
    $d1 = makeDepartment();
    $d2 = makeDepartment();
    Discipline::create(['department_id' => $d1->id, 'name' => 'A', 'code' => 'A1']);
    Discipline::create(['department_id' => $d2->id, 'name' => 'B', 'code' => 'B1']);

    $this->actingAs($student, 'sanctum')
        ->getJson("/api/v1/disciplines?department_id={$d1->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'A1');
});

it('фильтрует по faculty_id через department', function () {
    $student = makeUserWithRoleDis('student');
    $d1 = makeDepartment();
    $d2 = makeDepartment();
    Discipline::create(['department_id' => $d1->id, 'name' => 'A', 'code' => 'A1']);
    Discipline::create(['department_id' => $d2->id, 'name' => 'B', 'code' => 'B1']);

    $this->actingAs($student, 'sanctum')
        ->getJson("/api/v1/disciplines?faculty_id={$d1->faculty_id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'A1');
});

it('ищет по name и code', function () {
    $admin = makeUserWithRoleDis('admin');
    $dept = makeDepartment();
    Discipline::create(['department_id' => $dept->id, 'name' => 'Математика', 'code' => 'МАТ']);
    Discipline::create(['department_id' => $dept->id, 'name' => 'Физика', 'code' => 'ФИЗ']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/disciplines?search=матем')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'МАТ');
});

it('преподаватель не может создать дисциплину', function () {
    $teacher = makeUserWithRoleDis('teacher');
    $dept = makeDepartment();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/disciplines', [
            'department_id' => $dept->id,
            'name' => 'X',
            'code' => 'XXX',
        ])
        ->assertStatus(403);
});

it('админ обновляет дисциплину', function () {
    $admin = makeUserWithRoleDis('admin');
    $dept = makeDepartment();
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'Старое', 'code' => 'СТ']);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/disciplines/{$disc->id}", ['name' => 'Новое'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Новое');
});

it('админ удаляет дисциплину', function () {
    $admin = makeUserWithRoleDis('admin');
    $dept = makeDepartment();
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'Удал', 'code' => 'УД']);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/disciplines/{$disc->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('disciplines', ['id' => $disc->id]);
});

it('возвращает 401 без токена', function () {
    $this->getJson('/api/v1/disciplines')->assertStatus(401);
});
