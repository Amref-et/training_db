<?php

namespace Tests\Feature;

use App\Models\DashboardTab;
use App\Models\DashboardWidget;
use App\Models\User;
use App\Services\DashboardLayoutService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_shared_dashboard_tabs_are_visible_to_other_dashboard_users_as_read_only(): void
    {
        $owner = $this->adminUser();
        $viewer = $this->viewerUser();
        $sharedTab = DashboardTab::query()->create([
            'user_id' => $owner->id,
            'name' => 'Shared Reports Dashboard',
            'slug' => 'shared-reports-dashboard',
            'sort_order' => 1,
            'is_default' => false,
            'is_shared' => true,
        ]);
        DashboardTab::query()->create([
            'user_id' => $owner->id,
            'name' => 'Admin Private Dashboard',
            'slug' => 'admin-private-dashboard',
            'sort_order' => 2,
            'is_default' => false,
            'is_shared' => false,
        ]);

        DashboardWidget::query()->create([
            'dashboard_tab_id' => $sharedTab->id,
            'title' => 'Shared KPI',
            'chart_type' => 'stat',
            'sql_query' => 'SELECT 7 AS total_shared',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.dashboard', [
                'tab_id' => $sharedTab->id,
                'edit' => '1',
            ]));

        $response
            ->assertOk()
            ->assertSee('Shared Reports Dashboard')
            ->assertSee('Shared KPI')
            ->assertSee('Shared')
            ->assertSee('This shared dashboard is read-only for your account.')
            ->assertDontSee('Admin Private Dashboard')
            ->assertDontSee('Edit Tab')
            ->assertDontSee('Delete Tab')
            ->assertDontSee('Add Widget');
    }

    public function test_shared_widget_data_can_refresh_for_other_dashboard_users(): void
    {
        $owner = $this->adminUser();
        $viewer = $this->viewerUser();
        $sharedTab = DashboardTab::query()->create([
            'user_id' => $owner->id,
            'name' => 'Shared Reports Dashboard',
            'slug' => 'shared-reports-dashboard',
            'sort_order' => 1,
            'is_default' => false,
            'is_shared' => true,
        ]);
        $privateTab = DashboardTab::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private Reports Dashboard',
            'slug' => 'private-reports-dashboard',
            'sort_order' => 2,
            'is_default' => false,
            'is_shared' => false,
        ]);
        $sharedWidget = DashboardWidget::query()->create([
            'dashboard_tab_id' => $sharedTab->id,
            'title' => 'Shared KPI',
            'chart_type' => 'stat',
            'sql_query' => 'SELECT 7 AS total_shared',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $privateWidget = DashboardWidget::query()->create([
            'dashboard_tab_id' => $privateTab->id,
            'title' => 'Private KPI',
            'chart_type' => 'stat',
            'sql_query' => 'SELECT 3 AS total_private',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this
            ->actingAs($viewer)
            ->getJson(route('admin.dashboard.widgets.data', $sharedWidget))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.type', 'stat')
            ->assertJsonPath('data.value', 7);

        $this
            ->actingAs($viewer)
            ->getJson(route('admin.dashboard.widgets.data', $privateWidget))
            ->assertForbidden();
    }

    public function test_admin_can_mark_created_dashboard_tab_visible_to_other_users(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->post(route('admin.dashboard.tabs.store'), [
                'name' => 'Reports Dashboard',
                'is_shared' => '1',
                'edit' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dashboard_tabs', [
            'user_id' => $admin->id,
            'slug' => 'reports-dashboard',
            'is_shared' => true,
        ]);
    }

    public function test_default_admin_reports_dashboard_is_shared(): void
    {
        $admin = $this->adminUser();

        app(DashboardLayoutService::class)->ensureDefaultTabs($admin);

        $this->assertDatabaseHas('dashboard_tabs', [
            'user_id' => $admin->id,
            'slug' => 'reports-dashboard',
            'is_shared' => true,
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }

    private function viewerUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Viewer']);

        return $user;
    }
}
