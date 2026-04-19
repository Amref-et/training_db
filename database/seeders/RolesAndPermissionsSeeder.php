<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Admin' => [],
            'Editor' => [
                'dashboard.view',
                'pages.view', 'pages.create', 'pages.update', 'pages.delete',
                'menus.view', 'menus.create', 'menus.update', 'menus.delete',
                'appearance.view', 'appearance.update',
                'regions.view', 'regions.create', 'regions.update',
                'woredas.view', 'woredas.create', 'woredas.update',
                'zones.view', 'zones.create', 'zones.update', 'zones.delete',
                'organizations.view', 'organizations.create', 'organizations.update',
                'participants.view', 'participants.create', 'participants.update',
                'training_organizers.view', 'training_organizers.create', 'training_organizers.update',
                'trainings.view', 'trainings.create', 'trainings.update',
                'training_categories.view', 'training_categories.create', 'training_categories.update',
                'training_materials.view', 'training_materials.create', 'training_materials.update',
                'projects.view', 'projects.create', 'projects.update',
                'project_categories.view', 'project_categories.create', 'project_categories.update',
                'training_events.view', 'training_events.create', 'training_events.update',
                'training_rounds.view', 'training_rounds.create', 'training_rounds.update',
                'training_event_participants.view', 'training_event_participants.create', 'training_event_participants.update',
                'training_event_workshop_scores.view', 'training_event_workshop_scores.create', 'training_event_workshop_scores.update',
                'crud_builder.view', 'crud_builder.create', 'crud_builder.delete',
            ],
            'Viewer' => [
                'dashboard.view', 'pages.view', 'regions.view', 'woredas.view', 'zones.view', 'organizations.view',
                'participants.view', 'training_organizers.view', 'trainings.view', 'projects.view', 'project_categories.view', 'training_events.view', 'training_rounds.view',
                'training_categories.view',
                'training_materials.view',
                'training_event_participants.view', 'training_event_workshop_scores.view',
            ],
        ];

        foreach (PermissionRegistry::all() as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], ['name' => $permission['name']]);
        }

        $allPermissions = Permission::query()->pluck('id', 'slug');

        foreach ($roles as $roleName => $permissionSlugs) {
            $role = Role::updateOrCreate(['name' => $roleName]);
            $role->permissions()->sync($roleName === 'Admin' ? $allPermissions->values() : $allPermissions->only($permissionSlugs)->values());
        }
    }
}

