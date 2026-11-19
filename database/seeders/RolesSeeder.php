<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'admin', 'name' => 'Администратор'],
            ['code' => 'dean',  'name' => 'Сотрудник деканата'],
            ['code' => 'teacher', 'name' => 'Преподаватель'],
            ['code' => 'student', 'name' => 'Студент'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['code' => $role['code']], $role);
        }
    }
}
