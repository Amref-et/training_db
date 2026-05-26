<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Region;
use App\Models\Training;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingOrganizer;
use App\Models\Woreda;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingEventGroupedTrainingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_training_events_are_grouped_only_by_training_title(): void
    {
        $firstTraining = Training::query()->create([
            'title' => 'Case Management',
            'description' => 'First curriculum record',
            'modality' => 'Face 2 face',
            'type' => 'Basic',
        ]);
        $secondTraining = Training::query()->create([
            'title' => 'Case Management',
            'description' => 'Second curriculum record with the same title',
            'modality' => 'Face 2 face',
            'type' => 'Advanced',
        ]);
        $otherTraining = Training::query()->create([
            'title' => 'Data Quality',
            'description' => 'Data quality curriculum',
            'modality' => 'Virtual',
            'type' => 'Basic',
        ]);
        $organizer = TrainingOrganizer::query()->create([
            'title' => 'Project Alpha',
            'project_code' => 'PROJ-ALPHA',
            'project_name' => 'Project Alpha',
        ]);

        TrainingEvent::query()->create([
            'event_name' => 'Case Management Round 1',
            'training_id' => $firstTraining->id,
            'training_organizer_id' => $organizer->id,
            'organizer_type' => 'The project',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'status' => 'Completed',
        ]);
        TrainingEvent::query()->create([
            'event_name' => 'Case Management Round 2',
            'training_id' => $secondTraining->id,
            'training_organizer_id' => $organizer->id,
            'organizer_type' => 'The project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'status' => 'Ongoing',
        ]);
        TrainingEvent::query()->create([
            'event_name' => 'Data Quality Round 1',
            'training_id' => $otherTraining->id,
            'training_organizer_id' => $organizer->id,
            'organizer_type' => 'The project',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'Pending',
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-events.grouped-training'));

        $response
            ->assertOk()
            ->assertSee('Grouped Training')
            ->assertSee('Case Management')
            ->assertSee('Case Management Round 1')
            ->assertSee('Case Management Round 2')
            ->assertSee('Data Quality');

        $this->assertSame(1, substr_count($response->getContent(), 'Case Management</td>'));
    }

    public function test_grouped_training_shows_participants_grouped_by_training(): void
    {
        $training = Training::query()->create([
            'title' => 'Participant Grouping Training',
            'description' => 'Training for participants',
            'modality' => 'Virtual',
            'type' => 'Basic',
        ]);
        $organizer = TrainingOrganizer::query()->create([
            'title' => 'Project Beta',
            'project_code' => 'PROJ-BETA',
            'project_name' => 'Project Beta',
        ]);

        $eventA = TrainingEvent::query()->create([
            'event_name' => 'Participant Grouping - A',
            'training_id' => $training->id,
            'training_organizer_id' => $organizer->id,
            'organizer_type' => 'The project',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'status' => 'Completed',
        ]);
        $eventB = TrainingEvent::query()->create([
            'event_name' => 'Participant Grouping - B',
            'training_id' => $training->id,
            'training_organizer_id' => $organizer->id,
            'organizer_type' => 'The project',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'status' => 'Completed',
        ]);

        $region = Region::query()->create(['name' => 'Test Region']);
        $woreda = Woreda::query()->create(['name' => 'Test Woreda', 'region_id' => $region->id]);
        $organization = Organization::query()->create([
            'name' => 'Test Organization',
            'category' => 'Health',
            'type' => 'Partner',
            'region_id' => $region->id,
            'woreda_id' => $woreda->id,
        ]);

        $participant = Participant::query()->create([
            'first_name' => 'John',
            'father_name' => 'Doe',
            'grandfather_name' => 'Smith',
            'mobile_phone' => '0912345678',
            'email' => 'john@example.com',
            'profession' => 'Nurse',
            'gender' => 'male',
            'region_id' => $region->id,
            'woreda_id' => $woreda->id,
            'organization_id' => $organization->id,
        ]);

        TrainingEventParticipant::query()->create([
            'training_event_id' => $eventA->id,
            'participant_id' => $participant->id,
            'final_score' => 88.5,
        ]);
        TrainingEventParticipant::query()->create([
            'training_event_id' => $eventB->id,
            'participant_id' => $participant->id,
            'final_score' => 90.5,
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-events.grouped-training'));

        $response
            ->assertOk()
            ->assertSee('Participants grouped by training')
            ->assertSee('John Doe Smith')
            ->assertSee('Participant Grouping - A')
            ->assertSee('Participant Grouping - B')
            ->assertSee('89.5');

        $this->assertSame(1, substr_count($response->getContent(), 'John Doe Smith'));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
