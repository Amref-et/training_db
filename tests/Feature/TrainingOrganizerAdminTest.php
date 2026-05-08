<?php

namespace Tests\Feature;

use App\Models\TrainingOrganizer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingOrganizerAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_project_form_includes_metadata_fields_and_creatable_program_dropdown(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-organizers.create'));

        $response
            ->assertOk()
            ->assertSee('Project Long Name')
            ->assertSee('Donor')
            ->assertSee('Program')
            ->assertSee('DPC')
            ->assertSee('RMNCATH-N')
            ->assertSee('js-creatable-select', false);
    }

    public function test_admin_can_save_project_metadata_with_custom_program(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->from(route('admin.training-organizers.create'))
            ->post(route('admin.training-organizers.store'), [
                'project_code' => 'PROJ-META',
                'project_name' => 'Short Project Name',
                'project_long_name' => 'Complete Project Long Name',
                'donor' => 'USAID',
                'program' => 'Custom Program Area',
                'subawardees' => ['Subawardee One'],
            ]);

        $response
            ->assertRedirect(route('admin.training-organizers.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Project created successfully.');

        $this->assertDatabaseHas('training_organizers', [
            'project_code' => 'PROJ-META',
            'project_name' => 'Short Project Name',
            'project_long_name' => 'Complete Project Long Name',
            'donor' => 'USAID',
            'program' => 'Custom Program Area',
        ]);

        $organizer = TrainingOrganizer::query()
            ->where('project_code', 'PROJ-META')
            ->firstOrFail();

        $this->assertSame('Short Project Name', $organizer->title);
        $this->assertDatabaseHas('project_subawardees', [
            'project_id' => $organizer->id,
            'subawardee_name' => 'Subawardee One',
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
