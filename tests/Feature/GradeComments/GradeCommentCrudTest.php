<?php

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\GradeComment;
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

function makeUserWithRoleGC(string $roleCode): User
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

function setupGradeContext(string $periodStatus = 'open'): array
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
    $m = Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);

    $teacherUser = makeUserWithRoleGC('teacher');
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
    TeacherAssignment::create(['teacher_id' => $teacher->id, 'curriculum_id' => $curr->id, 'period_id' => $period->id]);

    $studentUser = makeUserWithRoleGC('student');
    $student = Student::create([
        'user_id' => $studentUser->id,
        'group_id' => $group->id,
        'record_book_number' => 'RB' . rand(10000, 99999),
        'status' => 'active',
    ]);

    $workType = WorkType::create(['name' => 'ЛР', 'code' => 'LAB' . rand(100, 999)]);

    $grade = Grade::create([
        'student_id' => $student->id,
        'module_id' => $m->id,
        'work_type_id' => $workType->id,
        'teacher_id' => $teacher->id,
        'period_id' => $period->id,
        'value' => 8,
        'date_assigned' => '2025-10-01',
        'weeks_late' => 0,
    ]);

    return [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student];
}

it('преподаватель создаёт комментарий к своей оценке', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();

    $response = $this->actingAs($teacherUser, 'sanctum')
        ->postJson("/api/v1/grades/{$grade->id}/comments", [
            'comment_text' => 'Отличная работа, но есть замечания к оформлению.',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.comment_text', 'Отличная работа, но есть замечания к оформлению.')
        ->assertJsonPath('data.teacher_id', $teacher->id);
});

it('преподаватель не может комментировать чужую оценку', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();

    $dept = Department::where('id', $teacher->department_id)->first();
    $otherTeacherUser = makeUserWithRoleGC('teacher');
    $otherTeacher = Teacher::create(['user_id' => $otherTeacherUser->id, 'department_id' => $dept->id]);

    $this->actingAs($otherTeacherUser, 'sanctum')
        ->postJson("/api/v1/grades/{$grade->id}/comments", [
            'comment_text' => 'Нельзя.',
        ])
        ->assertStatus(403);
});

it('не создаёт комментарий в закрытом периоде', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();
    $period->update(['status' => 'closed']);

    $this->actingAs($teacherUser, 'sanctum')
        ->postJson("/api/v1/grades/{$grade->id}/comments", [
            'comment_text' => 'Поздно.',
        ])
        ->assertStatus(403);
});

it('студент видит комментарии к своей оценке', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();

    GradeComment::create([
        'grade_id' => $grade->id,
        'teacher_id' => $teacher->id,
        'comment_text' => 'Первый комментарий',
    ]);

    $this->actingAs($studentUser, 'sanctum')
        ->getJson("/api/v1/grades/{$grade->id}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comment_text', 'Первый комментарий');
});

it('другой студент не видит комментарии', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();

    GradeComment::create([
        'grade_id' => $grade->id,
        'teacher_id' => $teacher->id,
        'comment_text' => 'Секрет',
    ]);

    $otherUser = makeUserWithRoleGC('student');

    $this->actingAs($otherUser, 'sanctum')
        ->getJson("/api/v1/grades/{$grade->id}/comments")
        ->assertStatus(403);
});

it('преподаватель обновляет свой комментарий', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();

    $comment = GradeComment::create([
        'grade_id' => $grade->id,
        'teacher_id' => $teacher->id,
        'comment_text' => 'Старый',
    ]);

    $this->actingAs($teacherUser, 'sanctum')
        ->patchJson("/api/v1/comments/{$comment->id}", ['comment_text' => 'Новый'])
        ->assertOk()
        ->assertJsonPath('data.comment_text', 'Новый');
});

it('преподаватель удаляет свой комментарий', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();

    $comment = GradeComment::create([
        'grade_id' => $grade->id,
        'teacher_id' => $teacher->id,
        'comment_text' => 'Удалю',
    ]);

    $this->actingAs($teacherUser, 'sanctum')
        ->deleteJson("/api/v1/comments/{$comment->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('grade_comments', ['id' => $comment->id]);
});

it('не редактирует комментарий в закрытом периоде', function () {
    [$faculty, $teacherUser, $teacher, $period, $grade, $studentUser, $student] = setupGradeContext();

    $comment = GradeComment::create([
        'grade_id' => $grade->id,
        'teacher_id' => $teacher->id,
        'comment_text' => 'Старый',
    ]);

    $period->update(['status' => 'closed']);

    $this->actingAs($teacherUser, 'sanctum')
        ->patchJson("/api/v1/comments/{$comment->id}", ['comment_text' => 'Новый'])
        ->assertStatus(403);
});
