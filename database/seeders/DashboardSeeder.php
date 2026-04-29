<?php

namespace Database\Seeders;

use App\Models\DashboardTab;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\DashboardLayoutService;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        /** @var DashboardLayoutService $dashboardLayout */
        $dashboardLayout = app(DashboardLayoutService::class);

        $users = User::query()
            ->whereIn('email', ['admin@example.com', 'editor@example.com', 'viewer@example.com'])
            ->get();

        foreach ($users as $user) {
            $dashboardLayout->ensureDefaultTabs($user);
        }

        $publicTab = DashboardTab::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'admin@example.com'))
            ->where('slug', 'training-dashboard')
            ->first();

        if ($publicTab) {
            WebsiteSetting::query()->updateOrCreate(
                ['id' => 1],
                ['public_home_dashboard_tab_id' => $publicTab->id]
            );
        }
    }
}
