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

function userWithRole(string $code): User
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
 * Готовит факультет с одной группой, 3 студентами, 1 дисциплиной, оценки [10, 8, 6].
 * Возвращает [faculty, group, period, students[], adminUser].
 */
function setupDashboardContext(int $studentsCount = 3): array
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

    $tu = userWithRole('teacher');
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
    $values = [10, 8, 6, 9, 7, 5, 4];
    for ($i = 0; $i < $studentsCount; $i++) {
        $su = userWithRole('student');
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

    $admin = userWithRole('admin');

    return [$faculty, $group, $period, $students, $admin];
}

it('summary возвращает агрегаты по факультету', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/dashboard/summary?faculty_id={$faculty->id}&period_id={$period->id}")
        ->assertOk()
        ->assertJsonPath('data.stats.students_count', 3)
        ->assertJsonPath('data.stats.groups_count', 1)
        ->assertJsonStructure([
            'data' => [
                'period' => ['id', 'name', 'status'],
                'stats' => ['avg_rating', 'students_count', 'admitted_count', 'not_admitted_count', 'groups_count'],
            ],
        ]);
});

it('summary: средний R_сов корректен', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    // Оценки: 10, 8, 6 → MP: 90, 72, 54 → ИР: 90, 72, 54 → R_сов: 90, 72, 54
    // Среднее: (90 + 72 + 54) / 3 = 72
    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/dashboard/summary?faculty_id={$faculty->id}&period_id={$period->id}");

    expect((float) $response->json('data.stats.avg_rating'))->toBe(72.0);
});

it('faculty возвращает разбивку по группам', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    // Добавим вторую группу
    $group2 = Group::create(['faculty_id' => $faculty->id, 'name' => 'G2X' . rand(1000, 9999), 'enrollment_year' => 2023]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/dashboard/faculty/{$faculty->id}?period_id={$period->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.groups')
        ->assertJsonPath('data.stats.students_count', 3);
});

it('faculty: декан не может смотреть чужой факультет', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    $otherFaculty = Faculty::create(['name' => 'Чужой', 'short_name' => 'Ч']);
    $deanUser = userWithRole('dean');
    DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $otherFaculty->id]);

    $this->actingAs($deanUser, 'sanctum')
        ->getJson("/api/v1/dashboard/faculty/{$faculty->id}?period_id={$period->id}")
        ->assertStatus(403);
});

it('group возвращает статистику и топ студентов', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/dashboard/group/{$group->id}?period_id={$period->id}");

    $response->assertOk()
        ->assertJsonPath('data.stats.students_count', 3)
        ->assertJsonCount(3, 'data.top_students')
        ->assertJsonCount(1, 'data.disciplines');

    // Топ: студент с оценкой 10 → rank 1, R_сов = 90
    expect((float) $response->json('data.top_students.0.rating'))->toBe(90.0);
    expect((float) $response->json('data.stats.best_rating'))->toBe(90.0);
    expect((float) $response->json('data.stats.worst_rating'))->toBe(54.0);
});

it('group: студент не может смотреть чужую группу', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    $otherGroup = Group::create(['faculty_id' => $faculty->id, 'name' => 'CH' . rand(1000, 9999), 'enrollment_year' => 2023]);

    $this->actingAs($students[0]->user, 'sanctum')
        ->getJson("/api/v1/dashboard/group/{$otherGroup->id}?period_id={$period->id}")
        ->assertStatus(403);
});

it('group: студент может смотреть свою группу', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    $this->actingAs($students[0]->user, 'sanctum')
        ->getJson("/api/v1/dashboard/group/{$group->id}?period_id={$period->id}")
        ->assertOk();
});

it('404 если периода нет', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/dashboard/summary?period_id=999999')
        ->assertStatus(404);
});

it('декан получает summary только по своему факультету', function () {
    [$faculty, $group, $period, $students, $admin] = setupDashboardContext(3);

    $deanUser = userWithRole('dean');
    DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $faculty->id]);

    $response = $this->actingAs($deanUser, 'sanctum')
        ->getJson("/api/v1/dashboard/summary?period_id={$period->id}");

    $response->assertOk()
        ->assertJsonPath('data.stats.students_count', 3);
});
