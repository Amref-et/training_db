<?php

namespace App\Support;

use App\Models\AdminSidebarMenuItem;
use App\Models\AdminSidebarMenuSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminSidebarMenuDefaults
{
    public static function seedSuggested(bool $replace = false): void
    {
        DB::transaction(function () use ($replace): void {
            if ($replace) {
                AdminSidebarMenuItem::query()->delete();
            } elseif (AdminSidebarMenuItem::query()->exists()) {
                return;
            }

            foreach (self::structure() as $index => $group) {
                $sectionName = $group['section_title'] ?? 'General';
                $sectionOrder = (int) ($group['section_sort_order'] ?? 0);
                $sectionId = self::resolveSectionId($sectionName, $sectionOrder);

                $parent = AdminSidebarMenuItem::query()->create([
                    'title' => $group['title'],
                    'icon' => $group['icon'] ?? null,
                    'route_name' => $group['route_name'] ?? null,
                    'url' => $group['url'] ?? null,
                    'target' => $group['target'] ?? '_self',
                    'required_permission' => $group['required_permission'] ?? null,
                    'section_id' => $sectionId,
                    'section_title' => $sectionName,
                    'section_sort_order' => $sectionOrder,
                    'parent_id' => null,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ]);

                foreach (($group['children'] ?? []) as $childIndex => $child) {
                    $childSectionName = $child['section_title'] ?? $sectionName;
                    $childSectionOrder = (int) ($child['section_sort_order'] ?? $sectionOrder);
                    $childSectionId = self::resolveSectionId($childSectionName, $childSectionOrder) ?: $sectionId;

                    AdminSidebarMenuItem::query()->create([
                        'title' => $child['title'],
                        'icon' => $child['icon'] ?? null,
                        'route_name' => $child['route_name'] ?? null,
                        'url' => $child['url'] ?? null,
                        'target' => $child['target'] ?? '_self',
                        'required_permission' => $child['required_permission'] ?? ($group['required_permission'] ?? null),
                        'section_id' => $childSectionId,
                        'section_title' => $childSectionName,
                        'section_sort_order' => $childSectionOrder,
                        'parent_id' => $parent->id,
                        'sort_order' => ($childIndex + 1) * 10,
                        'is_active' => true,
                    ]);
                }
            }
        });
    }

    public static function structure(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'icon' => 'chart-line',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'dashboard.view',
                'children' => [
                    ['title' => 'Main Dashboard', 'route_name' => 'admin.dashboard'],
                ],
            ],
            [
                'title' => 'CMS Pages',
                'icon' => 'file-text',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'pages.view',
                'children' => [
                    ['title' => 'All Pages', 'route_name' => 'admin.pages.index'],
                    ['title' => 'Add New Page', 'route_name' => 'admin.pages.create', 'required_permission' => 'pages.create'],
                    ['title' => 'Page Categories', 'url' => '#'],
                    ['title' => 'Page Settings', 'url' => '#'],
                ],
            ],
            [
                'title' => 'Appearance',
                'icon' => 'palette',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'appearance.view',
                'children' => [
                    ['title' => 'Theme Settings', 'route_name' => 'admin.appearance.edit'],
                    ['title' => 'Layout Builder', 'route_name' => 'admin.appearance.edit'],
                    ['title' => 'Menu Manager', 'route_name' => 'admin.menus.index', 'required_permission' => 'menus.view'],
                    ['title' => 'Header & Footer', 'route_name' => 'admin.appearance.edit'],
                    ['title' => 'Custom CSS', 'route_name' => 'admin.appearance.custom-css'],
                    ['title' => 'Custom JS', 'route_name' => 'admin.appearance.custom-js'],
                    ['title' => 'Env Settings', 'route_name' => 'admin.settings.env.edit'],
                ],
            ],
            [
                'title' => 'API Management',
                'icon' => 'cloud-arrow-up',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'api_management.view',
                'children' => [
                    ['title' => 'API Dashboard', 'route_name' => 'admin.api-management.index'],
                    ['title' => 'DHIS2 Integration', 'route_name' => 'admin.api-management.index', 'required_permission' => 'api_management.update'],
                ],
            ],
            [
                'title' => 'Users',
                'icon' => 'users',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'users.view',
                'children' => [
                    ['title' => 'All Users', 'route_name' => 'admin.users.index'],
                    ['title' => 'Add New User', 'route_name' => 'admin.users.create', 'required_permission' => 'users.create'],
                    ['title' => 'User Profile Fields', 'url' => '#'],
                    ['title' => 'User Activity Log', 'route_name' => 'admin.user-activity-logs.index'],
                ],
            ],
            [
                'title' => 'Roles & Permissions',
                'icon' => 'shield',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'roles.view',
                'children' => [
                    ['title' => 'All Roles', 'route_name' => 'admin.roles.index'],
                    ['title' => 'Create Role', 'route_name' => 'admin.roles.create', 'required_permission' => 'roles.create'],
                    ['title' => 'Permission Matrix', 'route_name' => 'admin.roles.index'],
                ],
            ],
            [
                'title' => 'Menu Management',
                'icon' => 'menu',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'menus.view',
                'children' => [
                    ['title' => 'Main Menu', 'route_name' => 'admin.menus.index'],
                    ['title' => 'Sidebar Menu', 'route_name' => 'admin.sidebar-menus.index'],
                    ['title' => 'Footer Menu', 'route_name' => 'admin.menus.index'],
                    ['title' => 'Mobile Menu', 'route_name' => 'admin.menus.index'],
                ],
            ],
            [
                'title' => 'CRUD Builder',
                'icon' => 'hammer',
                'section_title' => 'Core',
                'section_sort_order' => 10,
                'required_permission' => 'crud_builder.view',
                'children' => [
                    ['title' => 'Create New Entity', 'route_name' => 'admin.crud-builders.create', 'required_permission' => 'crud_builder.create'],
                    ['title' => 'Manage Entities', 'route_name' => 'admin.crud-builders.index'],
                    ['title' => 'Field Builder', 'route_name' => 'admin.crud-builders.create', 'required_permission' => 'crud_builder.create'],
                    ['title' => 'Form Builder', 'route_name' => 'admin.crud-builders.create', 'required_permission' => 'crud_builder.create'],
                    ['title' => 'Generate CRUD', 'route_name' => 'admin.crud-builders.create', 'required_permission' => 'crud_builder.create'],
                ],
            ],
            [
                'title' => 'Regions',
                'icon' => 'map',
                'section_title' => 'Reference Data',
                'section_sort_order' => 20,
                'required_permission' => 'regions.view',
                'children' => [
                    ['title' => 'All Regions', 'route_name' => 'admin.regions.index'],
                    ['title' => 'Add Region', 'route_name' => 'admin.regions.create', 'required_permission' => 'regions.create'],
                    ['title' => 'Region Hierarchy', 'route_name' => 'admin.regions.index'],
                    ['title' => 'Region Settings', 'route_name' => 'admin.regions.index'],
                ],
            ],
            [
                'title' => 'Woredas (Districts)',
                'icon' => 'map-pin',
                'section_title' => 'Reference Data',
                'section_sort_order' => 20,
                'required_permission' => 'woredas.view',
                'children' => [
                    ['title' => 'All Woredas', 'route_name' => 'admin.woredas.index'],
                    ['title' => 'Add Woreda', 'route_name' => 'admin.woredas.create', 'required_permission' => 'woredas.create'],
                    ['title' => 'Assign to Region', 'route_name' => 'admin.woredas.index'],
                    ['title' => 'Woreda Settings', 'route_name' => 'admin.woredas.index'],
                ],
            ],
            [
                'title' => 'Zones',
                'icon' => 'geo',
                'section_title' => 'Reference Data',
                'section_sort_order' => 20,
                'required_permission' => 'zones.view',
                'children' => [
                    ['title' => 'All Zones', 'route_name' => 'admin.zones.index'],
                    ['title' => 'Add Zone', 'route_name' => 'admin.zones.create', 'required_permission' => 'zones.create'],
                    ['title' => 'Zone Settings', 'route_name' => 'admin.zones.index'],
                ],
            ],
            [
                'title' => 'Organizations',
                'icon' => 'building',
                'section_title' => 'Reference Data',
                'section_sort_order' => 20,
                'required_permission' => 'organizations.view',
                'children' => [
                    ['title' => 'All Organizations', 'route_name' => 'admin.organizations.index'],
                    ['title' => 'Add Organization', 'route_name' => 'admin.organizations.create', 'required_permission' => 'organizations.create'],
                    ['title' => 'Zone List', 'route_name' => 'admin.zones.index', 'required_permission' => 'zones.view'],
                    ['title' => 'Organization Types', 'route_name' => 'admin.organizations.index'],
                    ['title' => 'Organization Hierarchy', 'route_name' => 'admin.organizations.index'],
                    ['title' => 'Organization Contacts', 'route_name' => 'admin.organizations.index'],
                ],
            ],
            [
                'title' => 'Projects',
                'icon' => 'users-cog',
                'section_title' => 'Reference Data',
                'section_sort_order' => 20,
                'required_permission' => 'training_organizers.view',
                'children' => [
                    ['title' => 'All Projects', 'route_name' => 'admin.training-organizers.index'],
                    ['title' => 'Add Project', 'route_name' => 'admin.training-organizers.create', 'required_permission' => 'training_organizers.create'],
                    ['title' => 'Project Directory', 'route_name' => 'admin.training-organizers.index'],
                    ['title' => 'Project Trainings', 'route_name' => 'admin.training-events.index', 'required_permission' => 'training_events.view'],
                    ['title' => 'Project Performance', 'route_name' => 'admin.training-events.grouped', 'required_permission' => 'training_events.view'],
                ],
            ],
            [
                'title' => 'Trainings',
                'icon' => 'book',
                'section_title' => 'Training Operations',
                'section_sort_order' => 30,
                'required_permission' => 'trainings.view',
                'children' => [
                    ['title' => 'All Trainings', 'route_name' => 'admin.trainings.index'],
                    ['title' => 'Create Training', 'route_name' => 'admin.trainings.create', 'required_permission' => 'trainings.create'],
                    ['title' => 'Training Categories', 'route_name' => 'admin.trainingcategories.index', 'required_permission' => 'training_categories.view'],
                    ['title' => 'Training Curriculum', 'route_name' => 'admin.trainings.index'],
                    ['title' => 'Training Materials', 'route_name' => 'admin.trainingmaterials.index', 'required_permission' => 'training_materials.view'],
                    ['title' => 'Training Templates', 'route_name' => 'admin.trainings.index'],
                ],
            ],
            [
                'title' => 'Projects',
                'icon' => 'target',
                'section_title' => 'Training Operations',
                'section_sort_order' => 30,
                'required_permission' => 'projects.view',
                'children' => [
                    ['title' => 'All Projects', 'route_name' => 'admin.projects.index'],
                    ['title' => 'Create Project', 'route_name' => 'admin.projects.create', 'required_permission' => 'projects.create'],
                    ['title' => 'Project Categories', 'route_name' => 'admin.project-categories.index', 'required_permission' => 'project_categories.view'],
                    ['title' => 'Project Budget', 'route_name' => 'admin.projects.index'],
                    ['title' => 'Project Timeline', 'route_name' => 'admin.projects.index'],
                    ['title' => 'Project Stakeholders', 'route_name' => 'admin.projects.index'],
                    ['title' => 'Project Reports', 'route_name' => 'admin.projects.index'],
                ],
            ],
            [
                'title' => 'Training Events',
                'icon' => 'calendar',
                'section_title' => 'Training Operations',
                'section_sort_order' => 30,
                'required_permission' => 'training_events.view',
                'children' => [
                    ['title' => 'All Events', 'route_name' => 'admin.training-events.index'],
                    ['title' => 'Create Event', 'route_name' => 'admin.training-events.create', 'required_permission' => 'training_events.create'],
                    ['title' => 'Event Calendar View', 'route_name' => 'admin.training-events-calendar.index'],
                    ['title' => 'Event by Training', 'route_name' => 'admin.training-events.grouped'],
                    ['title' => 'Event by Organizer', 'route_name' => 'admin.training-events.grouped'],
                    ['title' => 'Event by Region/Woreda', 'route_name' => 'admin.training-events.grouped'],
                    ['title' => 'Event Status Management', 'route_name' => 'admin.training-events.index'],
                ],
            ],
            [
                'title' => 'Event Participants',
                'icon' => 'user-check',
                'section_title' => 'Training Operations',
                'section_sort_order' => 30,
                'required_permission' => 'training_event_participants.view',
                'children' => [
                    ['title' => 'All Participants', 'route_name' => 'admin.training-event-participants.index'],
                    ['title' => 'Enroll Participant', 'route_name' => 'admin.training-event-participants.create', 'required_permission' => 'training_event_participants.create'],
                    ['title' => 'Bulk Enrollment', 'url' => '/admin/training-workflow?step=2', 'required_permission' => 'training_events.view'],
                    ['title' => 'Participant List by Event', 'route_name' => 'admin.training-events.grouped', 'required_permission' => 'training_events.view'],
                    ['title' => 'Participant Unique ID Generator', 'route_name' => 'admin.participants.create', 'required_permission' => 'participants.create'],
                    ['title' => 'Participant History', 'route_name' => 'admin.participants.index', 'required_permission' => 'participants.view'],
                    ['title' => 'Participant Reports', 'route_name' => 'admin.training-workflow.index', 'required_permission' => 'training_events.view'],
                ],
            ],
            [
                'title' => 'Workshop Scores',
                'icon' => 'clipboard',
                'section_title' => 'Training Operations',
                'section_sort_order' => 30,
                'required_permission' => 'training_event_workshop_scores.view',
                'children' => [
                    ['title' => 'Enter Pre/Post Scores', 'url' => '/admin/training-workflow?step=3', 'required_permission' => 'training_event_workshop_scores.update'],
                    ['title' => 'View Scores by Event', 'route_name' => 'admin.training-event-workshop-scores.index'],
                    ['title' => 'View Scores by Participant', 'route_name' => 'admin.training-event-workshop-scores.index'],
                    ['title' => 'Workshop Score Sheets', 'route_name' => 'admin.training-event-workshop-scores.index'],
                    ['title' => 'Score Validation', 'route_name' => 'admin.training-event-workshop-scores.index'],
                    ['title' => 'Score Reports', 'url' => '/admin/training-workflow?step=4', 'required_permission' => 'training_events.view'],
                    ['title' => 'Final Score Calculator', 'url' => '/admin/training-workflow?step=4', 'required_permission' => 'training_events.view'],
                ],
            ],
            [
                'title' => 'Grouped Training Events',
                'icon' => 'layers',
                'section_title' => 'Training Operations',
                'section_sort_order' => 30,
                'required_permission' => 'training_events.view',
                'children' => [
                    ['title' => 'View Grouped Events (by Training)', 'route_name' => 'admin.training-events.grouped'],
                    ['title' => 'Create Event Group', 'route_name' => 'admin.training-events.create', 'required_permission' => 'training_events.create'],
                    ['title' => 'Manage Group Workshops', 'url' => '/admin/training-workflow?step=3'],
                    ['title' => 'Group Performance Report', 'url' => '/admin/training-workflow?step=4'],
                    ['title' => 'Participant Progress Across Group', 'route_name' => 'admin.training-events.grouped'],
                ],
            ],
            [
                'title' => 'Training Workflow',
                'icon' => 'workflow',
                'section_title' => 'Workflow',
                'section_sort_order' => 40,
                'required_permission' => 'training_events.view',
                'children' => [
                    ['title' => 'Workflow Stages', 'url' => '/admin/training-workflow?step=1'],
                    ['title' => 'Planning', 'url' => '/admin/training-workflow?step=1'],
                    ['title' => 'Organizer Assignment', 'url' => '/admin/training-workflow?step=1'],
                    ['title' => 'Participant Enrollment', 'url' => '/admin/training-workflow?step=2'],
                    ['title' => 'Pre-Test', 'url' => '/admin/training-workflow?step=3'],
                    ['title' => 'Workshop Delivery', 'url' => '/admin/training-workflow?step=3'],
                    ['title' => 'Post-Test', 'url' => '/admin/training-workflow?step=3'],
                    ['title' => 'Score Calculation', 'url' => '/admin/training-workflow?step=4'],
                    ['title' => 'Certification', 'url' => '/admin/training-workflow?step=4'],
                    ['title' => 'Workflow Rules', 'url' => '/admin/training-workflow'],
                    ['title' => 'Workflow Automation', 'url' => '/admin/training-workflow'],
                    ['title' => 'Approval Matrix', 'url' => '/admin/training-workflow'],
                    ['title' => 'Workflow Logs', 'url' => '/admin/training-workflow'],
                ],
            ],
        ];
    }

    private static function resolveSectionId(string $name, int $sortOrder): ?int
    {
        if (! Schema::hasTable('admin_sidebar_menu_sections')) {
            return null;
        }

        $sectionName = trim($name);
        if ($sectionName === '') {
            $sectionName = 'General';
        }

        $existing = AdminSidebarMenuSection::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($sectionName)])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return AdminSidebarMenuSection::query()->create([
            'name' => $sectionName,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ])->id;
    }
}
