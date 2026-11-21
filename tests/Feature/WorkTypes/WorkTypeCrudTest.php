<?php

use App\Models\Role;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

function makeUserWithRoleWT(string $roleCode): User
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

it('админ создаёт вид работы', function () {
    $admin = makeUserWithRoleWT('admin');

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/work-types', [
            'name' => 'Лабораторная работа',
            'code' => 'LAB',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'LAB');
});

it('не создаёт вид работы с дублирующимся code', function () {
    $admin = makeUserWithRoleWT('admin');
    WorkType::create(['name' => 'A', 'code' => 'LAB']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/work-types', [
            'name' => 'B',
            'code' => 'LAB',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('не создаёт вид работы без обязательных полей', function () {
    $admin = makeUserWithRoleWT('admin');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/work-types', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'code']);
});

it('студент видит список видов работ', function () {
    $student = makeUserWithRoleWT('student');
    WorkType::create(['name' => 'Лабораторная', 'code' => 'LAB']);
    WorkType::create(['name' => 'Контрольная', 'code' => 'TEST']);

    $this->actingAs($student, 'sanctum')
        ->getJson('/api/v1/work-types')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('ищет по name и code', function () {
    $admin = makeUserWithRoleWT('admin');
    WorkType::create(['name' => 'Лабораторная', 'code' => 'LAB']);
    WorkType::create(['name' => 'Контрольная', 'code' => 'TEST']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/work-types?search=лаб')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'LAB');
});

it('преподаватель не может создать вид работы', function () {
    $teacher = makeUserWithRoleWT('teacher');

    $this->actingAs($teacher, 'sanctum')
        ->postJson('/api/v1/work-types', [
            'name' => 'X',
            'code' => 'XXX',
        ])
        ->assertStatus(403);
});

it('админ обновляет вид работы', function () {
    $admin = makeUserWithRoleWT('admin');
    $wt = WorkType::create(['name' => 'Старое', 'code' => 'OLD']);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/work-types/{$wt->id}", ['name' => 'Новое'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Новое');
});

it('админ удаляет вид работы', function () {
    $admin = makeUserWithRoleWT('admin');
    $wt = WorkType::create(['name' => 'Удал', 'code' => 'DEL']);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/work-types/{$wt->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('work_types', ['id' => $wt->id]);
});

it('возвращает 401 без токена', function () {
    $this->getJson('/api/v1/work-types')->assertStatus(401);
});
