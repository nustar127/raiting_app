<?php

use App\Models\CumulativeRating;
use App\Models\Curriculum;
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
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\WeightCoefficient;
use App\Models\WorkType;
use App\Services\Rating\RatingRecalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function userWithRoleRRE(string $code): User
{
    $r = Role::where('code', $code)->firstOrFail();
    return User::create([
        'login' => 'u_' . uniqid(),
        'password_hash' => Hash::make('Password1!'),
        'last_name' => 'Ф',
        'first_name' => 'И',
        'role_id' => $r->id,
    ]);
}

function buildRatingContext(int $modulesCount = 2): array
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
    $modules = [];
    for ($i = 1; $i <= $modulesCount; $i++) {
        $modules[] = Module::create(['curriculum_id' => $curr->id, 'name' => "М{$i}", 'order_number' => $i]);
    }
    $teacherUser = userWithRoleRRE('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $dept->id]);
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
    $studentUser = userWithRoleRRE('student');
    $student = Student::create([
        'user_id' => $studentUser->id,
        'group_id' => $group->id,
        'record_book_number' => 'RB' . rand(10000, 99999),
        'status' => 'active',
    ]);
    $wt = WorkType::create(['name' => 'ЛР', 'code' => 'LAB' . rand(100, 999)]);

    return compact('faculty', 'dept', 'disc', 'group', 'curr', 'modules', 'teacher', 'period', 'student', 'wt');
}

it('пересчитывает MP по модулю без весов (арифметика)', function () {
    $ctx = buildRatingContext(2);
    $s = $ctx['student'];
    $p = $ctx['period'];
    $m1 = $ctx['modules'][0];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];

    // Две работы в модуле: 8 и 6
    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 8, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);
    // вторую работу с другим work_type, чтобы UNIQUE не сработал
    $wt2 = WorkType::create(['name' => 'Тест', 'code' => 'TST' . rand(100, 999)]);
    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt2->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 6, 'date_assigned' => '2025-10-02', 'weeks_late' => 0]);

    app(RatingRecalculator::class)->recalculateForStudent($s, $p);

    $mr = ModuleRating::where('student_id', $s->id)->where('module_id', $m1->id)->first();
    expect($mr)->not->toBeNull();
    // (8 + 6) / 2 * 9 = 63
    expect((float) $mr->calculated_value)->toBe(63.0);
});

it('применяет штраф за просрочку в MP', function () {
    $ctx = buildRatingContext(1);
    $s = $ctx['student'];
    $p = $ctx['period'];
    $m1 = $ctx['modules'][0];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];

    // Одна работа с оценкой 10 и просрочкой 1 неделя
    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 10, 'date_assigned' => '2025-10-01', 'weeks_late' => 1]);

    app(RatingRecalculator::class)->recalculateForStudent($s, $p);

    $mr = ModuleRating::where('student_id', $s->id)->where('module_id', $m1->id)->first();
    // 10 * 0.9 * 9 = 81
    expect((float) $mr->calculated_value)->toBe(81.0);
});

it('применяет веса работ в MP', function () {
    $ctx = buildRatingContext(1);
    $s = $ctx['student'];
    $p = $ctx['period'];
    $m1 = $ctx['modules'][0];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];
    $curr = $ctx['curr'];

    $wt2 = WorkType::create(['name' => 'Тест', 'code' => 'TST' . rand(100, 999)]);

    // Веса работ: ЛР = 0.7, Тест = 0.3
    WeightCoefficient::create(['period_id' => $p->id, 'curriculum_id' => $curr->id, 'level' => 'work', 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'weight_value' => 0.7]);
    WeightCoefficient::create(['period_id' => $p->id, 'curriculum_id' => $curr->id, 'level' => 'work', 'module_id' => $m1->id, 'work_type_id' => $wt2->id, 'weight_value' => 0.3]);

    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 10, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);
    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt2->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 5, 'date_assigned' => '2025-10-02', 'weeks_late' => 0]);

    app(RatingRecalculator::class)->recalculateForStudent($s, $p);

    $mr = ModuleRating::where('student_id', $s->id)->where('module_id', $m1->id)->first();
    // (10*0.7 + 5*0.3) / 1 * 9 = 8.5 * 9 = 76.5
    expect((float) $mr->calculated_value)->toBe(76.5);
});

it('пересчитывает ИР по дисциплине', function () {
    $ctx = buildRatingContext(2);
    $s = $ctx['student'];
    $p = $ctx['period'];
    [$m1, $m2] = $ctx['modules'];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];
    $curr = $ctx['curr'];

    // Обе работы в обоих модулях, веса модулей 0.5/0.5
    WeightCoefficient::create(['period_id' => $p->id, 'curriculum_id' => $curr->id, 'level' => 'module', 'module_id' => $m1->id, 'weight_value' => 0.5]);
    WeightCoefficient::create(['period_id' => $p->id, 'curriculum_id' => $curr->id, 'level' => 'module', 'module_id' => $m2->id, 'weight_value' => 0.5]);

    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 10, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);
    Grade::create(['student_id' => $s->id, 'module_id' => $m2->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 5, 'date_assigned' => '2025-10-02', 'weeks_late' => 0]);

    app(RatingRecalculator::class)->recalculateForStudent($s, $p);

    $fr = FinalRating::where('student_id', $s->id)->where('curriculum_id', $curr->id)->first();
    expect($fr)->not->toBeNull();
    // MP1 = 90, MP2 = 45, ИР = 90*0.5 + 45*0.5 = 67.5
    expect((float) $fr->calculated_value)->toBe(67.5);
    expect($fr->is_admitted)->toBeTrue();
});

it('is_admitted = false, если есть MP = 0', function () {
    $ctx = buildRatingContext(2);
    $s = $ctx['student'];
    $p = $ctx['period'];
    [$m1, $m2] = $ctx['modules'];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];
    $curr = $ctx['curr'];

    // В m1 оценка 10, в m2 оценка 0
    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 10, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);
    Grade::create(['student_id' => $s->id, 'module_id' => $m2->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 0, 'date_assigned' => '2025-10-02', 'weeks_late' => 0]);

    app(RatingRecalculator::class)->recalculateForStudent($s, $p);

    $fr = FinalRating::where('student_id', $s->id)->where('curriculum_id', $curr->id)->first();
    expect($fr->is_admitted)->toBeFalse();
});

it('пересчитывает R_сов как среднее ИР по дисциплинам', function () {
    $ctx = buildRatingContext(2);
    $s = $ctx['student'];
    $p = $ctx['period'];
    [$m1] = $ctx['modules'];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];
    $curr = $ctx['curr'];
    $g = $ctx['group'];

    // Добавим вторую дисциплину в тот же период
    $disc2 = Discipline::create(['department_id' => $ctx['dept']->id, 'name' => 'Д2', 'code' => 'D2' . rand(1000, 9999)]);
    $curr2 = Curriculum::create(['discipline_id' => $disc2->id, 'group_id' => $g->id, 'course_number' => 2, 'semester_number' => 3]);
    $m2 = Module::create(['curriculum_id' => $curr2->id, 'name' => 'М1_2', 'order_number' => 1]);
    TeacherAssignment::create(['teacher_id' => $t->id, 'curriculum_id' => $curr2->id, 'period_id' => $p->id]);

    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 10, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);
    Grade::create(['student_id' => $s->id, 'module_id' => $m2->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 5, 'date_assigned' => '2025-10-02', 'weeks_late' => 0]);

    app(RatingRecalculator::class)->recalculateForStudent($s, $p);

    $cr = CumulativeRating::where('student_id', $s->id)->where('period_id', $p->id)->first();
    // ИР1 = 90, ИР2 = 45, R_сов = 67.5
    expect($cr)->not->toBeNull();
    expect((float) $cr->calculated_value)->toBe(67.5);
    expect($cr->disciplines_count)->toBe(2);
});

it('recalculateForGroup пересчитывает всех студентов группы', function () {
    $ctx = buildRatingContext(1);
    $p = $ctx['period'];
    $m1 = $ctx['modules'][0];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];
    $g = $ctx['group'];

    // Второй студент
    $u2 = userWithRoleRRE('student');
    $s2 = Student::create(['user_id' => $u2->id, 'group_id' => $g->id, 'record_book_number' => 'RB' . rand(10000, 99999), 'status' => 'active']);

    Grade::create(['student_id' => $ctx['student']->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 10, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);
    Grade::create(['student_id' => $s2->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 5, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);

    app(RatingRecalculator::class)->recalculateForGroup($g->id, $p);

    expect(ModuleRating::where('student_id', $ctx['student']->id)->count())->toBe(1);
    expect(ModuleRating::where('student_id', $s2->id)->count())->toBe(1);
});

it('пересчёт идемпотентен: повторный вызов не создаёт дубликатов', function () {
    $ctx = buildRatingContext(1);
    $s = $ctx['student'];
    $p = $ctx['period'];
    $m1 = $ctx['modules'][0];
    $wt = $ctx['wt'];
    $t = $ctx['teacher'];
    $curr = $ctx['curr'];

    Grade::create(['student_id' => $s->id, 'module_id' => $m1->id, 'work_type_id' => $wt->id, 'teacher_id' => $t->id, 'period_id' => $p->id, 'value' => 10, 'date_assigned' => '2025-10-01', 'weeks_late' => 0]);

    app(RatingRecalculator::class)->recalculateForStudent($s, $p);
    app(RatingRecalculator::class)->recalculateForStudent($s, $p);

    expect(ModuleRating::where('student_id', $s->id)->count())->toBe(1);
    expect(FinalRating::where('student_id', $s->id)->count())->toBe(1);
    expect(CumulativeRating::where('student_id', $s->id)->count())->toBe(1);
});
