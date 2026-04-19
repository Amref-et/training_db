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
        $admin = User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Admin User', 'password' => Hash::make('password')]);
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $admin->syncRoles([$adminRole->id]);
    }
}
