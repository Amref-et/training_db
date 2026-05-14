<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Region;
use App\Models\Training;
use App\Models\TrainingCategory;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingEventWorkshop;
use App\Models\TrainingOrganizer;
use App\Models\User;
use App\Models\Woreda;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingWorkflowWorkshopDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_workshop_one_defaults_to_event_dates_on_training_flow_form(): void
    {
        $event = $this->trainingEventWithEnrollment();

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-workflow.index', [
                'event_id' => $event->id,
                'step' => 3,
                'workshop' => 1,
            ]));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/name="workshop_start_date"[\s\S]*?value="2026-06-10"/', $content);
        $this->assertMatchesRegularExpression('/name="workshop_end_date"[\s\S]*?value="2026-06-12"/', $content);
    }

    public function test_creating_workshop_structure_only_defaults_first_workshop(): void
    {
        $event = $this->trainingEventWithEnrollment();

        $response = $this
            ->actingAs($this->adminUser())
            ->post(route('admin.training-workflow.workshop-count.store', $event), [
                'workshop_count' => 2,
            ]);

        $response->assertRedirect(route('admin.training-workflow.index', [
            'event_id' => $event->id,
            'step' => 3,
            'workshop' => 1,
        ]));

        $workshops = TrainingEventWorkshop::query()
            ->where('training_event_id', $event->id)
            ->orderBy('workshop_number')
            ->get()
            ->keyBy('workshop_number');

        $this->assertSame('2026-06-10', $workshops->get(1)?->start_date?->toDateString());
        $this->assertSame('2026-06-12', $workshops->get(1)?->end_date?->toDateString());
        $this->assertNull($workshops->get(2)?->start_date);
        $this->assertNull($workshops->get(2)?->end_date);
    }

    public function test_default_workshop_date_can_be_changed_in_training_flow(): void
    {
        $event = $this->trainingEventWithEnrollment();

        $response = $this
            ->actingAs($this->adminUser())
            ->post(route('admin.training-workflow.workshops.save', $event), [
                'workshop_number' => 1,
                'workshop_start_date' => '2026-06-11',
                'workshop_end_date' => '2026-06-13',
            ]);

        $response->assertRedirect(route('admin.training-workflow.index', [
            'event_id' => $event->id,
            'step' => 3,
            'workshop' => 1,
        ]));

        $workshop = TrainingEventWorkshop::query()
            ->where('training_event_id', $event->id)
            ->where('workshop_number', 1)
            ->first();

        $this->assertSame('2026-06-11', $workshop?->start_date?->toDateString());
        $this->assertSame('2026-06-13', $workshop?->end_date?->toDateString());
    }

    private function trainingEventWithEnrollment(): TrainingEvent
    {
        $region = Region::query()->create(['name' => 'Addis Ababa']);
        $zone = Zone::query()->create([
            'region_id' => $region->id,
            'name' => 'Lideta',
        ]);
        $woreda = Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Lideta Health Center',
            'category' => 'Government/Public',
            'type' => 'Health Center/Clinic/Division',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
        ]);
        $participant = Participant::query()->create([
            'first_name' => 'Marta',
            'father_name' => 'Tesfaye',
            'grandfather_name' => 'Kebede',
            'date_of_birth' => '1992-04-20',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'woreda_id' => $woreda->id,
            'organization_id' => $organization->id,
            'gender' => 'female',
            'mobile_phone' => '+251911222333',
            'email' => 'marta@example.test',
            'profession' => 'Nurse',
        ]);
        $category = TrainingCategory::query()->create(['name' => 'Clinical']);
        $training = Training::query()->create([
            'training_category_id' => $category->id,
            'title' => 'Clinical Mentorship',
            'description' => 'Clinical training',
            'modality' => 'Blended',
            'type' => 'ToT',
        ]);
        $organizer = TrainingOrganizer::query()->create([
            'title' => 'HSS Project',
            'project_code' => 'HSS-001',
            'project_name' => 'Health Systems',
            'project_long_name' => 'Health Systems Strengthening Project',
            'donor' => 'USAID',
            'program' => 'HSS',
            'is_active' => true,
        ]);
        $event = TrainingEvent::query()->create([
            'event_name' => 'Clinical Mentorship Round 1',
            'training_id' => $training->id,
            'training_organizer_id' => $organizer->id,
            'training_region_id' => $region->id,
            'participant_id' => $participant->id,
            'training_city' => 'Addis Ababa',
            'course_venue' => 'Training Hall',
            'workshop_count' => 2,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'status' => 'Ongoing',
        ]);

        TrainingEventParticipant::query()->create([
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
        ]);

        return $event;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
