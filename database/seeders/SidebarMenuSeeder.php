<?php

namespace Database\Seeders;

use App\Support\AdminSidebarMenuDefaults;
use Illuminate\Database\Seeder;

class SidebarMenuSeeder extends Seeder
{
    public function run(): void
    {
        AdminSidebarMenuDefaults::seedSuggested();
    }
}
