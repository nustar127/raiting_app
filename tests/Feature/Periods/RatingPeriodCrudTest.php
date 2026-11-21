<?php

use App\Models\Curriculum;
use App\Models\DeanEmployee;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\Group;
use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleRP(string $roleCode): User
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

function makeFacultyRP(): Faculty
{
    return Faculty::create(['name' => 'Ф' . uniqid(), 'short_name' => 'Ф' . rand(100, 999)]);
}

function makePeriod(Faculty $faculty, string $status = 'closed'): RatingPeriod
{
    return RatingPeriod::create([
        'faculty_id' => $faculty->id,
        'name' => 'Осенний семестр ' . rand(1000, 9999),
        'academic_year' => 2025,
        'semester' => 1,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
        'status' => $status,
    ]);
}

it('админ создаёт период', function () {
    $admin = makeUserWithRoleRP('admin');
    $faculty = makeFacultyRP();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/periods', [
            'faculty_id' => $faculty->id,
            'name' => 'Осенний семестр 2025/2026',
            'academic_year' => 2025,
            'semester' => 1,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-31',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.academic_year', 2025);
});

it('не создаёт период с пересекающимися датами на том же факультете', function () {
    $admin = makeUserWithRoleRP('admin');
    $faculty = makeFacultyRP();
    makePeriod($faculty);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/periods', [
            'faculty_id' => $faculty->id,
            'name' => 'Другой',
            'academic_year' => 2025,
            'semester' => 2,
            'start_date' => '2025-10-01',
            'end_date' => '2026-01-15',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);
});

it('не создаёт период с дублирующимся (faculty, year, semester)', function () {
    $admin = makeUserWithRoleRP('admin');
    $faculty = makeFacultyRP();
    makePeriod($faculty);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/periods', [
            'faculty_id' => $faculty->id,
            'name' => 'Ещё один',
            'academic_year' => 2025,
            'semester' => 1,
            'start_date' => '2025-09-02',
            'end_date' => '2025-12-30',
        ])
        ->assertStatus(422);
});

it('декан открывает период своего факультета', function () {
    $faculty = makeFacultyRP();
    $period = makePeriod($faculty);
    $dean = makeUserWithRoleRP('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    $this->actingAs($dean, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/open")
        ->assertOk()
        ->assertJsonPath('data.status', 'open');
});

it('декан не может открыть период чужого факультета', function () {
    $f1 = makeFacultyRP();
    $f2 = makeFacultyRP();
    $period = makePeriod($f2);
    $dean = makeUserWithRoleRP('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $f1->id]);

    $this->actingAs($dean, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/open")
        ->assertStatus(403);
});

it('не открывает уже открытый период', function () {
    $faculty = makeFacultyRP();
    $period = makePeriod($faculty, 'open');
    $admin = makeUserWithRoleRP('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/open")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('декан закрывает период, если модулей в норме', function () {
    $faculty = makeFacultyRP();
    $period = makePeriod($faculty, 'open');
    $dean = makeUserWithRoleRP('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    // Создаём curriculum с 2 модулями
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К', 'short_name' => 'К']);
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'Д', 'code' => 'D1']);
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => 'G1', 'enrollment_year' => 2023]);
    $curr = Curriculum::create(['discipline_id' => $disc->id, 'group_id' => $group->id, 'course_number' => 2, 'semester_number' => 3]);
    Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);
    Module::create(['curriculum_id' => $curr->id, 'name' => 'М2', 'order_number' => 2]);

    $this->actingAs($dean, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');
});

it('не закрывает период, если у curriculum 1 модуль', function () {
    $faculty = makeFacultyRP();
    $period = makePeriod($faculty, 'open');
    $admin = makeUserWithRoleRP('admin');

    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К', 'short_name' => 'К']);
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'Д', 'code' => 'D1']);
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => 'G1', 'enrollment_year' => 2023]);
    $curr = Curriculum::create(['discipline_id' => $disc->id, 'group_id' => $group->id, 'course_number' => 2, 'semester_number' => 3]);
    Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/close")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['modules']);
});

it('не закрывает уже закрытый период', function () {
    $faculty = makeFacultyRP();
    $period = makePeriod($faculty, 'closed');
    $admin = makeUserWithRoleRP('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/close")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('декан видит только периоды своего факультета', function () {
    $f1 = makeFacultyRP();
    $f2 = makeFacultyRP();
    makePeriod($f1);
    makePeriod($f2);

    $dean = makeUserWithRoleRP('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $f1->id]);

    $this->actingAs($dean, 'sanctum')
        ->getJson('/api/v1/periods')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('нельзя удалить открытый период', function () {
    $faculty = makeFacultyRP();
    $period = makePeriod($faculty, 'open');
    $admin = makeUserWithRoleRP('admin');

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/periods/{$period->id}")
        ->assertStatus(403);
});

it('преподаватель не может создать период', function () {
    $teacher = makeUserWithRoleRP('teacher');
    $faculty = makeFacultyRP();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/periods', [
            'faculty_id' => $faculty->id,
            'name' => 'X',
            'academic_year' => 2025,
            'semester' => 1,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-31',
        ])
        ->assertStatus(403);
});
