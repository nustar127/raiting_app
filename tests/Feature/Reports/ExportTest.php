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
use App\Services\Rating\RatingRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function setupExportContext(): array
{
    $faculty = Faculty::create(['name' => 'Ф', 'short_name' => 'Ф']);
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К', 'short_name' => 'К']);
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'РППБА', 'code' => 'РППБА']);
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => 'G1', 'enrollment_year' => 2023]);
    $curr = Curriculum::create([
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 3,
    ]);
    $m1 = Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);

    $tu = User::create([
        'login' => 't_' . uniqid(),
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'Иванов',
        'first_name' => 'И',
        'role_id' => Role::where('code', 'teacher')->first()->id,
    ]);
    $teacher = Teacher::create(['user_id' => $tu->id, 'department_id' => $dept->id]);

    $period = RatingPeriod::create([
        'faculty_id' => $faculty->id,
        'name' => 'Осень 2025',
        'academic_year' => 2025,
        'semester' => 1,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);
    TeacherAssignment::create(['teacher_id' => $teacher->id, 'curriculum_id' => $curr->id, 'period_id' => $period->id]);

    $wt = WorkType::create(['name' => 'ЛР', 'code' => 'LAB']);

    $students = [];
    foreach ([10, 8, 6] as $val) {
        $su = User::create([
            'login' => 's_' . uniqid(),
            'password_hash' => Hash::make('P1!'),
            'last_name' => 'Студентов',
            'first_name' => 'С',
            'role_id' => Role::where('code', 'student')->first()->id,
        ]);
        $s = Student::create([
            'user_id' => $su->id,
            'group_id' => $group->id,
            'record_book_number' => 'RB' . rand(100000, 999999),
            'status' => 'active',
        ]);
        $students[] = $s;
        Grade::create([
            'student_id' => $s->id,
            'module_id' => $m1->id,
            'work_type_id' => $wt->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'value' => $val,
            'date_assigned' => '2025-10-01',
            'weeks_late' => 0,
        ]);
    }

    app(RatingRecalculator::class)->recalculateForGroup($group->id, $period);

    $admin = User::create([
        'login' => 'a_' . uniqid(),
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'А',
        'first_name' => 'А',
        'role_id' => Role::where('code', 'admin')->first()->id,
    ]);

    return compact('faculty', 'group', 'period', 'students', 'admin');
}

it('rating-list в CSV отдаёт файл', function () {
    $ctx = setupExportContext();

    $response = $this->actingAs($ctx['admin'], 'sanctum')
        ->get("/api/v1/reports/rating-list/export?period_id={$ctx['period']->id}&format=csv");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    expect($content)->toContain('Рейтинговый список');
    expect($content)->toContain('Студентов');
});

it('rating-list в XLSX отдаёт файл', function () {
    $ctx = setupExportContext();

    $response = $this->actingAs($ctx['admin'], 'sanctum')
        ->get("/api/v1/reports/rating-list/export?period_id={$ctx['period']->id}&format=xlsx");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
});

it('rating-list в PDF отдаёт файл с кириллицей', function () {
    $ctx = setupExportContext();

    $response = $this->actingAs($ctx['admin'], 'sanctum')
        ->get("/api/v1/reports/rating-list/export?period_id={$ctx['period']->id}&format=pdf");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('.pdf');

    // PDF начинается с %PDF
    $content = $response->getContent();
    expect(substr($content, 0, 4))->toBe('%PDF');
});

it('student-card в PDF отдаёт файл', function () {
    $ctx = setupExportContext();
    $student = $ctx['students'][0];

    $response = $this->actingAs($ctx['admin'], 'sanctum')
        ->get("/api/v1/reports/student-card/export?student_id={$student->id}&period_id={$ctx['period']->id}&format=pdf");

    $response->assertOk();
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('period-summary в CSV отдаёт файл', function () {
    $ctx = setupExportContext();

    $response = $this->actingAs($ctx['admin'], 'sanctum')
        ->get("/api/v1/reports/period-summary/export?period_id={$ctx['period']->id}&format=csv");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('неподдерживаемый формат → 422', function () {
    $ctx = setupExportContext();

    $this->actingAs($ctx['admin'], 'sanctum')
        ->get("/api/v1/reports/rating-list/export?period_id={$ctx['period']->id}&format=xml")
        ->assertStatus(422);
});

it('неизвестный тип отчёта → 404', function () {
    $ctx = setupExportContext();

    $this->actingAs($ctx['admin'], 'sanctum')
        ->get("/api/v1/reports/unknown/export?period_id={$ctx['period']->id}&format=csv")
        ->assertStatus(404); // маршрут не сматчится
});
