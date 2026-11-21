<?php

use App\Models\DeanEmployee;
use App\Models\Faculty;
use App\Models\Group;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleSt(string $roleCode): User
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

function makeGroup(int $enrollmentYearOffset = 0): Group
{
    $faculty = Faculty::create([
        'name' => 'Факультет ' . uniqid(),
        'short_name' => 'Ф' . rand(100, 999),
    ]);

    return Group::create([
        'faculty_id' => $faculty->id,
        'name' => 'Г' . rand(1000, 9999),
        'enrollment_year' => (int) date('Y') - $enrollmentYearOffset,
    ]);
}

it('админ создаёт студента и возвращает credentials', function () {
    $admin = makeUserWithRoleSt('admin');
    $group = makeGroup();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/students', [
            'group_id' => $group->id,
            'record_book_number' => '1234567',
            'last_name' => 'Коростелёва',
            'first_name' => 'Юлия',
            'middle_name' => 'Александровна',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.record_book_number', '1234567')
        ->assertJsonPath('data.last_name', 'Коростелёва')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['credentials' => ['login', 'temporary_password']]);

    // Юзер создан с must_change_password = true
    $login = $response->json('credentials.login');
    $user = User::where('login', $login)->first();
    expect($user)->not->toBeNull();
    expect($user->must_change_password)->toBeTrue();
    expect($user->role->code)->toBe('student');
});

it('не создаёт двух студентов с одинаковым record_book_number', function () {
    $admin = makeUserWithRoleSt('admin');
    $group = makeGroup();
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/students', [
            'group_id' => $group->id,
            'record_book_number' => '1234567',
            'last_name' => 'А',
            'first_name' => 'А',
        ])
        ->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/students', [
            'group_id' => $group->id,
            'record_book_number' => '1234567',
            'last_name' => 'Б',
            'first_name' => 'Б',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['record_book_number']);
});

it('декан видит студентов только своего факультета', function () {
    $faculty1 = Faculty::create(['name' => 'Ф1', 'short_name' => 'Ф1']);
    $faculty2 = Faculty::create(['name' => 'Ф2', 'short_name' => 'Ф2']);
    $group1 = Group::create(['faculty_id' => $faculty1->id, 'name' => 'G1', 'enrollment_year' => 2023]);
    $group2 = Group::create(['faculty_id' => $faculty2->id, 'name' => 'G2', 'enrollment_year' => 2023]);

    $admin = makeUserWithRoleSt('admin');
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group1->id,
        'record_book_number' => '1111111',
        'last_name' => 'Студент',
        'first_name' => 'Первый',
    ])->assertCreated();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group2->id,
        'record_book_number' => '2222222',
        'last_name' => 'Студент',
        'first_name' => 'Второй',
    ])->assertCreated();

    $deanUser = makeUserWithRoleSt('dean');
    DeanEmployee::create(['user_id' => $deanUser->id, 'faculty_id' => $faculty1->id]);

    $response = $this->actingAs($deanUser, 'sanctum')
        ->getJson('/api/v1/students');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.record_book_number', '1111111');
});

it('студент видит только свой профиль', function () {
    $admin = makeUserWithRoleSt('admin');
    $group = makeGroup();
    $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group->id,
        'record_book_number' => '3333333',
        'last_name' => 'Мой',
        'first_name' => 'Профиль',
    ])->json('data');

    $other = makeUserWithRoleSt('student');

    // Свой профиль — можно
    $meUser = User::where('login', $created['login'] ?? '')->first();
    // Создадим второго студента
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group->id,
        'record_book_number' => '4444444',
        'last_name' => 'Другой',
        'first_name' => 'Студент',
    ])->assertCreated();

    $student = Student::where('record_book_number', '3333333')->first();

    // Чужой профиль — 403
    $this->actingAs($other, 'sanctum')
        ->getJson("/api/v1/students/{$student->id}")
        ->assertStatus(403);
});

it('админ обновляет ФИО и статус студента', function () {
    $admin = makeUserWithRoleSt('admin');
    $group = makeGroup();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group->id,
        'record_book_number' => '5555555',
        'last_name' => 'Старое',
        'first_name' => 'Имя',
    ])->assertCreated();

    $student = Student::where('record_book_number', '5555555')->first();

    $response = $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/students/{$student->id}", [
            'last_name' => 'Новое',
            'status' => 'expelled',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.last_name', 'Новое')
        ->assertJsonPath('data.status', 'expelled');
});

it('админ удаляет студента вместе с user', function () {
    $admin = makeUserWithRoleSt('admin');
    $group = makeGroup();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group->id,
        'record_book_number' => '6666666',
        'last_name' => 'Удал',
        'first_name' => 'Удал',
    ])->assertCreated();

    $student = Student::where('record_book_number', '6666666')->first();
    $userId = $student->user_id;

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/students/{$student->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('students', ['id' => $student->id]);
    $this->assertDatabaseMissing('users', ['id' => $userId]);
});

it('не удаляет группу, если в ней есть студенты', function () {
    $admin = makeUserWithRoleSt('admin');
    $group = makeGroup();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group->id,
        'record_book_number' => '7777777',
        'last_name' => 'X',
        'first_name' => 'Y',
    ])->assertCreated();

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/groups/{$group->id}")
        ->assertStatus(409);
});

it('фильтрует по group_id и status', function () {
    $admin = makeUserWithRoleSt('admin');
    $g1 = makeGroup();
    $g2 = makeGroup();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $g1->id,
        'record_book_number' => '8888881',
        'last_name' => 'A',
        'first_name' => 'A',
    ]);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $g2->id,
        'record_book_number' => '8888882',
        'last_name' => 'B',
        'first_name' => 'B',
        'status' => 'expelled',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/v1/students?group_id={$g1->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/students?status=expelled')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('поиск по ФИО и record_book_number работает', function () {
    $admin = makeUserWithRoleSt('admin');
    $group = makeGroup();
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group->id,
        'record_book_number' => '9999991',
        'last_name' => 'Иванов',
        'first_name' => 'Иван',
    ]);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/students', [
        'group_id' => $group->id,
        'record_book_number' => '9999992',
        'last_name' => 'Петров',
        'first_name' => 'Пётр',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/students?search=иванов')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/students?search=9999992')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('преподаватель не может создать студента', function () {
    $teacher = makeUserWithRoleSt('teacher');
    $group = makeGroup();

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/students', [
            'group_id' => $group->id,
            'record_book_number' => '0000001',
            'last_name' => 'A',
            'first_name' => 'B',
        ])
        ->assertStatus(403);
});
