<?php

use App\Models\CumulativeRating;
use App\Models\RatingPeriod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function setupCommandContext(): array
{
    $faculty = \App\Models\Faculty::create(['name' => 'Ф', 'short_name' => 'Ф']);
    $dept = \App\Models\Department::create(['faculty_id' => $faculty->id, 'name' => 'К', 'short_name' => 'К']);
    $disc = \App\Models\Discipline::create(['department_id' => $dept->id, 'name' => 'Д', 'code' => 'D' . rand(10000, 99999)]);
    $group = \App\Models\Group::create(['faculty_id' => $faculty->id, 'name' => 'G' . rand(1000, 9999), 'enrollment_year' => 2023]);
    $curr = \App\Models\Curriculum::create(['discipline_id' => $disc->id, 'group_id' => $group->id, 'course_number' => 2, 'semester_number' => 3]);
    $m1 = \App\Models\Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);

    $role = Role::where('code', 'teacher')->first();
    $tu = User::create(['login' => 't_' . uniqid(), 'password_hash' => Hash::make('P1!'), 'last_name' => 'Ф', 'first_name' => 'И', 'role_id' => $role->id]);
    $teacher = \App\Models\Teacher::create(['user_id' => $tu->id, 'department_id' => $dept->id]);

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

    $roleS = Role::where('code', 'student')->first();
    $su = User::create(['login' => 's_' . uniqid(), 'password_hash' => Hash::make('P1!'), 'last_name' => 'Ф', 'first_name' => 'И', 'role_id' => $roleS->id]);
    $student = \App\Models\Student::create(['user_id' => $su->id, 'group_id' => $group->id, 'record_book_number' => 'RB' . rand(10000, 99999), 'status' => 'active']);

    $wt = \App\Models\WorkType::create(['name' => 'ЛР', 'code' => 'LAB' . rand(100, 999)]);
    \App\Models\Grade::create([
        'student_id' => $student->id,
        'module_id' => $m1->id,
        'work_type_id' => $wt->id,
        'teacher_id' => $teacher->id,
        'period_id' => $period->id,
        'value' => 8,
        'date_assigned' => '2025-10-01',
        'weeks_late' => 0,
    ]);

    return [$period, $student, $group];
}

it('команда требует --period', function () {
    $exit = Artisan::call('ratings:recalculate');
    expect($exit)->toBe(1);
});

it('команда возвращает ошибку при неизвестном --period', function () {
    $exit = Artisan::call('ratings:recalculate', ['--period' => 999999]);
    expect($exit)->toBe(1);
});

it('команда пересчитывает одного студента', function () {
    [$period, $student] = setupCommandContext();

    $exit = Artisan::call('ratings:recalculate', [
        '--period' => $period->id,
        '--student' => $student->id,
    ]);

    expect($exit)->toBe(0);
    expect(CumulativeRating::where('student_id', $student->id)->count())->toBe(1);
});

it('команда пересчитывает группу', function () {
    [$period, $student, $group] = setupCommandContext();

    $exit = Artisan::call('ratings:recalculate', [
        '--period' => $period->id,
        '--group' => $group->id,
    ]);

    expect($exit)->toBe(0);
    expect(CumulativeRating::where('student_id', $student->id)->count())->toBe(1);
});

it('команда пересчитывает всех студентов периода через --all', function () {
    [$period, $student] = setupCommandContext();

    $exit = Artisan::call('ratings:recalculate', [
        '--period' => $period->id,
        '--all' => true,
    ]);

    expect($exit)->toBe(0);
    expect(CumulativeRating::where('student_id', $student->id)->count())->toBe(1);
});
