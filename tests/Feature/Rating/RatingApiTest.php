<?php

use App\Models\CumulativeRating;
use App\Models\Curriculum;
use App\Models\DeanEmployee;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\FinalRating;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Module;
use App\Models\ModuleRating;
use App\Models\RatingPeriod;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkType;
use App\Services\Rating\RatingRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeRatingSetup(): array
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

    $roleT = Role::where('code', 'teacher')->first();
    $tu = User::create(['login' => 't_' . uniqid(), 'password_hash' => Hash::make('P1!'), 'last_name' => 'Ф', 'first_name' => 'И', 'role_id' => $roleT->id]);
    $teacher = Teacher::create(['user_id' => $tu->id, 'department_id' => $dept->id]);

    $period = RatingPeriod::create([
        'faculty_id' => $faculty->id,
        'name' => 'П',
        'academic_year' => 2025,
        'semester' => 1,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);
    \App\Models\TeacherAssignment::create(['teacher_id' => $teacher->id, 'curriculum_id' => $curr->id, 'period_id' => $period->id]);

    $wt = WorkType::create(['name' => 'ЛР', 'code' => 'LAB' . rand(100, 999)]);

    // 3 студента в группе
    $students = [];
    foreach (['А', 'Б', 'В'] as $idx => $letter) {
        $roleS = Role::where('code', 'student')->first();
        $su = User::create(['login' => 's' . $idx . '_' . uniqid(), 'password_hash' => Hash::make('P1!'), 'last_name' => $letter, 'first_name' => 'С', 'role_id' => $roleS->id]);
        $students[] = Student::create([
            'user_id' => $su->id,
            'group_id' => $group->id,
            'record_book_number' => 'RB' . rand(10000, 99999),
            'status' => 'active',
        ]);
    }

    // Оценки: А=10, Б=8, В=6
    $values = [10, 8, 6];
    foreach ($students as $i => $s) {
        Grade::create([
            'student_id' => $s->id,
            'module_id' => $m1->id,
            'work_type_id' => $wt->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'value' => $values[$i],
            'date_assigned' => '2025-10-01',
            'weeks_late' => 0,
        ]);
        Grade::create([
            'student_id' => $s->id,
            'module_id' => $m2->id,
            'work_type_id' => $wt->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'value' => $values[$i],
            'date_assigned' => '2025-10-01',
            'weeks_late' => 0,
        ]);
    }

    // Пересчёт
    app(RatingRecalculator::class)->recalculateForGroup($group->id, $period);

    return compact('faculty', 'group', 'curr', 'm1', 'm2', 'period', 'students', 'tu');
}

it('студент видит карточку своего рейтинга с местами', function () {
    $ctx = makeRatingSetup();
    $student = $ctx['students'][0]; // А, лучший
    $studentUser = $student->user;

    $response = $this->actingAs($studentUser, 'sanctum')
        ->getJson("/api/v1/ratings/students/{$student->id}?period_id={$ctx['period']->id}");

    $response->assertOk()
        ->assertJsonPath('data.places.group.rank', 1)
        ->assertJsonPath('data.places.group.total', 3);

    expect($response->json('data.cumulative_rating'))->toBeGreaterThan(0);
});

it('студент не видит карточку другого студента', function () {
    $ctx = makeRatingSetup();
    $this->actingAs($ctx['students'][1]->user, 'sanctum')
        ->getJson("/api/v1/ratings/students/{$ctx['students'][0]->id}?period_id={$ctx['period']->id}")
        ->assertStatus(403);
});

it('рейтинговый список группы упорядочен по убыванию', function () {
    $ctx = makeRatingSetup();
    $deanUser = User::create([
        'login' => 'dean_' . uniqid(),
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'Д',
        'first_name' => 'Д',
        'role_id' => Role::where('code', 'dean')->first()->id,
    ]);
    DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $ctx['faculty']->id]);

    $response = $this->actingAs($deanUser, 'sanctum')
        ->getJson("/api/v1/ratings/groups/{$ctx['group']->id}?period_id={$ctx['period']->id}");

    $response->assertOk()
        ->assertJsonCount(3, 'data.students')
        ->assertJsonPath('data.students.0.rank', 1)
        ->assertJsonPath('data.students.0.full_name', 'А С')
        ->assertJsonPath('data.students.1.rank', 2)
        ->assertJsonPath('data.students.2.rank', 3);
});

it('рейтинговый список факультета содержит всех студентов', function () {
    $ctx = makeRatingSetup();
    $admin = User::create([
        'login' => 'a_' . uniqid(),
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'А',
        'first_name' => 'А',
        'role_id' => Role::where('code', 'admin')->first()->id,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/ratings/faculties/{$ctx['faculty']->id}?period_id={$ctx['period']->id}");

    $response->assertOk()
        ->assertJsonCount(3, 'data.students');
});

it('top-N возвращает лучших студентов', function () {
    $ctx = makeRatingSetup();
    $admin = User::create([
        'login' => 'a_' . uniqid(),
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'А',
        'first_name' => 'А',
        'role_id' => Role::where('code', 'admin')->first()->id,
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/ratings/top?period_id={$ctx['period']->id}&limit=2");

    $response->assertOk()
        ->assertJsonCount(2, 'data.top')
        ->assertJsonPath('data.top.0.full_name', 'А С');
});

it('карточка возвращает разбивку по дисциплинам и модулям', function () {
    $ctx = makeRatingSetup();
    $student = $ctx['students'][0];

    $response = $this->actingAs($student->user, 'sanctum')
        ->getJson("/api/v1/ratings/students/{$student->id}?period_id={$ctx['period']->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data.disciplines')
        ->assertJsonCount(2, 'data.modules');
});

it('404, если период не найден', function () {
    $ctx = makeRatingSetup();
    $admin = User::create([
        'login' => 'a_' . uniqid(),
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'А',
        'first_name' => 'А',
        'role_id' => Role::where('code', 'admin')->first()->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/ratings/groups/{$ctx['group']->id}?period_id=999999")
        ->assertStatus(404);
});
