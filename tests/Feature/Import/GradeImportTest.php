<?php

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Discipline;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\Group;
use App\Models\ImportLog;
use App\Models\Module;
use App\Models\RatingPeriod;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);

    $this->faculty = Faculty::create(['name' => 'ФКП', 'short_name' => 'ФКП']);
    $this->dept = Department::create(['faculty_id' => $this->faculty->id, 'name' => 'ПИКС', 'short_name' => 'ПИКС']);
    $this->group = Group::create(['faculty_id' => $this->faculty->id, 'name' => '514371', 'enrollment_year' => 2023]);
    $this->discipline = Discipline::create(['department_id' => $this->dept->id, 'name' => 'РППБА', 'code' => 'РППБА']);
    $this->curriculum = Curriculum::create([
        'discipline_id' => $this->discipline->id,
        'group_id' => $this->group->id,
        'course_number' => 2,
        'semester_number' => 3,
    ]);
    $this->module = Module::create(['curriculum_id' => $this->curriculum->id, 'name' => 'М1', 'order_number' => 1]);
    $this->workType = WorkType::create(['name' => 'Лабораторная', 'code' => 'LAB']);
    $this->period = RatingPeriod::create([
        'faculty_id' => $this->faculty->id,
        'name' => 'Осень 2025',
        'academic_year' => 2025,
        'semester' => 1,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
        'status' => 'open',
    ]);

    $this->teacherUser = User::create([
        'login' => 'teacher1',
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'Иванов',
        'first_name' => 'Иван',
        'role_id' => Role::where('code', 'teacher')->first()->id,
    ]);
    $this->teacher = Teacher::create([
        'user_id' => $this->teacherUser->id,
        'department_id' => $this->dept->id,
    ]);
    TeacherAssignment::create([
        'teacher_id' => $this->teacher->id,
        'curriculum_id' => $this->curriculum->id,
        'period_id' => $this->period->id,
    ]);

    // Студенты
    $this->students = [];
    foreach (['100001', '100002', '100003'] as $rb) {
        $su = User::create([
            'login' => 's_' . $rb,
            'password_hash' => Hash::make('P1!'),
            'last_name' => 'Студентов',
            'first_name' => 'С',
            'role_id' => Role::where('code', 'student')->first()->id,
        ]);
        $this->students[] = Student::create([
            'user_id' => $su->id,
            'group_id' => $this->group->id,
            'record_book_number' => $rb,
            'status' => 'active',
        ]);
    }

    $this->admin = User::create([
        'login' => 'admin',
        'password_hash' => Hash::make('Admin123!'),
        'last_name' => 'Админ',
        'first_name' => 'С',
        'role_id' => Role::where('code', 'admin')->first()->id,
    ]);
});

function makeGradeCsv(array $lines): UploadedFile
{
    $content = "\xEF\xBB\xBF" . implode("\n", $lines) . "\n";
    return UploadedFile::fake()->createWithContent('grades.csv', $content);
}

it('успешно импортирует оценки', function () {
    $csv = makeGradeCsv([
        'record_book_number;value;weeks_late',
        '100001;9;0',
        '100002;8;0',
        '100003;7;2',
    ]);

    $response = $this->actingAs($this->teacherUser, 'sanctum')
        ->post('/api/v1/import/grades', [
            'file' => $csv,
            'module_id' => $this->module->id,
            'work_type_id' => $this->workType->id,
            'period_id' => $this->period->id,
            'date_assigned' => '2025-10-15',
        ]);

    $response->assertOk()
        ->assertJsonPath('saved', 3)
        ->assertJsonPath('failed', 0)
        ->assertJsonPath('status', 'success');

    expect(Grade::count())->toBe(3);
    expect((int) Grade::where('student_id', $this->students[0]->id)->value('value'))->toBe(9);
    expect((int) Grade::where('student_id', $this->students[2]->id)->value('weeks_late'))->toBe(2);
});

it('возвращает ошибки по строкам', function () {
    $csv = makeGradeCsv([
        'record_book_number;value;weeks_late',
        '100001;9;0',          // ок
        'НЕТ_ТАКОГО;8;0',      // студент не найден
        '100002;11;0',         // оценка > 10
        '100003;-1;0',         // оценка < 0
        '100003;7;-2',         // просрочка < 0
    ]);

    $response = $this->actingAs($this->teacherUser, 'sanctum')
        ->post('/api/v1/import/grades', [
            'file' => $csv,
            'module_id' => $this->module->id,
            'work_type_id' => $this->workType->id,
            'period_id' => $this->period->id,
            'date_assigned' => '2025-10-15',
        ]);

    $response->assertOk()
        ->assertJsonPath('saved', 1)
        ->assertJsonPath('failed', 4);
    expect($response->json('errors'))->toHaveCount(4);
});

it('не импортирует в закрытом периоде', function () {
    $this->period->update(['status' => 'closed']);

    $csv = makeGradeCsv([
        'record_book_number;value;weeks_late',
        '100001;9;0',
    ]);

    $response = $this->actingAs($this->teacherUser, 'sanctum')
        ->post('/api/v1/import/grades', [
            'file' => $csv,
            'module_id' => $this->module->id,
            'work_type_id' => $this->workType->id,
            'period_id' => $this->period->id,
            'date_assigned' => '2025-10-15',
        ]);

    $response->assertOk()
        ->assertJsonPath('saved', 0)
        ->assertJsonPath('failed', 1);

    expect(Grade::count())->toBe(0);
});

it('не импортирует, если преподаватель не привязан к дисциплине', function () {
    TeacherAssignment::where('teacher_id', $this->teacher->id)->delete();

    $csv = makeGradeCsv([
        'record_book_number;value;weeks_late',
        '100001;9;0',
    ]);

    $response = $this->actingAs($this->teacherUser, 'sanctum')
        ->post('/api/v1/import/grades', [
            'file' => $csv,
            'module_id' => $this->module->id,
            'work_type_id' => $this->workType->id,
            'period_id' => $this->period->id,
            'date_assigned' => '2025-10-15',
        ]);

    $response->assertOk()
        ->assertJsonPath('saved', 0)
        ->assertJsonPath('failed', 1);

    expect(Grade::count())->toBe(0);
});

it('перезаписывает существующую оценку', function () {
    Grade::create([
        'student_id' => $this->students[0]->id,
        'module_id' => $this->module->id,
        'work_type_id' => $this->workType->id,
        'teacher_id' => $this->teacher->id,
        'period_id' => $this->period->id,
        'value' => 5,
        'date_assigned' => '2025-10-01',
        'weeks_late' => 0,
    ]);

    $csv = makeGradeCsv([
        'record_book_number;value;weeks_late',
        '100001;10;0',
    ]);

    $this->actingAs($this->teacherUser, 'sanctum')
        ->post('/api/v1/import/grades', [
            'file' => $csv,
            'module_id' => $this->module->id,
            'work_type_id' => $this->workType->id,
            'period_id' => $this->period->id,
            'date_assigned' => '2025-10-15',
        ])
        ->assertOk()
        ->assertJsonPath('saved', 1);

    expect(Grade::count())->toBe(1);
    expect((int) Grade::first()->value)->toBe(10);
});

it('создаёт запись в import_logs с типом grades', function () {
    $csv = makeGradeCsv([
        'record_book_number;value;weeks_late',
        '100001;9;0',
    ]);

    $this->actingAs($this->teacherUser, 'sanctum')
        ->post('/api/v1/import/grades', [
            'file' => $csv,
            'module_id' => $this->module->id,
            'work_type_id' => $this->workType->id,
            'period_id' => $this->period->id,
            'date_assigned' => '2025-10-15',
        ]);

    expect(ImportLog::count())->toBe(1);
    expect(ImportLog::first()->import_type)->toBe('grades');
    expect(ImportLog::first()->saved_count)->toBe(1);
});

it('студент не может импортировать оценки', function () {
    $csv = makeGradeCsv(['record_book_number;value', '100001;9']);

    $this->actingAs($this->students[0]->user, 'sanctum')
        ->post('/api/v1/import/grades', [
            'file' => $csv,
            'module_id' => $this->module->id,
            'work_type_id' => $this->workType->id,
            'period_id' => $this->period->id,
            'date_assigned' => '2025-10-15',
        ])
        ->assertStatus(403);
});

it('требует обязательные поля', function () {
    $csv = makeGradeCsv(['record_book_number;value', '100001;9']);

    $this->actingAs($this->teacherUser, 'sanctum')
        ->post('/api/v1/import/grades', ['file' => $csv])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['module_id', 'work_type_id', 'period_id', 'date_assigned']);
});
