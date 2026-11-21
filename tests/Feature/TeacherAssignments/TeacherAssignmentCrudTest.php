<?php

use App\Models\Curriculum;
use App\Models\DeanEmployee;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\Group;
use App\Models\RatingPeriod;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleTA(string $roleCode): User
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

function makeTeacherTA(Faculty $faculty): Teacher
{
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К' . uniqid(), 'short_name' => 'К' . rand(100, 999)]);
    $user = makeUserWithRoleTA('teacher');
    return Teacher::create(['user_id' => $user->id, 'department_id' => $dept->id]);
}

function makeCurriculumAndPeriodTA(Faculty $faculty): array
{
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К' . uniqid(), 'short_name' => 'К' . rand(100, 999)]);
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'Д', 'code' => 'D' . rand(1000, 9999)]);
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => 'G' . rand(1000, 9999), 'enrollment_year' => 2023]);
    $curr = Curriculum::create([
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 3,
    ]);
    $period = RatingPeriod::create([
        'faculty_id' => $faculty->id,
        'name' => 'П',
        'academic_year' => 2025,
        'semester' => 1,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);

    return [$curr, $period];
}

it('админ создаёт привязку', function () {
    $admin = makeUserWithRoleTA('admin');
    $faculty = Faculty::create(['name' => 'Ф', 'short_name' => 'Ф']);
    $teacher = makeTeacherTA($faculty);
    [$curr, $period] = makeCurriculumAndPeriodTA($faculty);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/teacher-assignments', [
            'teacher_id' => $teacher->id,
            'curriculum_id' => $curr->id,
            'period_id' => $period->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.teacher_id', $teacher->id);
});

it('не создаёт дубликат привязки', function () {
    $admin = makeUserWithRoleTA('admin');
    $faculty = Faculty::create(['name' => 'Ф', 'short_name' => 'Ф']);
    $teacher = makeTeacherTA($faculty);
    [$curr, $period] = makeCurriculumAndPeriodTA($faculty);

    $payload = [
        'teacher_id' => $teacher->id,
        'curriculum_id' => $curr->id,
        'period_id' => $period->id,
    ];

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teacher-assignments', $payload)->assertCreated();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/teacher-assignments', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['teacher_id']);
});

it('не создаёт привязку, если период не с факультета curriculum', function () {
    $admin = makeUserWithRoleTA('admin');
    $f1 = Faculty::create(['name' => 'Ф1', 'short_name' => 'Ф1']);
    $f2 = Faculty::create(['name' => 'Ф2', 'short_name' => 'Ф2']);
    $teacher = makeTeacherTA($f1);
    [$curr] = makeCurriculumAndPeriodTA($f1);
    [$c2, $period2] = makeCurriculumAndPeriodTA($f2);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/teacher-assignments', [
            'teacher_id' => $teacher->id,
            'curriculum_id' => $curr->id,
            'period_id' => $period2->id, // период чужого факультета
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['period_id']);
});

it('декан видит только привязки своего факультета', function () {
    $admin = makeUserWithRoleTA('admin');
    $f1 = Faculty::create(['name' => 'Ф1', 'short_name' => 'Ф1']);
    $f2 = Faculty::create(['name' => 'Ф2', 'short_name' => 'Ф2']);
    $t1 = makeTeacherTA($f1);
    $t2 = makeTeacherTA($f2);
    [$c1, $p1] = makeCurriculumAndPeriodTA($f1);
    [$c2, $p2] = makeCurriculumAndPeriodTA($f2);

    TeacherAssignment::create(['teacher_id' => $t1->id, 'curriculum_id' => $c1->id, 'period_id' => $p1->id]);
    TeacherAssignment::create(['teacher_id' => $t2->id, 'curriculum_id' => $c2->id, 'period_id' => $p2->id]);

    $dean = makeUserWithRoleTA('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $f1->id]);

    $this->actingAs($dean, 'sanctum')
        ->getJson('/api/v1/teacher-assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('преподаватель видит только свои привязки', function () {
    $admin = makeUserWithRoleTA('admin');
    $faculty = Faculty::create(['name' => 'Ф', 'short_name' => 'Ф']);
    $t1 = makeTeacherTA($faculty);
    $t2 = makeTeacherTA($faculty);
    [$c1, $p1] = makeCurriculumAndPeriodTA($faculty);

    TeacherAssignment::create(['teacher_id' => $t1->id, 'curriculum_id' => $c1->id, 'period_id' => $p1->id]);

    $this->actingAs($t1->user, 'sanctum')
        ->getJson('/api/v1/teacher-assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.teacher_id', $t1->id);

    $this->actingAs($t2->user, 'sanctum')
        ->getJson('/api/v1/teacher-assignments')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('админ удаляет привязку', function () {
    $admin = makeUserWithRoleTA('admin');
    $faculty = Faculty::create(['name' => 'Ф', 'short_name' => 'Ф']);
    $teacher = makeTeacherTA($faculty);
    [$curr, $period] = makeCurriculumAndPeriodTA($faculty);

    $a = TeacherAssignment::create(['teacher_id' => $teacher->id, 'curriculum_id' => $curr->id, 'period_id' => $period->id]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/teacher-assignments/{$a->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teacher_assignments', ['id' => $a->id]);
});

it('метод update запрещён', function () {
    $admin = makeUserWithRoleTA('admin');
    $faculty = Faculty::create(['name' => 'Ф', 'short_name' => 'Ф']);
    $teacher = makeTeacherTA($faculty);
    [$curr, $period] = makeCurriculumAndPeriodTA($faculty);

    $a = TeacherAssignment::create(['teacher_id' => $teacher->id, 'curriculum_id' => $curr->id, 'period_id' => $period->id]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/teacher-assignments/{$a->id}", [])
        ->assertStatus(405); // Method Not Allowed
});
