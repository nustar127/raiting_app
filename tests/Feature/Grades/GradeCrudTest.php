<?php

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleGr(string $roleCode): User
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

/**
 * Готовит полный контекст: факультет, кафедра, группа, дисциплина,
 * curriculum, 2 модуля, преподаватель, привязка, период, студент.
 */
function setupFullContext(string $periodStatus = 'open'): array
{
    $faculty = Faculty::create(['name' => 'Ф' . uniqid(), 'short_name' => 'Ф' . rand(100, 999)]);
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К' . uniqid(), 'short_name' => 'К' . rand(100, 999)]);
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'Д', 'code' => 'D' . rand(10000, 99999)]);
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => 'G' . rand(1000, 9999), 'enrollment_year' => 2023]);
    $curr = Curriculum::create([
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 3,
    ]);
    $m1 = Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);
    $m2 = Module::create(['curriculum_id' => $curr->id, 'name' => 'М2', 'order_number' => 2]);

    $teacherUser = makeUserWithRoleGr('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $dept->id]);

    $period = RatingPeriod::create([
        'faculty_id' => $faculty->id,
        'name' => 'П',
        'academic_year' => 2025,
        'semester' => 1,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
        'status' => $periodStatus,
    ]);

    TeacherAssignment::create([
        'teacher_id' => $teacher->id,
        'curriculum_id' => $curr->id,
        'period_id' => $period->id,
    ]);

    $studentUser = makeUserWithRoleGr('student');
    $student = Student::create([
        'user_id' => $studentUser->id,
        'group_id' => $group->id,
        'record_book_number' => 'RB' . rand(10000, 99999),
        'status' => 'active',
    ]);

    $workType = WorkType::create(['name' => 'Лабораторная', 'code' => 'LAB' . rand(100, 999)]);

    return [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType];
}

it('преподаватель ставит оценку', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();

    $response = $this->actingAs($teacherUser, 'sanctum')
        ->postJson('/api/v1/grades', [
            'student_id' => $student->id,
            'module_id' => $m1->id,
            'work_type_id' => $workType->id,
            'period_id' => $period->id,
            'value' => 8,
            'date_assigned' => '2025-10-01',
            'weeks_late' => 1,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.value', 8)
        ->assertJsonPath('data.weeks_late', 1)
        ->assertJsonPath('data.teacher_id', $teacher->id);
});

it('не ставит оценку без привязки преподавателя', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();
    TeacherAssignment::where('teacher_id', $teacher->id)->delete();

    $this->actingAs($teacherUser, 'sanctum')
        ->postJson('/api/v1/grades', [
            'student_id' => $student->id,
            'module_id' => $m1->id,
            'work_type_id' => $workType->id,
            'period_id' => $period->id,
            'value' => 8,
            'date_assigned' => '2025-10-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['teacher_id']);
});

it('не ставит оценку в закрытом периоде', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext('closed');

    $this->actingAs($teacherUser, 'sanctum')
        ->postJson('/api/v1/grades', [
            'student_id' => $student->id,
            'module_id' => $m1->id,
            'work_type_id' => $workType->id,
            'period_id' => $period->id,
            'value' => 8,
            'date_assigned' => '2025-10-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['period_id']);
});

it('не ставит оценку студенту из чужой группы', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();
    $otherGroup = Group::create(['faculty_id' => $faculty->id, 'name' => 'OTHER' . rand(1000, 9999), 'enrollment_year' => 2023]);
    $otherUser = makeUserWithRoleGr('student');
    $otherStudent = Student::create([
        'user_id' => $otherUser->id,
        'group_id' => $otherGroup->id,
        'record_book_number' => 'RB2' . rand(10000, 99999),
        'status' => 'active',
    ]);

    $this->actingAs($teacherUser, 'sanctum')
        ->postJson('/api/v1/grades', [
            'student_id' => $otherStudent->id,
            'module_id' => $m1->id,
            'work_type_id' => $workType->id,
            'period_id' => $period->id,
            'value' => 8,
            'date_assigned' => '2025-10-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);
});

it('не ставит оценку с value вне 0–10', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();

    $this->actingAs($teacherUser, 'sanctum')
        ->postJson('/api/v1/grades', [
            'student_id' => $student->id,
            'module_id' => $m1->id,
            'work_type_id' => $workType->id,
            'period_id' => $period->id,
            'value' => 11,
            'date_assigned' => '2025-10-01',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['value']);
});

it('не создаёт дубликат оценки', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();

    $payload = [
        'student_id' => $student->id,
        'module_id' => $m1->id,
        'work_type_id' => $workType->id,
        'period_id' => $period->id,
        'value' => 8,
        'date_assigned' => '2025-10-01',
    ];

    $this->actingAs($teacherUser, 'sanctum')->postJson('/api/v1/grades', $payload)->assertCreated();
    $this->actingAs($teacherUser, 'sanctum')->postJson('/api/v1/grades', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);
});

it('преподаватель обновляет свою оценку', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();

    $grade = Grade::create([
        'student_id' => $student->id,
        'module_id' => $m1->id,
        'work_type_id' => $workType->id,
        'teacher_id' => $teacher->id,
        'period_id' => $period->id,
        'value' => 6,
        'date_assigned' => '2025-10-01',
        'weeks_late' => 0,
    ]);

    $this->actingAs($teacherUser, 'sanctum')
        ->patchJson("/api/v1/grades/{$grade->id}", ['value' => 9, 'weeks_late' => 2])
        ->assertOk()
        ->assertJsonPath('data.value', 9)
        ->assertJsonPath('data.weeks_late', 2);
});

it('нельзя обновить оценку в закрытом периоде', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();

    $grade = Grade::create([
        'student_id' => $student->id,
        'module_id' => $m1->id,
        'work_type_id' => $workType->id,
        'teacher_id' => $teacher->id,
        'period_id' => $period->id,
        'value' => 6,
        'date_assigned' => '2025-10-01',
        'weeks_late' => 0,
    ]);

    $period->update(['status' => 'closed']);

    $this->actingAs($teacherUser, 'sanctum')
        ->patchJson("/api/v1/grades/{$grade->id}", ['value' => 9])
        ->assertStatus(403);
});

it('студент видит только свою оценку', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();

    $grade = Grade::create([
        'student_id' => $student->id,
        'module_id' => $m1->id,
        'work_type_id' => $workType->id,
        'teacher_id' => $teacher->id,
        'period_id' => $period->id,
        'value' => 8,
        'date_assigned' => '2025-10-01',
        'weeks_late' => 0,
    ]);

    $this->actingAs($studentUser, 'sanctum')
        ->getJson("/api/v1/grades/{$grade->id}")
        ->assertOk()
        ->assertJsonPath('data.value', 8);
});

it('bulk сохраняет корректные строки и возвращает ошибки', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();
    $otherGroup = Group::create(['faculty_id' => $faculty->id, 'name' => 'OTHER' . rand(1000, 9999), 'enrollment_year' => 2023]);
    $badUser = makeUserWithRoleGr('student');
    $badStudent = Student::create([
        'user_id' => $badUser->id,
        'group_id' => $otherGroup->id,
        'record_book_number' => 'RB3' . rand(10000, 99999),
        'status' => 'active',
    ]);

    $response = $this->actingAs($teacherUser, 'sanctum')
        ->postJson('/api/v1/grades/bulk', [
            'module_id' => $m1->id,
            'work_type_id' => $workType->id,
            'period_id' => $period->id,
            'date_assigned' => '2025-10-01',
            'grades' => [
                ['student_id' => $student->id, 'value' => 8],
                ['student_id' => $badStudent->id, 'value' => 5],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('saved', 1)
        ->assertJsonPath('failed', 1);

    $this->assertDatabaseHas('grades', [
        'student_id' => $student->id,
        'module_id' => $m1->id,
        'value' => 8,
    ]);
    $this->assertDatabaseMissing('grades', [
        'student_id' => $badStudent->id,
        'module_id' => $m1->id,
    ]);
});

it('декан видит только оценки студентов своего факультета', function () {
    [$faculty, $teacherUser, $teacher, $period, $curr, $m1, $m2, $studentUser, $student, $workType] = setupFullContext();

    Grade::create([
        'student_id' => $student->id,
        'module_id' => $m1->id,
        'work_type_id' => $workType->id,
        'teacher_id' => $teacher->id,
        'period_id' => $period->id,
        'value' => 8,
        'date_assigned' => '2025-10-01',
        'weeks_late' => 0,
    ]);

    $deanUser = makeUserWithRoleGr('dean');
    \App\Models\DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $faculty->id]);

    $this->actingAs($deanUser, 'sanctum')
        ->getJson('/api/v1/grades')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
