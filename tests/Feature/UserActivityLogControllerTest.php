<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivityLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_activity_log_index_uses_recent_order_and_date_filters(): void
    {
        $user = $this->adminUser();

        UserActivityLog::query()->create([
            'user_id' => $user->id,
            'log_type' => 'activity',
            'action' => 'Older log entry',
            'method' => 'GET',
            'path' => '/older',
            'route_name' => 'older.route',
            'occurred_at' => Carbon::parse('2026-05-20 09:00:00'),
        ]);

        UserActivityLog::query()->create([
            'user_id' => $user->id,
            'log_type' => 'activity',
            'action' => 'Newest log entry',
            'method' => 'POST',
            'path' => '/newest',
            'route_name' => 'newest.route',
            'occurred_at' => Carbon::parse('2026-05-25 10:00:00'),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.user-activity-logs.index', [
                'from' => '2026-05-20',
                'to' => '2026-05-25',
            ]));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                'Newest log entry',
                'Older log entry',
            ]);
    }

    public function test_activity_log_default_page_orders_by_primary_key_to_avoid_sort_buffer(): void
    {
        $user = $this->adminUser();
        $queries = [];

        UserActivityLog::query()->create([
            'user_id' => $user->id,
            'log_type' => 'activity',
            'action' => 'Default page entry',
            'method' => 'GET',
            'path' => '/default',
            'route_name' => 'default.route',
            'occurred_at' => Carbon::parse('2026-05-25 10:00:00'),
        ]);

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this
            ->actingAs($user)
            ->get(route('admin.user-activity-logs.index'))
            ->assertOk();

        $logSelect = collect($queries)->first(function (string $sql): bool {
            $normalized = strtolower($sql);

            return str_starts_with($normalized, 'select *')
                && (
                    str_contains($normalized, 'from "user_activity_logs"')
                    || str_contains($normalized, 'from `user_activity_logs`')
                );
        });

        $this->assertNotNull($logSelect);
        $this->assertStringContainsString('order by', strtolower($logSelect));
        $this->assertStringNotContainsString('occurred_at', strtolower($logSelect));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
