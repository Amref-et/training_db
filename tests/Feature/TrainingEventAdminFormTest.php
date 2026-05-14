<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingEvent;
use App\Models\TrainingOrganizer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingEventAdminFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_training_event_create_form_defaults_to_one_workshop_and_has_up_coming_status(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-events.create'));

        $response->assertOk();
        $response->assertSee('Number of Workshops');
        $response->assertSee('Up coming');
        $this->assertMatchesRegularExpression('/name="workshop_count"[\s\S]*?value="1"/', $response->getContent());
    }

    public function test_training_event_can_be_created_as_up_coming_with_default_workshop_count(): void
    {
        $training = Training::query()->create([
            'title' => 'HIV Case Management',
            'description' => 'Case management training',
            'modality' => 'Face 2 face',
            'type' => 'Basic',
        ]);
        $organizer = TrainingOrganizer::query()->create([
            'title' => 'Project Alpha',
            'project_code' => 'PROJ-ALPHA',
            'project_name' => 'Project Alpha',
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->post(route('admin.training-events.store'), [
                'event_name' => 'Upcoming Case Management Training',
                'training_id' => $training->id,
                'training_organizer_id' => $organizer->id,
                'organizer_type' => 'The project',
                'start_date' => '2026-07-10',
                'end_date' => '2026-07-12',
                'status' => 'Up coming',
            ]);

        $response->assertRedirect(route('admin.training-events.index'));

        $event = TrainingEvent::query()
            ->where('event_name', 'Upcoming Case Management Training')
            ->first();

        $this->assertSame('Up coming', $event?->status);
        $this->assertSame(1, $event?->workshop_count);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
