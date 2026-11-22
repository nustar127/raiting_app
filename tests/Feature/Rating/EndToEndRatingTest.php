<?php

use App\Models\CumulativeRating;
use App\Models\FinalRating;
use App\Models\Grade;
use App\Models\ModuleRating;
use App\Models\RatingPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\Rating\RatingRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

it('сквозной сценарий: от справочников до закрытия периода', function () {
    // ============================================================
    // 1. Админ логинится
    // ============================================================
    $adminRole = Role::where('code', 'admin')->firstOrFail();
    $admin = User::create([
        'login' => 'admin',
        'password_hash' => Hash::make('Admin123!'),
        'last_name' => 'Админ',
        'first_name' => 'Системы',
        'role_id' => $adminRole->id,
    ]);

    $loginResp = $this->postJson('/api/v1/auth/login', [
        'login' => 'admin',
        'password' => 'Admin123!',
    ])->assertOk();

    $adminToken = $loginResp->json('token');

    // ============================================================
    // 2. Админ создаёт справочники
    // ============================================================
    $faculty = $this->withToken($adminToken)
        ->postJson('/api/v1/faculties', ['name' => 'ФКП', 'short_name' => 'ФКП'])
        ->assertCreated()
        ->json('data');

    $dept = $this->withToken($adminToken)
        ->postJson('/api/v1/departments', [
            'faculty_id' => $faculty['id'],
            'name' => 'ПИКС',
            'short_name' => 'ПИКС',
        ])->assertCreated()
        ->json('data');

    $group = $this->withToken($adminToken)
        ->postJson('/api/v1/groups', [
            'faculty_id' => $faculty['id'],
            'name' => '514371',
            'enrollment_year' => (int) date('Y') - 1,
        ])->assertCreated()
        ->json('data');

    $discipline = $this->withToken($adminToken)
        ->postJson('/api/v1/disciplines', [
            'department_id' => $dept['id'],
            'name' => 'РППБА',
            'code' => 'РППБА',
        ])->assertCreated()
        ->json('data');

    $workTypeLab = $this->withToken($adminToken)
        ->postJson('/api/v1/work-types', ['name' => 'Лабораторная', 'code' => 'LAB'])
        ->assertCreated()
        ->json('data');

    $workTypeTest = $this->withToken($adminToken)
        ->postJson('/api/v1/work-types', ['name' => 'Контрольная', 'code' => 'TEST'])
        ->assertCreated()
        ->json('data');

    // ============================================================
    // 3. Учебный план + 2 модуля
    // ============================================================
    $curriculum = $this->withToken($adminToken)
        ->postJson('/api/v1/curricula', [
            'discipline_id' => $discipline['id'],
            'group_id' => $group['id'],
            'course_number' => 2,
            'semester_number' => 3,
        ])->assertCreated()
        ->json('data');

    $module1 = $this->withToken($adminToken)
        ->postJson('/api/v1/modules', [
            'curriculum_id' => $curriculum['id'],
            'name' => 'Модуль 1',
            'order_number' => 1,
        ])->assertCreated()
        ->json('data');

    $module2 = $this->withToken($adminToken)
        ->postJson('/api/v1/modules', [
            'curriculum_id' => $curriculum['id'],
            'name' => 'Модуль 2',
            'order_number' => 2,
        ])->assertCreated()
        ->json('data');

    // ============================================================
    // 4. Преподаватель и привязка
    // ============================================================
    $teacherResp = $this->withToken($adminToken)
        ->postJson('/api/v1/teachers', [
            'department_id' => $dept['id'],
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'middle_name' => 'Иванович',
        ])->assertCreated();

    $teacherId = $teacherResp->json('data.id');
    $teacherLogin = $teacherResp->json('credentials.login');
    $teacherPassword = $teacherResp->json('credentials.temporary_password');

    $this->app['auth']->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    // Смена временного пароля
    $teacherToken = $this->postJson('/api/v1/auth/login', [
        'login' => $teacherLogin,
        'password' => $teacherPassword,
    ])->assertOk()->json('token');

    $this->app['auth']->forgetGuards();

    $this->withToken($teacherToken)
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => $teacherPassword,
            'new_password' => 'Teacher123!',
            'new_password_confirmation' => 'Teacher123!',
        ])->assertOk();

    $teacherToken = $this->postJson('/api/v1/auth/login', [
        'login' => $teacherLogin,
        'password' => 'Teacher123!',
    ])->assertOk()->json('token');

    // ============================================================
    // 5. Студенты
    // ============================================================
    $this->app['auth']->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();

    $loginResp = $this->postJson('/api/v1/auth/login', [
        'login' => 'admin',
        'password' => 'Admin123!',
    ])->assertOk();

    $adminToken = $loginResp->json('token');

    $student1Resp = $this->withToken($adminToken)
        ->postJson('/api/v1/students', [
            'group_id' => $group['id'],
            'record_book_number' => '1111111',
            'last_name' => 'Коростелёва',
            'first_name' => 'Юлия',
            'middle_name' => 'Александровна',
        ])->assertCreated();

    $student1Id = $student1Resp->json('data.id');
    $student1Login = $student1Resp->json('credentials.login');
    $student1Password = $student1Resp->json('credentials.temporary_password');

    $student2Resp = $this->withToken($adminToken)
        ->postJson('/api/v1/students', [
            'group_id' => $group['id'],
            'record_book_number' => '2222222',
            'last_name' => 'Петров',
            'first_name' => 'Пётр',
        ])->assertCreated();

    $student2Id = $student2Resp->json('data.id');

    // ============================================================
    // 6. Период + привязка преподавателя + открытие
    // ============================================================
    $period = $this->withToken($adminToken)
        ->postJson('/api/v1/periods', [
            'faculty_id' => $faculty['id'],
            'name' => 'Осенний семестр 2025/2026',
            'academic_year' => 2025,
            'semester' => 1,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-31',
        ])->assertCreated()
        ->json('data');

    $this->withToken($adminToken)
        ->postJson('/api/v1/teacher-assignments', [
            'teacher_id' => $teacherId,
            'curriculum_id' => $curriculum['id'],
            'period_id' => $period['id'],
        ])->assertCreated();

    $this->withToken($adminToken)
        ->postJson("/api/v1/periods/{$period['id']}/open")
        ->assertOk()
        ->assertJsonPath('data.status', 'open');

    // ============================================================
    // 7. Веса: модули 50/50, работы ЛР 0.6 / Тест 0.4
    // ============================================================
    foreach (
        [
            ['level' => 'module', 'module_id' => $module1['id'], 'weight_value' => 0.5],
            ['level' => 'module', 'module_id' => $module2['id'], 'weight_value' => 0.5],
            ['level' => 'work', 'module_id' => $module1['id'], 'work_type_id' => $workTypeLab['id'], 'weight_value' => 0.6],
            ['level' => 'work', 'module_id' => $module1['id'], 'work_type_id' => $workTypeTest['id'], 'weight_value' => 0.4],
            ['level' => 'work', 'module_id' => $module2['id'], 'work_type_id' => $workTypeLab['id'], 'weight_value' => 0.6],
            ['level' => 'work', 'module_id' => $module2['id'], 'work_type_id' => $workTypeTest['id'], 'weight_value' => 0.4],
        ] as $weight
    ) {
        $this->withToken($adminToken)
            ->postJson('/api/v1/weights', array_merge($weight, [
                'period_id' => $period['id'],
                'curriculum_id' => $curriculum['id'],
            ]))->assertCreated();
    }

    // Декан утверждает веса
    $deanResp = $this->withToken($adminToken)
        ->postJson('/api/v1/dean-employees', [
            'faculty_id' => $faculty['id'],
            'last_name' => 'Сидорова',
            'first_name' => 'Анна',
        ])->assertCreated();

    $deanLogin = $deanResp->json('credentials.login');
    $deanPassword = $deanResp->json('credentials.temporary_password');

    $this->app['auth']->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();

    $deanToken = $this->postJson('/api/v1/auth/login', [
        'login' => $deanLogin,
        'password' => $deanPassword,
    ])->assertOk()->json('token');

    $this->app['auth']->forgetGuards();

    $this->withToken($deanToken)
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => $deanPassword,
            'new_password' => 'Dean1234!',
            'new_password_confirmation' => 'Dean1234!',
        ])->assertOk();

    $deanToken = $this->postJson('/api/v1/auth/login', [
        'login' => $deanLogin,
        'password' => 'Dean1234!',
    ])->assertOk()->json('token');

    $this->withToken($deanToken)
        ->postJson("/api/v1/periods/{$period['id']}/weights/approve")
        ->assertOk();

    // ============================================================
    // 8. Преподаватель вносит оценки
    // ============================================================
    // Студент 1: Модуль 1: ЛР=9, Тест=8; Модуль 2: ЛР=10, Тест=9
    // Студент 2: Модуль 1: ЛР=6, Тест=5; Модуль 2: ЛР=7, Тест=8
    $grades = [
        // Студент 1
        ['student_id' => $student1Id, 'module_id' => $module1['id'], 'work_type_id' => $workTypeLab['id'], 'value' => 9],
        ['student_id' => $student1Id, 'module_id' => $module1['id'], 'work_type_id' => $workTypeTest['id'], 'value' => 8],
        ['student_id' => $student1Id, 'module_id' => $module2['id'], 'work_type_id' => $workTypeLab['id'], 'value' => 10],
        ['student_id' => $student1Id, 'module_id' => $module2['id'], 'work_type_id' => $workTypeTest['id'], 'value' => 9],
        // Студент 2
        ['student_id' => $student2Id, 'module_id' => $module1['id'], 'work_type_id' => $workTypeLab['id'], 'value' => 6],
        ['student_id' => $student2Id, 'module_id' => $module1['id'], 'work_type_id' => $workTypeTest['id'], 'value' => 5],
        ['student_id' => $student2Id, 'module_id' => $module2['id'], 'work_type_id' => $workTypeLab['id'], 'value' => 7],
        ['student_id' => $student2Id, 'module_id' => $module2['id'], 'work_type_id' => $workTypeTest['id'], 'value' => 8],
    ];

    $this->app['auth']->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();

    $teacherToken = $this->postJson('/api/v1/auth/login', [
        'login' => $teacherLogin,
        'password' => 'Teacher123!',
    ])->assertOk()->json('token');

    $this->app['auth']->forgetGuards();

    foreach ($grades as $g) {
        $this->withToken($teacherToken)
            ->postJson('/api/v1/grades', array_merge($g, [
                'period_id' => $period['id'],
                'date_assigned' => '2025-10-15',
                'weeks_late' => 0,
            ]))->assertCreated();
    }

    // ============================================================
    // 9. Пересчёт (синхронно, через сервис — джоб уже задиспатчен, но
    //    для чистоты теста вызываем напрямую)
    // ============================================================
    $recalc = app(RatingRecalculator::class);
    $recalc->recalculateForGroup($group['id'], RatingPeriod::find($period['id']));

    // ============================================================
    // 10. Проверка расчётов
    // ============================================================

    // --- Студент 1 ---
    // Модуль 1: (9*0.6 + 8*0.4) / 1.0 * 9 = (5.4 + 3.2) * 9 = 8.6 * 9 = 77.4
    // Модуль 2: (10*0.6 + 9*0.4) / 1.0 * 9 = (6 + 3.6) * 9 = 9.6 * 9 = 86.4
    $s1M1 = ModuleRating::where('student_id', $student1Id)->where('module_id', $module1['id'])->first();
    $s1M2 = ModuleRating::where('student_id', $student1Id)->where('module_id', $module2['id'])->first();
    expect((float) $s1M1->calculated_value)->toBe(77.4);
    expect((float) $s1M2->calculated_value)->toBe(86.4);

    // ИР: (77.4*0.5 + 86.4*0.5) / 1.0 = 81.9
    $s1Final = FinalRating::where('student_id', $student1Id)->where('curriculum_id', $curriculum['id'])->first();
    expect((float) $s1Final->calculated_value)->toBe(81.9);
    expect($s1Final->is_admitted)->toBeTrue();

    // R_сов = 81.9 (одна дисциплина)
    $s1Cum = CumulativeRating::where('student_id', $student1Id)->where('period_id', $period['id'])->first();
    expect((float) $s1Cum->calculated_value)->toBe(81.9);
    expect($s1Cum->disciplines_count)->toBe(1);

    // --- Студент 2 ---
    // Модуль 1: (6*0.6 + 5*0.4) * 9 = (3.6 + 2.0) * 9 = 5.6 * 9 = 50.4
    // Модуль 2: (7*0.6 + 8*0.4) * 9 = (4.2 + 3.2) * 9 = 7.4 * 9 = 66.6
    $s2M1 = ModuleRating::where('student_id', $student2Id)->where('module_id', $module1['id'])->first();
    $s2M2 = ModuleRating::where('student_id', $student2Id)->where('module_id', $module2['id'])->first();
    expect((float) $s2M1->calculated_value)->toBe(50.4);
    expect((float) $s2M2->calculated_value)->toBe(66.6);

    // ИР: (50.4 + 66.6) / 2 = 58.5
    $s2Final = FinalRating::where('student_id', $student2Id)->where('curriculum_id', $curriculum['id'])->first();
    expect((float) $s2Final->calculated_value)->toBe(58.5);

    // ============================================================
    // 11. Проверка API рейтинга
    // ============================================================
    $student1User = User::where('login', $student1Login)->first();

    $this->app['auth']->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();

    // Смена пароля студентом
    $studentToken = $this->postJson('/api/v1/auth/login', [
        'login' => $student1Login,
        'password' => $student1Password,
    ])->assertOk()->json('token');

    $this->app['auth']->forgetGuards();

    $this->withToken($studentToken)
        ->postJson('/api/v1/auth/change-password', [
            'current_password' => $student1Password,
            'new_password' => 'Student123!',
            'new_password_confirmation' => 'Student123!',
        ])->assertOk();

    $studentToken = $this->postJson('/api/v1/auth/login', [
        'login' => $student1Login,
        'password' => 'Student123!',
    ])->assertOk()->json('token');

    // Карточка: место 1 в группе из 2
    $this->withToken($studentToken)
        ->getJson("/api/v1/ratings/students/{$student1Id}?period_id={$period['id']}")
        ->assertOk()
        ->assertJsonPath('data.places.group.rank', 1)
        ->assertJsonPath('data.places.group.total', 2);

    $this->app['auth']->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();

    $deanToken = $this->postJson('/api/v1/auth/login', [
        'login' => $deanLogin,
        'password' => 'Dean1234!',
    ])->assertOk()->json('token');

    $this->app['auth']->forgetGuards();

    // Рейтинговый список группы
    $this->withToken($deanToken)
        ->getJson("/api/v1/ratings/groups/{$group['id']}?period_id={$period['id']}")
        ->assertOk()
        ->assertJsonPath('data.students.0.student_id', $student1Id)
        ->assertJsonPath('data.students.1.student_id', $student2Id);

    // ============================================================
    // 12. Попытка изменить оценку в закрытом периоде
    // ============================================================
    // Сначала закроем период (2 модуля — BR-02 соблюдено)
    $this->withToken($deanToken)
        ->postJson("/api/v1/periods/{$period['id']}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    $this->app['auth']->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    // Смена временного пароля
    $teacherToken = $this->postJson('/api/v1/auth/login', [
        'login' => $teacherLogin,
        'password' => 'Teacher123!',
    ])->assertOk()->json('token');

    $this->app['auth']->forgetGuards();

    // Теперь попытка изменить оценку → 403
    $gradeToEdit = Grade::where('student_id', $student1Id)->first();
    $this->withToken($teacherToken)
        ->patchJson("/api/v1/grades/{$gradeToEdit->id}", ['value' => 10])
        ->assertStatus(403);
});
