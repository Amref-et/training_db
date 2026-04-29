<?php

namespace Database\Seeders;

use App\Models\AdminSidebarMenuItem;
use App\Models\AdminSidebarMenuSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SidebarMenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $sections = [];

            foreach ($this->sections() as $definition) {
                $section = AdminSidebarMenuSection::query()->updateOrCreate(
                    ['name' => $definition['name']],
                    [
                        'sort_order' => $definition['sort_order'],
                        'is_active' => $definition['is_active'],
                    ]
                );

                $sections[$definition['key']] = $section;
            }

            $itemsByKey = [];

            foreach ($this->items() as $definition) {
                $section = $sections[$definition['section_key']] ?? null;
                if (! $section) {
                    continue;
                }

                $parent = null;
                if (! empty($definition['parent_key'])) {
                    $parent = $itemsByKey[$definition['parent_key']] ?? null;

                    if (! $parent) {
                        continue;
                    }
                }

                $item = AdminSidebarMenuItem::query()->updateOrCreate(
                    [
                        'title' => $definition['title'],
                        'section_id' => $section->id,
                        'parent_id' => $parent?->id,
                    ],
                    [
                        'icon' => $definition['icon'],
                        'route_name' => $definition['route_name'],
                        'url' => $definition['url'],
                        'target' => $definition['target'],
                        'required_permission' => $definition['required_permission'],
                        'section_title' => $section->name,
                        'section_sort_order' => $section->sort_order,
                        'sort_order' => $definition['sort_order'],
                        'is_active' => $definition['is_active'],
                    ]
                );

                $itemsByKey[$definition['key']] = $item;
            }
        });
    }

    private function sections(): array
    {
        return [
            ['key' => 'dashboard', 'name' => 'Dashboard', 'sort_order' => 0, 'is_active' => true],
            ['key' => 'participant', 'name' => 'Participant', 'sort_order' => 1, 'is_active' => true],
            ['key' => 'projects', 'name' => 'Projects', 'sort_order' => 2, 'is_active' => true],
            ['key' => 'training', 'name' => 'Training', 'sort_order' => 3, 'is_active' => true],
            ['key' => 'cms', 'name' => 'CMS', 'sort_order' => 4, 'is_active' => true],
            ['key' => 'configuration', 'name' => 'Configuration', 'sort_order' => 5, 'is_active' => true],
            ['key' => 'settings', 'name' => 'Settings', 'sort_order' => 6, 'is_active' => true],
            ['key' => 'core', 'name' => 'Core', 'sort_order' => 10, 'is_active' => true],
        ];
    }

    private function items(): array
    {
        return [
            ['key' => 'dashboard_root', 'title' => 'Dashboard', 'section_key' => 'dashboard', 'parent_key' => null, 'icon' => 'chart-line', 'route_name' => 'admin.dashboard', 'url' => null, 'target' => '_self', 'required_permission' => 'dashboard.view', 'sort_order' => 10, 'is_active' => true],

            ['key' => 'participants_root', 'title' => 'Participants', 'section_key' => 'participant', 'parent_key' => null, 'icon' => 'person-badge', 'route_name' => 'admin.participants.index', 'url' => null, 'target' => '_self', 'required_permission' => null, 'sort_order' => 0, 'is_active' => true],
            ['key' => 'participants_add', 'title' => 'Add participant', 'section_key' => 'participant', 'parent_key' => null, 'icon' => 'person-fill-add', 'route_name' => 'admin.participants.create', 'url' => null, 'target' => '_self', 'required_permission' => null, 'sort_order' => 1, 'is_active' => true],

            ['key' => 'reference_projects_root', 'title' => 'Projects', 'section_key' => 'projects', 'parent_key' => null, 'icon' => 'users-cog', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'training_organizers.view', 'sort_order' => 110, 'is_active' => true],
            ['key' => 'reference_projects_all', 'title' => 'All Projects', 'section_key' => 'projects', 'parent_key' => 'reference_projects_root', 'icon' => null, 'route_name' => 'admin.training-organizers.index', 'url' => null, 'target' => '_self', 'required_permission' => 'training_organizers.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'reference_projects_add', 'title' => 'Add Project', 'section_key' => 'projects', 'parent_key' => 'reference_projects_root', 'icon' => null, 'route_name' => 'admin.training-organizers.create', 'url' => null, 'target' => '_self', 'required_permission' => 'training_organizers.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'reference_projects_trainings', 'title' => 'Project Trainings', 'section_key' => 'projects', 'parent_key' => 'reference_projects_root', 'icon' => null, 'route_name' => 'admin.training-events.index', 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.view', 'sort_order' => 40, 'is_active' => true],
            ['key' => 'reference_projects_performance', 'title' => 'Project Performance', 'section_key' => 'projects', 'parent_key' => 'reference_projects_root', 'icon' => null, 'route_name' => 'admin.training-events.grouped', 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.view', 'sort_order' => 50, 'is_active' => true],

            ['key' => 'trainings_root', 'title' => 'Trainings', 'section_key' => 'training', 'parent_key' => null, 'icon' => 'book', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'trainings.view', 'sort_order' => 120, 'is_active' => true],
            ['key' => 'trainings_all', 'title' => 'All Trainings', 'section_key' => 'training', 'parent_key' => 'trainings_root', 'icon' => null, 'route_name' => 'admin.trainings.index', 'url' => null, 'target' => '_self', 'required_permission' => 'trainings.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'trainings_add', 'title' => 'Create Training', 'section_key' => 'training', 'parent_key' => 'trainings_root', 'icon' => null, 'route_name' => 'admin.trainings.create', 'url' => null, 'target' => '_self', 'required_permission' => 'trainings.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'trainings_categories', 'title' => 'Training Categories', 'section_key' => 'training', 'parent_key' => 'trainings_root', 'icon' => null, 'route_name' => 'admin.trainingcategories.index', 'url' => null, 'target' => '_self', 'required_permission' => 'trainings.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'trainings_materials', 'title' => 'Training Materials', 'section_key' => 'training', 'parent_key' => 'trainings_root', 'icon' => null, 'route_name' => 'admin.trainingmaterials.index', 'url' => null, 'target' => '_self', 'required_permission' => 'trainings.view', 'sort_order' => 50, 'is_active' => true],

            ['key' => 'projects_training_root', 'title' => 'Projects', 'section_key' => 'training', 'parent_key' => null, 'icon' => 'target', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'projects.view', 'sort_order' => 170, 'is_active' => true],
            ['key' => 'projects_training_all', 'title' => 'All Projects', 'section_key' => 'training', 'parent_key' => 'projects_training_root', 'icon' => null, 'route_name' => 'admin.projects.index', 'url' => null, 'target' => '_self', 'required_permission' => 'projects.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'projects_training_add', 'title' => 'Create Project', 'section_key' => 'training', 'parent_key' => 'projects_training_root', 'icon' => null, 'route_name' => 'admin.projects.create', 'url' => null, 'target' => '_self', 'required_permission' => 'projects.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'projects_training_categories', 'title' => 'Project Categories', 'section_key' => 'training', 'parent_key' => 'projects_training_root', 'icon' => null, 'route_name' => 'admin.project-categories.index', 'url' => null, 'target' => '_self', 'required_permission' => 'projects.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'projects_training_reports', 'title' => 'Project Reports', 'section_key' => 'training', 'parent_key' => 'projects_training_root', 'icon' => null, 'route_name' => 'admin.projects.index', 'url' => null, 'target' => '_self', 'required_permission' => 'projects.view', 'sort_order' => 70, 'is_active' => true],

            ['key' => 'events_root', 'title' => 'Training Events', 'section_key' => 'training', 'parent_key' => null, 'icon' => 'calendar', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.view', 'sort_order' => 130, 'is_active' => true],
            ['key' => 'events_all', 'title' => 'All Events', 'section_key' => 'training', 'parent_key' => 'events_root', 'icon' => null, 'route_name' => 'admin.training-events.index', 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'events_add', 'title' => 'Create Event', 'section_key' => 'training', 'parent_key' => 'events_root', 'icon' => null, 'route_name' => 'admin.training-events.create', 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'events_calendar', 'title' => 'Event Calendar View', 'section_key' => 'training', 'parent_key' => 'events_root', 'icon' => null, 'route_name' => 'admin.training-events-calendar.index', 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'events_participants_by', 'title' => 'Participants by Event', 'section_key' => 'training', 'parent_key' => 'events_root', 'icon' => null, 'route_name' => 'admin.training-events.grouped', 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.view', 'sort_order' => 40, 'is_active' => true],

            ['key' => 'event_participants_root', 'title' => 'Event Participants', 'section_key' => 'training', 'parent_key' => null, 'icon' => 'user-check', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'training_event_participants.view', 'sort_order' => 150, 'is_active' => true],
            ['key' => 'event_participants_all', 'title' => 'All Participants', 'section_key' => 'training', 'parent_key' => 'event_participants_root', 'icon' => null, 'route_name' => 'admin.training-event-participants.index', 'url' => null, 'target' => '_self', 'required_permission' => 'training_event_participants.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'event_participants_enroll', 'title' => 'Enroll Participant', 'section_key' => 'training', 'parent_key' => 'event_participants_root', 'icon' => null, 'route_name' => 'admin.training-event-participants.create', 'url' => null, 'target' => '_self', 'required_permission' => 'training_event_participants.create', 'sort_order' => 20, 'is_active' => true],

            ['key' => 'workshop_scores_root', 'title' => 'Workshop Scores', 'section_key' => 'training', 'parent_key' => null, 'icon' => 'clipboard', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'training_event_workshop_scores.view', 'sort_order' => 160, 'is_active' => false],
            ['key' => 'workshop_scores_sheets', 'title' => 'Workshop Score Sheets', 'section_key' => 'training', 'parent_key' => 'workshop_scores_root', 'icon' => null, 'route_name' => 'admin.training-event-workshop-scores.index', 'url' => null, 'target' => '_self', 'required_permission' => 'training_event_workshop_scores.view', 'sort_order' => 40, 'is_active' => true],

            ['key' => 'workflow_root', 'title' => 'Training Workflow', 'section_key' => 'training', 'parent_key' => null, 'icon' => 'workflow', 'route_name' => 'admin.training-workflow.index', 'url' => null, 'target' => '_self', 'required_permission' => 'training_events.view', 'sort_order' => 140, 'is_active' => true],
            ['key' => 'workflow_child', 'title' => 'Training Workflow', 'section_key' => 'training', 'parent_key' => 'workflow_root', 'icon' => 'bar-chart-steps', 'route_name' => 'admin.training-workflow.index', 'url' => null, 'target' => '_self', 'required_permission' => null, 'sort_order' => 0, 'is_active' => true],

            ['key' => 'cms_root', 'title' => 'CMS Pages', 'section_key' => 'cms', 'parent_key' => null, 'icon' => 'file-text', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'pages.view', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'cms_all', 'title' => 'All Pages', 'section_key' => 'cms', 'parent_key' => 'cms_root', 'icon' => null, 'route_name' => 'admin.pages.index', 'url' => null, 'target' => '_self', 'required_permission' => 'pages.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'cms_add', 'title' => 'Add New Page', 'section_key' => 'cms', 'parent_key' => 'cms_root', 'icon' => null, 'route_name' => 'admin.pages.create', 'url' => null, 'target' => '_self', 'required_permission' => 'pages.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'cms_categories', 'title' => 'Page Categories', 'section_key' => 'cms', 'parent_key' => 'cms_root', 'icon' => null, 'route_name' => null, 'url' => '#', 'target' => '_self', 'required_permission' => 'pages.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'cms_settings', 'title' => 'Page Settings', 'section_key' => 'cms', 'parent_key' => 'cms_root', 'icon' => null, 'route_name' => null, 'url' => '#', 'target' => '_self', 'required_permission' => 'pages.view', 'sort_order' => 40, 'is_active' => true],

            ['key' => 'crud_root', 'title' => 'CRUD Builder', 'section_key' => 'cms', 'parent_key' => null, 'icon' => 'hammer', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'crud_builder.view', 'sort_order' => 70, 'is_active' => true],
            ['key' => 'crud_manage', 'title' => 'Manage Entities', 'section_key' => 'cms', 'parent_key' => 'crud_root', 'icon' => null, 'route_name' => 'admin.crud-builders.index', 'url' => null, 'target' => '_self', 'required_permission' => 'crud_builder.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'crud_create', 'title' => 'Create New Entity', 'section_key' => 'cms', 'parent_key' => 'crud_root', 'icon' => null, 'route_name' => 'admin.crud-builders.create', 'url' => null, 'target' => '_self', 'required_permission' => 'crud_builder.create', 'sort_order' => 20, 'is_active' => true],

            ['key' => 'regions_root', 'title' => 'Regions', 'section_key' => 'configuration', 'parent_key' => null, 'icon' => 'map', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'regions.view', 'sort_order' => 60, 'is_active' => true],
            ['key' => 'regions_all', 'title' => 'All Regions', 'section_key' => 'configuration', 'parent_key' => 'regions_root', 'icon' => null, 'route_name' => 'admin.regions.index', 'url' => null, 'target' => '_self', 'required_permission' => 'regions.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'regions_add', 'title' => 'Add Region', 'section_key' => 'configuration', 'parent_key' => 'regions_root', 'icon' => null, 'route_name' => 'admin.regions.create', 'url' => null, 'target' => '_self', 'required_permission' => 'regions.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'regions_settings', 'title' => 'Region Settings', 'section_key' => 'configuration', 'parent_key' => 'regions_root', 'icon' => null, 'route_name' => 'admin.regions.index', 'url' => null, 'target' => '_self', 'required_permission' => 'regions.view', 'sort_order' => 40, 'is_active' => true],

            ['key' => 'woredas_root', 'title' => 'Woredas', 'section_key' => 'configuration', 'parent_key' => null, 'icon' => 'map-pin', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'woredas.view', 'sort_order' => 90, 'is_active' => true],
            ['key' => 'woredas_all', 'title' => 'All Woredas', 'section_key' => 'configuration', 'parent_key' => 'woredas_root', 'icon' => null, 'route_name' => 'admin.woredas.index', 'url' => null, 'target' => '_self', 'required_permission' => 'woredas.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'woredas_add', 'title' => 'Add Woreda', 'section_key' => 'configuration', 'parent_key' => 'woredas_root', 'icon' => null, 'route_name' => 'admin.woredas.create', 'url' => null, 'target' => '_self', 'required_permission' => 'woredas.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'woredas_assign', 'title' => 'Assign to Region', 'section_key' => 'configuration', 'parent_key' => 'woredas_root', 'icon' => null, 'route_name' => 'admin.woredas.index', 'url' => null, 'target' => '_self', 'required_permission' => 'woredas.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'woredas_settings', 'title' => 'Woreda Settings', 'section_key' => 'configuration', 'parent_key' => 'woredas_root', 'icon' => null, 'route_name' => 'admin.woredas.index', 'url' => null, 'target' => '_self', 'required_permission' => 'woredas.view', 'sort_order' => 40, 'is_active' => true],

            ['key' => 'organizations_root', 'title' => 'Organizations', 'section_key' => 'configuration', 'parent_key' => null, 'icon' => 'building', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'organizations.view', 'sort_order' => 100, 'is_active' => true],
            ['key' => 'organizations_all', 'title' => 'All Organizations', 'section_key' => 'configuration', 'parent_key' => 'organizations_root', 'icon' => null, 'route_name' => 'admin.organizations.index', 'url' => null, 'target' => '_self', 'required_permission' => 'organizations.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'organizations_add', 'title' => 'Add Organization', 'section_key' => 'configuration', 'parent_key' => 'organizations_root', 'icon' => null, 'route_name' => 'admin.organizations.create', 'url' => null, 'target' => '_self', 'required_permission' => 'organizations.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'organizations_types', 'title' => 'Organization Types', 'section_key' => 'configuration', 'parent_key' => 'organizations_root', 'icon' => null, 'route_name' => 'admin.organizations.index', 'url' => null, 'target' => '_self', 'required_permission' => 'organizations.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'organizations_hierarchy', 'title' => 'Organization Hierarchy', 'section_key' => 'configuration', 'parent_key' => 'organizations_root', 'icon' => null, 'route_name' => 'admin.organizations.index', 'url' => null, 'target' => '_self', 'required_permission' => 'organizations.view', 'sort_order' => 40, 'is_active' => true],
            ['key' => 'organizations_contacts', 'title' => 'Organization Contacts', 'section_key' => 'configuration', 'parent_key' => 'organizations_root', 'icon' => null, 'route_name' => 'admin.organizations.index', 'url' => null, 'target' => '_self', 'required_permission' => 'organizations.view', 'sort_order' => 50, 'is_active' => true],

            ['key' => 'profession_root', 'title' => 'Profession', 'section_key' => 'configuration', 'parent_key' => null, 'icon' => 'person-workspace', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => null, 'sort_order' => 110, 'is_active' => true],
            ['key' => 'profession_index', 'title' => 'Profession', 'section_key' => 'configuration', 'parent_key' => 'profession_root', 'icon' => null, 'route_name' => 'admin.professions.index', 'url' => null, 'target' => '_self', 'required_permission' => null, 'sort_order' => 0, 'is_active' => true],

            ['key' => 'zone_root', 'title' => 'Zone', 'section_key' => 'configuration', 'parent_key' => null, 'icon' => 'crosshair2', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => null, 'sort_order' => 70, 'is_active' => true],
            ['key' => 'zone_list', 'title' => 'Zone List', 'section_key' => 'configuration', 'parent_key' => 'zone_root', 'icon' => null, 'route_name' => 'admin.zones.index', 'url' => null, 'target' => '_self', 'required_permission' => 'zones.view', 'sort_order' => 70, 'is_active' => true],

            ['key' => 'appearance_root', 'title' => 'Appearance', 'section_key' => 'settings', 'parent_key' => null, 'icon' => 'palette', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'appearance.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'appearance_theme', 'title' => 'Theme Settings', 'section_key' => 'settings', 'parent_key' => 'appearance_root', 'icon' => null, 'route_name' => 'admin.appearance.edit', 'url' => null, 'target' => '_self', 'required_permission' => 'appearance.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'appearance_menu_manager', 'title' => 'Menu Manager', 'section_key' => 'settings', 'parent_key' => 'appearance_root', 'icon' => null, 'route_name' => 'admin.menus.index', 'url' => null, 'target' => '_self', 'required_permission' => 'menus.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'appearance_sidebar', 'title' => 'Sidebar menu', 'section_key' => 'settings', 'parent_key' => 'appearance_root', 'icon' => null, 'route_name' => 'admin.sidebar-menus.index', 'url' => null, 'target' => '_self', 'required_permission' => null, 'sort_order' => 40, 'is_active' => true],
            ['key' => 'appearance_env', 'title' => 'Env Settings', 'section_key' => 'settings', 'parent_key' => 'appearance_root', 'icon' => null, 'route_name' => 'admin.settings.env.edit', 'url' => null, 'target' => '_self', 'required_permission' => 'appearance.view', 'sort_order' => 50, 'is_active' => true],

            ['key' => 'users_root', 'title' => 'Users', 'section_key' => 'settings', 'parent_key' => null, 'icon' => 'users', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'users.view', 'sort_order' => 40, 'is_active' => true],
            ['key' => 'users_all', 'title' => 'All Users', 'section_key' => 'settings', 'parent_key' => 'users_root', 'icon' => null, 'route_name' => 'admin.users.index', 'url' => null, 'target' => '_self', 'required_permission' => 'users.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'users_add', 'title' => 'Add New User', 'section_key' => 'settings', 'parent_key' => 'users_root', 'icon' => null, 'route_name' => 'admin.users.create', 'url' => null, 'target' => '_self', 'required_permission' => 'users.create', 'sort_order' => 20, 'is_active' => true],
            ['key' => 'users_profile_fields', 'title' => 'User Profile Fields', 'section_key' => 'settings', 'parent_key' => 'users_root', 'icon' => null, 'route_name' => null, 'url' => '#', 'target' => '_self', 'required_permission' => 'users.view', 'sort_order' => 30, 'is_active' => true],
            ['key' => 'users_activity_log', 'title' => 'User Activity Log', 'section_key' => 'settings', 'parent_key' => 'users_root', 'icon' => null, 'route_name' => 'admin.user-activity-logs.index', 'url' => null, 'target' => '_self', 'required_permission' => 'users.view', 'sort_order' => 40, 'is_active' => true],

            ['key' => 'roles_root', 'title' => 'Roles & Permissions', 'section_key' => 'settings', 'parent_key' => null, 'icon' => 'shield', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'roles.view', 'sort_order' => 50, 'is_active' => true],
            ['key' => 'roles_all', 'title' => 'All Roles', 'section_key' => 'settings', 'parent_key' => 'roles_root', 'icon' => null, 'route_name' => 'admin.roles.index', 'url' => null, 'target' => '_self', 'required_permission' => 'roles.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'roles_create', 'title' => 'Create Role', 'section_key' => 'settings', 'parent_key' => 'roles_root', 'icon' => null, 'route_name' => 'admin.roles.create', 'url' => null, 'target' => '_self', 'required_permission' => 'roles.create', 'sort_order' => 20, 'is_active' => true],

            ['key' => 'api_root', 'title' => 'API Management', 'section_key' => 'core', 'parent_key' => null, 'icon' => 'cloud-arrow-up', 'route_name' => null, 'url' => null, 'target' => '_self', 'required_permission' => 'api_management.view', 'sort_order' => 65, 'is_active' => true],
            ['key' => 'api_dashboard', 'title' => 'API Dashboard', 'section_key' => 'core', 'parent_key' => 'api_root', 'icon' => null, 'route_name' => 'admin.api-management.index', 'url' => null, 'target' => '_self', 'required_permission' => 'api_management.view', 'sort_order' => 10, 'is_active' => true],
            ['key' => 'api_dhis2', 'title' => 'DHIS2 Integration', 'section_key' => 'core', 'parent_key' => 'api_root', 'icon' => null, 'route_name' => 'admin.api-management.index', 'url' => null, 'target' => '_self', 'required_permission' => 'api_management.update', 'sort_order' => 20, 'is_active' => true],
        ];
    }
}
