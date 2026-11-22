<?php

namespace Database\Seeders;

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
use App\Models\WeightCoefficient;
use App\Models\WorkType;
use App\Services\Rating\RatingRecalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // 1. Факультеты и кафедры
        // ============================================================
        $fkp = Faculty::create(['name' => 'Факультет компьютерного проектирования', 'short_name' => 'ФКП']);
        $fes = Faculty::create(['name' => 'Факультет экономики и спорта', 'short_name' => 'ФЭС']);

        $piks = Department::create(['faculty_id' => $fkp->id, 'name' => 'Проектирования информационно-компьютерных систем', 'short_name' => 'ПИКС']);
        $vt = Department::create(['faculty_id' => $fkp->id, 'name' => 'Вычислительных технологий', 'short_name' => 'ВТ']);
        $econom = Department::create(['faculty_id' => $fes->id, 'name' => 'Экономики', 'short_name' => 'ЭК']);

        // ============================================================
        // 2. Группы
        // ============================================================
        $g1 = Group::create(['faculty_id' => $fkp->id, 'name' => '514371', 'enrollment_year' => 2023]);
        $g2 = Group::create(['faculty_id' => $fkp->id, 'name' => '514372', 'enrollment_year' => 2023]);
        $g3 = Group::create(['faculty_id' => $fes->id, 'name' => '414371', 'enrollment_year' => 2023]);

        // ============================================================
        // 3. Дисциплины
        // ============================================================
        $rppba = Discipline::create(['department_id' => $piks->id, 'name' => 'Разработка программных приложений для бизнес-анализа', 'code' => 'РППБА']);
        $bd = Discipline::create(['department_id' => $vt->id, 'name' => 'Базы данных', 'code' => 'БД']);
        $math = Discipline::create(['department_id' => $vt->id, 'name' => 'Высшая математика', 'code' => 'МАТ']);

        // ============================================================
        // 4. Учебные планы + модули
        // ============================================================
        $curricula = [
            [$rppba, $g1, 2, 3],
            [$rppba, $g2, 2, 3],
            [$bd, $g1, 2, 3],
            [$bd, $g2, 2, 3],
            [$math, $g1, 2, 3],
            [$math, $g2, 2, 3],
        ];

        $modulesByCurriculum = [];

        foreach ($curricula as [$disc, $group, $course, $sem]) {
            $c = Curriculum::create([
                'discipline_id' => $disc->id,
                'group_id' => $group->id,
                'course_number' => $course,
                'semester_number' => $sem,
            ]);

            $modulesByCurriculum[$c->id] = [
                Module::create(['curriculum_id' => $c->id, 'name' => 'Модуль 1', 'order_number' => 1]),
                Module::create(['curriculum_id' => $c->id, 'name' => 'Модуль 2', 'order_number' => 2]),
            ];
        }

        // ============================================================
        // 5. Виды работ
        // ============================================================
        $lab = WorkType::create(['name' => 'Лабораторная работа', 'code' => 'LAB']);
        $test = WorkType::create(['name' => 'Контрольная работа', 'code' => 'TEST']);
        $exam = WorkType::create(['name' => 'Экзамен', 'code' => 'EXAM']);

        // ============================================================
        // 6. Преподаватели и декан
        // ============================================================
        $teacherRole = Role::where('code', 'teacher')->firstOrFail();
        $deanRole = Role::where('code', 'dean')->firstOrFail();
        $studentRole = Role::where('code', 'student')->firstOrFail();

        $ivanovUser = User::create([
            'login' => 'ivanov_ii',
            'password_hash' => Hash::make('Teacher123!'),
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'middle_name' => 'Иванович',
            'role_id' => $teacherRole->id,
            'must_change_password' => false,
        ]);
        $ivanov = Teacher::create(['user_id' => $ivanovUser->id, 'department_id' => $piks->id]);

        $petrovUser = User::create([
            'login' => 'petrov_pp',
            'password_hash' => Hash::make('Teacher123!'),
            'last_name' => 'Петров',
            'first_name' => 'Пётр',
            'middle_name' => 'Петрович',
            'role_id' => $teacherRole->id,
            'must_change_password' => false,
        ]);
        $petrov = Teacher::create(['user_id' => $petrovUser->id, 'department_id' => $vt->id]);

        $deanUser = User::create([
            'login' => 'sidorova_aa',
            'password_hash' => Hash::make('Dean1234!'),
            'last_name' => 'Сидорова',
            'first_name' => 'Анна',
            'middle_name' => 'Александровна',
            'role_id' => $deanRole->id,
            'must_change_password' => false,
        ]);
        DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $fkp->id]);

        // ============================================================
        // 7. Период + привязки + открытие
        // ============================================================
        $period = RatingPeriod::create([
            'faculty_id' => $fkp->id,
            'name' => 'Осенний семестр 2025/2026',
            'academic_year' => 2025,
            'semester' => 1,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-31',
            'status' => 'closed',
        ]);

        // Привязки преподавателей ко всем планам ФКП
        foreach (Curriculum::whereHas('group', fn($q) => $q->where('faculty_id', $fkp->id))->get() as $c) {
            $teacher = $c->discipline->department_id === $piks->id ? $ivanov : $petrov;
            TeacherAssignment::create([
                'teacher_id' => $teacher->id,
                'curriculum_id' => $c->id,
                'period_id' => $period->id,
            ]);
        }

        // Веса: модули 50/50, работы 60/40
        foreach (Curriculum::whereHas('group', fn($q) => $q->where('faculty_id', $fkp->id))->get() as $c) {
            $mods = $modulesByCurriculum[$c->id];

            foreach ($mods as $mod) {
                WeightCoefficient::create([
                    'period_id' => $period->id,
                    'curriculum_id' => $c->id,
                    'level' => 'module',
                    'module_id' => $mod->id,
                    'weight_value' => 0.5,
                ]);
                WeightCoefficient::create([
                    'period_id' => $period->id,
                    'curriculum_id' => $c->id,
                    'level' => 'work',
                    'module_id' => $mod->id,
                    'work_type_id' => $lab->id,
                    'weight_value' => 0.6,
                ]);
                WeightCoefficient::create([
                    'period_id' => $period->id,
                    'curriculum_id' => $c->id,
                    'level' => 'work',
                    'module_id' => $mod->id,
                    'work_type_id' => $test->id,
                    'weight_value' => 0.4,
                ]);
            }
        }

        // Утверждение весов
        WeightCoefficient::where('period_id', $period->id)->update([
            'approved_by' => $deanUser->id,
            'approved_at' => now(),
        ]);

        // ============================================================
        // 8. Студенты
        // ============================================================
        $studentNames = [
            ['Коростелёва', 'Юлия', 'Александровна', $g1],
            ['Сидоров', 'Алексей', 'Игоревич', $g1],
            ['Петрова', 'Мария', 'Сергеевна', $g1],
            ['Иванов', 'Дмитрий', 'Андреевич', $g2],
            ['Смирнова', 'Екатерина', 'Олеговна', $g2],
            ['Козлов', 'Никита', 'Владимирович', $g2],
            ['Экономистов', 'Павел', 'Петрович', $g3],
        ];

        $students = [];
        foreach ($studentNames as $i => [$last, $first, $middle, $group]) {
            $login = 'student_' . ($i + 1);
            $u = User::create([
                'login' => $login,
                'password_hash' => Hash::make('Student123!'),
                'last_name' => $last,
                'first_name' => $first,
                'middle_name' => $middle,
                'role_id' => $studentRole->id,
                'must_change_password' => false,
            ]);
            $s = Student::create([
                'user_id' => $u->id,
                'group_id' => $group->id,
                'record_book_number' => '10000' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'status' => 'active',
            ]);
            $students[] = $s;
        }

        // ============================================================
        // 9. Оценки (детерминированные)
        // ============================================================
        $gradeValues = [
            // Коростелёва
            0 => [9, 8, 10, 9, 8, 7],
            // Сидоров
            1 => [7, 6, 8, 7, 6, 7],
            // Петрова
            2 => [10, 9, 10, 10, 9, 10],
            // Иванов
            3 => [6, 7, 5, 6, 7, 6],
            // Смирнова
            4 => [8, 9, 8, 9, 8, 9],
            // Козлов
            5 => [5, 6, 7, 5, 6, 5],
            // Экономистов (другой факультет — оценки не ставим)
        ];

        foreach ($students as $i => $student) {
            if (!isset($gradeValues[$i])) {
                continue;
            }

            // Определяем curriculum'ы для группы студента
            $currList = Curriculum::where('group_id', $student->group_id)
                ->whereHas('group', fn($q) => $q->where('faculty_id', $fkp->id))
                ->get();

            $v = $gradeValues[$i];
            $idx = 0;

            foreach ($currList as $c) {
                $mods = $modulesByCurriculum[$c->id];
                $teacher = $c->discipline->department_id === $piks->id ? $ivanov : $petrov;

                foreach ($mods as $mod) {
                    // ЛР
                    Grade::create([
                        'student_id' => $student->id,
                        'module_id' => $mod->id,
                        'work_type_id' => $lab->id,
                        'teacher_id' => $teacher->id,
                        'period_id' => $period->id,
                        'value' => $v[$idx % count($v)],
                        'date_assigned' => '2025-10-15',
                        'weeks_late' => 0,
                    ]);
                    // Тест
                    Grade::create([
                        'student_id' => $student->id,
                        'module_id' => $mod->id,
                        'work_type_id' => $test->id,
                        'teacher_id' => $teacher->id,
                        'period_id' => $period->id,
                        'value' => $v[($idx + 1) % count($v)],
                        'date_assigned' => '2025-10-20',
                        'weeks_late' => 0,
                    ]);
                    $idx += 2;
                }
            }
        }

        // ============================================================
        // 10. Пересчёт рейтингов (синхронно)
        // ============================================================
        $recalc = app(RatingRecalculator::class);
        $recalc->recalculateForFaculty($fkp->id, $period);

        // ============================================================
        // 11. Закрываем период (2 модуля — BR-02 соблюдён)
        // ============================================================
        $period->update(['status' => 'closed']);

        // ============================================================
        // Вывод учётных данных
        // ============================================================
        $this->command->info('=== DEMO DATA CREATED ===');
        $this->command->info('');
        $this->command->info('ADMIN:');
        $this->command->info('  login: admin / password: Admin123! (если менял — свой)');
        $this->command->info('');
        $this->command->info('DEAN (ФКП):');
        $this->command->info('  login: sidorova_aa / password: Dean1234!');
        $this->command->info('');
        $this->command->info('TEACHERS:');
        $this->command->info('  login: ivanov_ii / password: Teacher123!');
        $this->command->info('  login: petrov_pp / password: Teacher123!');
        $this->command->info('');
        $this->command->info('STUDENTS:');
        foreach ($students as $i => $s) {
            $this->command->info('  login: student_' . ($i + 1) . ' / password: Student123! — ' . $s->user->last_name);
        }
        $this->command->info('');
        $this->command->info('PERIOD ID: ' . $period->id);
        $this->command->info('FACULTY ID (ФКП): ' . $fkp->id);
        $this->command->info('GROUP ID (514371): ' . $g1->id);
        $this->command->info('STUDENT ID (Коростелёва): ' . $students[0]->id);
    }
}
