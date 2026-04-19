<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionsTableSeeder::class,
            WoredasTableSeeder::class,
            OrganizationsTableSeeder::class,
            TrainingOrganizersTableSeeder::class,
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            ContentPageSeeder::class,
        ]);
    }
}
