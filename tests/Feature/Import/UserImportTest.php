<?php

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Group;
use App\Models\ImportLog;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);

    // Базовые справочники для импорта
    $this->faculty = Faculty::create(['name' => 'ФКП', 'short_name' => 'ФКП']);
    $this->dept = Department::create([
        'faculty_id' => $this->faculty->id,
        'name' => 'ПИКС',
        'short_name' => 'ПИКС',
    ]);
    $this->group = Group::create([
        'faculty_id' => $this->faculty->id,
        'name' => '514371',
        'enrollment_year' => 2023,
    ]);

    $this->admin = User::create([
        'login' => 'admin',
        'password_hash' => Hash::make('Admin123!'),
        'last_name' => 'Админ',
        'first_name' => 'Системы',
        'role_id' => Role::where('code', 'admin')->first()->id,
    ]);
});

function makeCsv(array $lines): UploadedFile
{
    $content = "\xEF\xBB\xBF" . implode("\n", $lines) . "\n";
    return UploadedFile::fake()->createWithContent('users.csv', $content);
}

it('успешно импортирует студентов, преподавателей и деканов', function () {
    $csv = makeCsv([
        'role;last_name;first_name;middle_name;group_name;record_book_number;department_name;faculty_name',
        'student;Коростелёва;Юлия;Александровна;514371;100001;;',
        'teacher;Иванов;Иван;Иванович;;;ПИКС;',
        'dean;Сидорова;Анна;Александровна;;;;ФКП',
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->post('/api/v1/import/users', ['file' => $csv]);

    $response->assertOk()
        ->assertJsonPath('saved', 3)
        ->assertJsonPath('failed', 0)
        ->assertJsonPath('status', 'success');

    expect(User::where('last_name', 'Коростелёва')->exists())->toBeTrue();
    expect(User::where('last_name', 'Иванов')->exists())->toBeTrue();
    expect(User::where('last_name', 'Сидорова')->exists())->toBeTrue();

    expect(Student::count())->toBe(1);
    expect(Teacher::count())->toBe(1);
});

it('возвращает ошибки по строкам', function () {
    $csv = makeCsv([
        'role;last_name;first_name;middle_name;group_name;record_book_number;department_name;faculty_name',
        'student;Петров;Пётр;;514371;100002;;',           // ок
        'student;Сидоров;Сидор;;НЕТ_ГРУППЫ;100003;;',   // группа не найдена
        'teacher;Иванов;Иван;Иванович;;;НЕТ_КАФ;',      // кафедра не найдена
        'admin;Плохой;Плохой;;;;;',                     // роль admin не разрешена
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->post('/api/v1/import/users', ['file' => $csv]);

    $response->assertOk()
        ->assertJsonPath('saved', 1)
        ->assertJsonPath('failed', 3)
        ->assertJsonPath('status', 'partial');

    expect($response->json('errors'))->toHaveCount(3);
});

it('не импортирует студента с дублирующейся зачёткой', function () {
    $studentUser = User::create([
        'login' => 'exists',
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'Старый',
        'first_name' => 'С',
        'role_id' => Role::where('code', 'student')->first()->id,
    ]);
    Student::create([
        'user_id' => $studentUser->id,
        'group_id' => $this->group->id,
        'record_book_number' => '100001',
        'status' => 'active',
    ]);

    $csv = makeCsv([
        'role;last_name;first_name;middle_name;group_name;record_book_number;department_name;faculty_name',
        'student;Дубль;Дубль;;514371;100001;;',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->post('/api/v1/import/users', ['file' => $csv])
        ->assertOk()
        ->assertJsonPath('saved', 0)
        ->assertJsonPath('failed', 1);
});

it('только админ может импортировать', function () {
    $teacher = User::create([
        'login' => 't1',
        'password_hash' => Hash::make('P1!'),
        'last_name' => 'Т',
        'first_name' => 'Т',
        'role_id' => Role::where('code', 'teacher')->first()->id,
    ]);

    $csv = makeCsv(['role;last_name;first_name']);

    $this->actingAs($teacher, 'sanctum')
        ->post('/api/v1/import/users', ['file' => $csv])
        ->assertStatus(403);
});

it('создаёт запись в import_logs', function () {
    $csv = makeCsv([
        'role;last_name;first_name;middle_name;group_name;record_book_number;department_name;faculty_name',
        'student;Коростелёва;Юлия;Александровна;514371;100001;;',
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->post('/api/v1/import/users', ['file' => $csv]);

    expect(ImportLog::count())->toBe(1);
    expect(ImportLog::first()->import_type)->toBe('users');
    expect(ImportLog::first()->saved_count)->toBe(1);
    expect(ImportLog::first()->status)->toBe('success');
});

it('шаблон users отдаётся в CSV', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/import/users/template?format=csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
});

it('шаблон users отдаётся в XLSX', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/import/users/template?format=xlsx');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('шаблон grades отдаётся', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/import/grades/template?format=csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});
