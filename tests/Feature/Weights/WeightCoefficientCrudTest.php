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
use App\Models\WeightCoefficient;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleW(string $roleCode): User
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

function setupPeriodAndCurriculum(int $modulesCount = 2): array
{
    $faculty = Faculty::create(['name' => 'Ф' . uniqid(), 'short_name' => 'Ф' . rand(100, 999)]);
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'К', 'short_name' => 'К']);
    $disc = Discipline::create(['department_id' => $dept->id, 'name' => 'Д', 'code' => 'D' . rand(1000, 9999)]);
    $group = Group::create(['faculty_id' => $faculty->id, 'name' => 'G' . rand(1000, 9999), 'enrollment_year' => 2023]);
    $curr = Curriculum::create([
        'discipline_id' => $disc->id,
        'group_id' => $group->id,
        'course_number' => 2,
        'semester_number' => 3,
    ]);

    $modules = [];
    for ($i = 1; $i <= $modulesCount; $i++) {
        $modules[] = Module::create([
            'curriculum_id' => $curr->id,
            'name' => "М{$i}",
            'order_number' => $i,
        ]);
    }

    $period = RatingPeriod::create([
        'faculty_id' => $faculty->id,
        'name' => 'Период',
        'academic_year' => 2025,
        'semester' => 1,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);

    return [$faculty, $period, $curr, $modules];
}

it('админ создаёт discipline-вес', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/weights', [
            'period_id' => $period->id,
            'curriculum_id' => $curr->id,
            'level' => 'discipline',
            'weight_value' => 1.0,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.level', 'discipline')
        ->assertJsonPath('data.is_approved', false);
});

it('админ создаёт module-вес', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/weights', [
            'period_id' => $period->id,
            'curriculum_id' => $curr->id,
            'level' => 'module',
            'module_id' => $modules[0]->id,
            'weight_value' => 0.5,
        ])
        ->assertCreated();
});

it('не создаёт module-вес без module_id', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/weights', [
            'period_id' => $period->id,
            'curriculum_id' => $curr->id,
            'level' => 'module',
            'weight_value' => 0.5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['module_id']);
});

it('не создаёт work-вес без work_type_id', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/weights', [
            'period_id' => $period->id,
            'curriculum_id' => $curr->id,
            'level' => 'work',
            'module_id' => $modules[0]->id,
            'weight_value' => 0.5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['work_type_id']);
});

it('не создаёт вес с weight_value > 1', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/weights', [
            'period_id' => $period->id,
            'curriculum_id' => $curr->id,
            'level' => 'discipline',
            'weight_value' => 1.5,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['weight_value']);
});

it('не создаёт вес в закрытом периоде', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();
    $period->update(['status' => 'closed']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/weights', [
            'period_id' => $period->id,
            'curriculum_id' => $curr->id,
            'level' => 'discipline',
            'weight_value' => 1.0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['period_id']);
});

it('админ обновляет вес', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $weight = WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'discipline',
        'weight_value' => 1.0,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/weights/{$weight->id}", ['weight_value' => 0.5])
        ->assertOk()
        ->assertJsonPath('data.weight_value', 0.5);
});

it('нельзя удалить утверждённый вес', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $weight = WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'discipline',
        'weight_value' => 1.0,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/weights/{$weight->id}")
        ->assertStatus(409);
});

it('админ утверждает веса, если суммы = 1', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum(2);

    WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'module',
        'module_id' => $modules[0]->id,
        'weight_value' => 0.5,
    ]);
    WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'module',
        'module_id' => $modules[1]->id,
        'weight_value' => 0.5,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/weights/approve")
        ->assertOk();

    expect(WeightCoefficient::where('period_id', $period->id)->whereNotNull('approved_by')->count())
        ->toBe(2);
});

it('не утверждает веса, если сумма модулей != 1', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum(2);

    WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'module',
        'module_id' => $modules[0]->id,
        'weight_value' => 0.3,
    ]);
    WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'module',
        'module_id' => $modules[1]->id,
        'weight_value' => 0.3,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/weights/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['weights']);
});

it('не утверждает веса, если не покрыты все модули', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum(3);

    // Всего 3 модуля, заданы только 2 веса
    WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'module',
        'module_id' => $modules[0]->id,
        'weight_value' => 0.5,
    ]);
    WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'module',
        'module_id' => $modules[1]->id,
        'weight_value' => 0.5,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/weights/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['weights']);
});

it('декан не может утверждать веса чужого факультета', function () {
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $otherFaculty = Faculty::create(['name' => 'Другой', 'short_name' => 'ДР']);
    $dean = makeUserWithRoleW('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $otherFaculty->id]);

    $this->actingAs($dean, 'sanctum')
        ->postJson("/api/v1/periods/{$period->id}/weights/approve")
        ->assertStatus(403);
});

it('фильтрует веса по period_id и level', function () {
    $admin = makeUserWithRoleW('admin');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    WeightCoefficient::create([
        'period_id' => $period->id,
        'curriculum_id' => $curr->id,
        'level' => 'discipline',
        'weight_value' => 1.0,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/weights?period_id={$period->id}&level=discipline")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('преподаватель не может создать вес', function () {
    $teacher = makeUserWithRoleW('teacher');
    [$faculty, $period, $curr, $modules] = setupPeriodAndCurriculum();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/weights', [
            'period_id' => $period->id,
            'curriculum_id' => $curr->id,
            'level' => 'discipline',
            'weight_value' => 1.0,
        ])
        ->assertStatus(403);
});
