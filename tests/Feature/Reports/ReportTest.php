<?php

use App\Models\CumulativeRating;
use App\Models\Curriculum;
use App\Models\DeanEmployee;
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

function userWithRoleReports(string $code): User
{
    return User::create([
        'login' => 'u_' . uniqid(),
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'Ф',
        'first_name' => 'И',
        'role_id' => Role::where('code', $code)->first()->id,
    ]);
}

/**
 * Факультет, 1 группа, N студентов, 1 дисциплина, оценки [10, 8, 6, 9, 7].
 * Возвращает [faculty, group, period, students[], teacher, curriculum, module, workType, admin].
 */
function setupReportContext(int $studentsCount = 3): array
{
    $faculty = Faculty::create(['name' => 'Ф' . uniqid(), 'short_name' => 'Ф' . rand(100, 999)]);
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К' . uniqid(), 'short_name' => 'К' . rand(100, 999)]);
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'РППБА', 'code' => 'D' . rand(10000, 99999)]);
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => 'G' . rand(1000, 9999), 'enrollment_year' => 2023]);
    $curr = Curriculum::create([
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 3,
    ]);
    $m1 = Module::create(['curriculum_id' => $curr->id, 'name' => 'М1', 'order_number' => 1]);

    $tu = userWithRoleReports('teacher');
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
    TeacherAssignment::create(['teacher_id' => $teacher->id, 'curriculum_id' => $curr->id, 'period_id' => $period->id]);

    $wt = WorkType::create(['name' => 'ЛР', 'code' => 'LAB' . rand(100, 999)]);

    $students = [];
    $values = [10, 8, 6, 9, 7];
    for ($i = 0; $i < $studentsCount; $i++) {
        $su = userWithRoleReports('student');
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
            'value' => $values[$i % count($values)],
            'date_assigned' => '2025-10-01',
            'weeks_late' => 0,
        ]);
    }

    app(RatingRecalculator::class)->recalculateForGroup($group->id, $period);

    $admin = userWithRoleReports('admin');

    return [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin];
}

// --- rating-list ---

it('rating-list возвращает рейтинговый список с ранжированием', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/reports/rating-list?faculty_id={$faculty->id}&period_id={$period->id}");

    $response->assertOk()
        ->assertJsonPath('data.report_type', 'rating_list')
        ->assertJsonCount(3, 'data.items')
        ->assertJsonPath('data.items.0.rank', 1)
        ->assertJsonPath('data.items.0.cumulative_value', fn ($v) => (float) $v === 90.0)
        ->assertJsonPath('data.items.1.rank', 2)
        ->assertJsonPath('data.items.2.rank', 3);
});

it('rating-list фильтрует по group_id', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/reports/rating-list?group_id={$group->id}&period_id={$period->id}")
        ->assertOk()
        ->assertJsonCount(3, 'data.items');
});

it('rating-list пагинация', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(5);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/reports/rating-list?period_id={$period->id}&per_page=2&page=1")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.pagination.total', 5)
        ->assertJsonPath('data.pagination.last_page', 3)
        ->assertJsonPath('data.items.0.rank', 1)
        ->assertJsonPath('data.items.1.rank', 2);
});

it('rating-list: декан видит только свой факультет', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $otherFaculty = Faculty::create(['name' => 'Чужой', 'short_name' => 'Ч']);
    $dean = userWithRoleReports('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $otherFaculty->id]);

    $this->actingAs($dean, 'sanctum')
        ->getJson("/api/v1/reports/rating-list?faculty_id={$faculty->id}&period_id={$period->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

// --- student-card ---

it('student-card возвращает полную карточку', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);
    $student = $students[0];

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/reports/student-card?student_id={$student->id}&period_id={$period->id}");

    $response->assertOk()
        ->assertJsonPath('data.report_type', 'student_card')
        ->assertJsonPath('data.student.id', $student->id)
        ->assertJsonPath('data.cumulative_rating', fn ($v) => (float) $v === 90.0)
        ->assertJsonCount(1, 'data.disciplines')
        ->assertJsonCount(1, 'data.grades')
        ->assertJsonPath('data.places.group.rank', 1);
});

it('student-card: студент не может смотреть чужую карточку', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $this->actingAs($students[0]->user, 'sanctum')
        ->getJson("/api/v1/reports/student-card?student_id={$students[1]->id}&period_id={$period->id}")
        ->assertStatus(403);
});

it('student-card: студент может смотреть свою карточку', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $this->actingAs($students[0]->user, 'sanctum')
        ->getJson("/api/v1/reports/student-card?student_id={$students[0]->id}&period_id={$period->id}")
        ->assertOk();
});

it('student-card без student_id возвращает 422', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/reports/student-card?period_id={$period->id}")
        ->assertStatus(422);
});

// --- period-summary ---

it('period-summary возвращает общую сводку и распределение', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/reports/period-summary?period_id={$period->id}");

    $response->assertOk()
        ->assertJsonPath('data.report_type', 'period_summary')
        ->assertJsonPath('data.totals.students_count', 3)
        ->assertJsonStructure([
            'data' => [
                'totals' => ['students_count', 'avg_rating', 'max_rating', 'min_rating'],
                'distribution' => ['0-59', '60-69', '70-79', '80-89', '90-100'],
                'groups',
                'disciplines',
            ],
        ]);

    // 3 студента с оценками 10, 8, 6 → R_сов 90, 72, 54
    // Распределение: 0-59: 1 (54), 60-69: 0, 70-79: 1 (72), 80-89: 0, 90-100: 1 (90)
    expect($response->json('data.distribution.0-59'))->toBe(1);
    expect($response->json('data.distribution.70-79'))->toBe(1);
    expect($response->json('data.distribution.90-100'))->toBe(1);
});

it('period-summary: разбивка по группам и дисциплинам', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/reports/period-summary?period_id={$period->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data.groups')
        ->assertJsonCount(1, 'data.disciplines')
        ->assertJsonPath('data.groups.0.students_count', 3);
});

it('period-summary: декан видит только свой факультет', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $dean = userWithRoleReports('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    $this->actingAs($dean, 'sanctum')
        ->getJson("/api/v1/reports/period-summary?period_id={$period->id}")
        ->assertOk()
        ->assertJsonPath('data.totals.students_count', 3);
});

it('404 если периода нет', function () {
    [$faculty, $group, $period, $students, $teacher, $curr, $m1, $wt, $admin] = setupReportContext(3);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/reports/rating-list?period_id=999999')
        ->assertStatus(404);
});
