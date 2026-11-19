<?php

use App\Models\DeanEmployee;
use App\Models\Faculty;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);

    // Тестовые маршруты — по одному на каждый middleware.
    Route::middleware(['auth:sanctum', 'role:admin'])
        ->get('/api/v1/_test/admin-only', fn() => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'role:admin,dean'])
        ->get('/api/v1/_test/admin-dean', fn() => response()->json(['ok' => true]));

    Route::middleware(['auth:sanctum', 'faculty.scope'])
        ->get('/api/v1/_test/faculty-scope', function (Illuminate\Http\Request $request) {
            return response()->json([
                'faculty_scope' => $request->attributes->get('faculty_scope'),
            ]);
        });

    Route::middleware(['auth:sanctum', 'teacher.scope'])
        ->get('/api/v1/_test/teacher-scope', function (Illuminate\Http\Request $request) {
            return response()->json([
                'teacher_scope' => $request->attributes->get('teacher_scope'),
                'department_scope' => $request->attributes->get('department_scope'),
            ]);
        });
});

function makeUser(string $roleCode, array $overrides = []): User
{
    $role = Role::where('code', $roleCode)->firstOrFail();

    return User::create(array_merge([
        'login' => 'u_' . uniqid(),
        'password_hash' => Hash::make('Password1!'),
        'last_name' => 'Ф',
        'first_name' => 'И',
        'role_id' => $role->id,
    ], $overrides));
}

it('пропускает админа на role:admin', function () {
    $admin = makeUser('admin');
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/_test/admin-only')
        ->assertOk();
});

it('блокирует преподавателя на role:admin', function () {
    $teacher = makeUser('teacher');
    $this->actingAs($teacher, 'sanctum')
        ->getJson('/api/v1/_test/admin-only')
        ->assertStatus(403);
});

it('пропускает декана на role:admin,dean', function () {
    $dean = makeUser('dean');
    $this->actingAs($dean, 'sanctum')
        ->getJson('/api/v1/_test/admin-dean')
        ->assertOk();
});

it('блокирует студента на role:admin,dean', function () {
    $student = makeUser('student');
    $this->actingAs($student, 'sanctum')
        ->getJson('/api/v1/_test/admin-dean')
        ->assertStatus(403);
});

it('faculty.scope проставляет faculty_scope для декана', function () {
    $faculty = Faculty::create(['name' => 'ФКП', 'short_name' => 'ФКП']);
    $dean = makeUser('dean');
    DeanEmployee::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    $this->actingAs($dean, 'sanctum')
        ->getJson('/api/v1/_test/faculty-scope')
        ->assertOk()
        ->assertJsonPath('faculty_scope', $faculty->id);
});

it('faculty.scope возвращает 403, если декан не привязан к факультету', function () {
    $dean = makeUser('dean');
    $this->actingAs($dean, 'sanctum')
        ->getJson('/api/v1/_test/faculty-scope')
        ->assertStatus(403);
});

it('faculty.scope не ограничивает админа', function () {
    $admin = makeUser('admin');
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/_test/faculty-scope')
        ->assertOk()
        ->assertJsonPath('faculty_scope', null);
});

it('teacher.scope проставляет teacher_scope для преподавателя', function () {
    $faculty = Faculty::create(['name' => 'ФКП', 'short_name' => 'ФКП']);
    $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'ПИКС', 'short_name' => 'ПИКС']);
    $teacherUser = makeUser('teacher');
    $teacher = Teacher::create(['user_id' => $teacherUser->id, 'department_id' => $dept->id]);

    $this->actingAs($teacherUser, 'sanctum')
        ->getJson('/api/v1/_test/teacher-scope')
        ->assertOk()
        ->assertJsonPath('teacher_scope', $teacher->id)
        ->assertJsonPath('department_scope', $dept->id);
});

it('teacher.scope возвращает 403, если преподаватель не привязан', function () {
    $teacherUser = makeUser('teacher');
    $this->actingAs($teacherUser, 'sanctum')
        ->getJson('/api/v1/_test/teacher-scope')
        ->assertStatus(403);
});

it('возвращает 401 без токена', function () {
    $this->getJson('/api/v1/_test/admin-only')->assertStatus(401);
});
