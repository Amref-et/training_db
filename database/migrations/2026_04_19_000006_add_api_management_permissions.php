<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'View api management', 'slug' => 'api_management.view'],
            ['name' => 'Update api management', 'slug' => 'api_management.update'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['slug' => $permission['slug']], ['name' => $permission['name']]);
        }

        $adminRole = Role::query()->where('name', 'Admin')->first();
        if ($adminRole) {
            $permissionIds = Permission::query()
                ->whereIn('slug', collect($permissions)->pluck('slug'))
                ->pluck('id')
                ->all();

            $adminRole->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    public function down(): void
    {
        $slugs = ['api_management.view', 'api_management.update'];
        $permissionIds = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();

        if ($permissionIds !== []) {
            foreach (Role::query()->get() as $role) {
                $role->permissions()->detach($permissionIds);
            }
        }

        Permission::query()->whereIn('slug', $slugs)->delete();
    }
};
