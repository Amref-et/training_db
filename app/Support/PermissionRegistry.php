<?php

namespace App\Support;

class PermissionRegistry
{
    public const MATRIX = [
        'dashboard' => ['view'],
        'pages' => ['view', 'create', 'update', 'delete'],
        'menus' => ['view', 'create', 'update', 'delete'],
        'appearance' => ['view', 'update'],
        'api_management' => ['view', 'update'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'crud_builder' => ['view', 'create', 'delete'],
        'regions' => ['view', 'create', 'update', 'delete'],
        'woredas' => ['view', 'create', 'update', 'delete'],
        'zones' => ['view', 'create', 'update', 'delete'],
        'organizations' => ['view', 'create', 'update', 'delete'],
        'participants' => ['view', 'create', 'update', 'delete'],
        'training_organizers' => ['view', 'create', 'update', 'delete'],
        'trainings' => ['view', 'create', 'update', 'delete'],
        'training_categories' => ['view', 'create', 'update', 'delete'],
        'training_materials' => ['view', 'create', 'update', 'delete'],
        'projects' => ['view', 'create', 'update', 'delete'],
        'project_categories' => ['view', 'create', 'update', 'delete'],
        'training_events' => ['view', 'create', 'update', 'delete'],
        'training_rounds' => ['view', 'create', 'update', 'delete'],
        'training_event_participants' => ['view', 'create', 'update', 'delete'],
        'training_event_workshop_scores' => ['view', 'create', 'update', 'delete'],
    ];

    public static function all(): array
    {
        $permissions = [];

        foreach (self::MATRIX as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => ucfirst($action).' '.str_replace('_', ' ', $resource),
                    'slug' => $resource.'.'.$action,
                    'resource' => $resource,
                    'action' => $action,
                ];
            }
        }

        return $permissions;
    }

    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::all() as $permission) {
            $grouped[$permission['resource']][] = $permission;
        }

        return $grouped;
    }
}

