<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrainingEventsCalendarDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_calendar_loads_current_month_by_default(): void
    {
        $this->travelTo(Carbon::create(2026, 5, 18, 12));

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-events-calendar.index'));

        $response->assertOk();
        $response->assertSee('May 2026');
        $response->assertSee('value="2026-05"', false);
    }

    public function test_blank_month_input_falls_back_to_current_month(): void
    {
        $this->travelTo(Carbon::create(2026, 5, 18, 12));

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-events-calendar.index', [
                'view' => 'month',
                'month' => '',
            ]));

        $response->assertOk();
        $response->assertSee('May 2026');
        $response->assertSee('value="2026-05"', false);
    }

    public function test_embed_calendar_loads_current_month_by_default(): void
    {
        $this->travelTo(Carbon::create(2026, 5, 18, 12));

        $response = $this->get(route('training-events-calendar.embed'));

        $response->assertOk();
        $response->assertSee('May 2026');
        $response->assertSee('value="2026-05"', false);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
