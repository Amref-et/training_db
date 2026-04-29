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
        $roleIds = Role::query()
            ->whereIn('name', ['Admin', 'Editor', 'Viewer'])
            ->pluck('id', 'name');

        $users = [
            [
                'email' => 'admin@example.com',
                'name' => 'System Administrator',
                'password' => 'password',
                'role' => 'Admin',
            ],
            [
                'email' => 'editor@example.com',
                'name' => 'Content Editor',
                'password' => 'password',
                'role' => 'Editor',
            ],
            [
                'email' => 'viewer@example.com',
                'name' => 'Reporting Viewer',
                'password' => 'password',
                'role' => 'Viewer',
            ],
        ];

        foreach ($users as $definition) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => Hash::make($definition['password']),
                ]
            );

            if (isset($roleIds[$definition['role']])) {
                $user->syncRoles([(int) $roleIds[$definition['role']]]);
            }
        }
    }
}
