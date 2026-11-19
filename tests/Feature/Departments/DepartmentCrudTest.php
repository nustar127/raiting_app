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

function makeUserWithRoleDep(string $roleCode): User
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

function makeFaculty(): Faculty
{
    return Faculty::create([
        'name' => 'Факультет ' . uniqid(),
        'short_name' => 'Ф' . rand(100, 999),
    ]);
}

it('админ создаёт кафедру', function () {
    $admin = makeUserWithRoleDep('admin');
    $faculty = makeFaculty();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/departments', [
            'faculty_id' => $faculty->id,
            'name' => 'Проектирования информационно-компьютерных систем',
            'short_name' => 'ПИКС',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.short_name', 'ПИКС')
        ->assertJsonPath('data.faculty_id', $faculty->id);
});

it('не создаёт кафедру с несуществующим faculty_id', function () {
    $admin = makeUserWithRoleDep('admin');

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/departments', [
            'faculty_id' => 999999,
            'name' => 'Тест',
            'short_name' => 'ТСТ',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['faculty_id']);
});

it('не создаёт кафедру с дублирующейся аббревиатурой на том же факультете', function () {
    $admin = makeUserWithRoleDep('admin');
    $faculty = makeFaculty();
    Department::create(['faculty_id' => $faculty->id, 'name' => 'Первая', 'short_name' => 'ПИКС']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/departments', [
            'faculty_id' => $faculty->id,
            'name' => 'Вторая',
            'short_name' => 'ПИКС',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['short_name']);
});

it('разрешает одинаковую аббревиатуру на разных факультетах', function () {
    $admin = makeUserWithRoleDep('admin');
    $f1 = makeFaculty();
    $f2 = makeFaculty();
    Department::create(['faculty_id' => $f1->id, 'name' => 'Первая', 'short_name' => 'ПИКС']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/departments', [
            'faculty_id' => $f2->id,
            'name' => 'Вторая',
            'short_name' => 'ПИКС',
        ]);

    $response->assertCreated();
});

it('фильтрует по faculty_id', function () {
    $student = makeUserWithRoleDep('student');
    $f1 = makeFaculty();
    $f2 = makeFaculty();
    Department::create(['faculty_id' => $f1->id, 'name' => 'A', 'short_name' => 'AA']);
    Department::create(['faculty_id' => $f2->id, 'name' => 'B', 'short_name' => 'BB']);

    $response = $this->actingAs($student, 'sanctum')
        ->getJson("/api/v1/departments?faculty_id={$f1->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.faculty_id', $f1->id);
});

it('студент видит список кафедр', function () {
    $student = makeUserWithRoleDep('student');
    $faculty = makeFaculty();
    Department::create(['faculty_id' => $faculty->id, 'name' => 'A', 'short_name' => 'AA']);
    Department::create(['faculty_id' => $faculty->id, 'name' => 'B', 'short_name' => 'BB']);

    $this->actingAs($student, 'sanctum')
        ->getJson('/api/v1/departments')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('преподаватель не может создать кафедру', function () {
    $teacher = makeUserWithRoleDep('teacher');
    $faculty = makeFaculty();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/departments', [
            'faculty_id' => $faculty->id,
            'name' => 'X',
            'short_name' => 'XX',
        ])
        ->assertStatus(403);
});

it('админ обновляет кафедру', function () {
    $admin = makeUserWithRoleDep('admin');
    $faculty = makeFaculty();
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Старое', 'short_name' => 'СТ']);

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/departments/{$dept->id}", ['name' => 'Новое']);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Новое');
});

it('админ удаляет кафедру без преподавателей', function () {
    $admin = makeUserWithRoleDep('admin');
    $faculty = makeFaculty();
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Удал', 'short_name' => 'УД']);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/departments/{$dept->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

it('не удаляет кафедру с преподавателями', function () {
    $admin = makeUserWithRoleDep('admin');
    $faculty = makeFaculty();
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'С преподом', 'short_name' => 'СП']);

    $teacherUser = makeUserWithRoleDep('teacher');
    Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $dept->id]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/departments/{$dept->id}")
        ->assertStatus(409);

    $this->assertDatabaseHas('departments', ['id' => $dept->id]);
});

it('возвращает 401 без токена', function () {
    $this->getJson('/api/v1/departments')->assertStatus(401);
});
