<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionsTableSeeder::class,
            ZonesTableSeeder::class,
            WoredasTableSeeder::class,
            OrganizationsTableSeeder::class,
            ProfessionsTableSeeder::class,
            TrainingOrganizersTableSeeder::class,
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            ContentPageSeeder::class,
            DashboardSeeder::class,
            ThemeSettingsSeeder::class,
            WebsiteMenuSeeder::class,
            SidebarMenuSeeder::class,
        ]);
    }
}
