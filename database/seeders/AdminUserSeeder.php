<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();

        User::updateOrCreate(
            ['login' => 'admin'],
            [
                'password_hash' => Hash::make('Admin123!'),
                'last_name' => 'Администратор',
                'first_name' => 'Системы',
                'middle_name' => null,
                'role_id' => $adminRole->id,
                'is_blocked' => false,
                'must_change_password' => true,
            ]
        );
    }
}
