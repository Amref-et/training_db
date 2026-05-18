<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\ProjectSubawardee;
use App\Models\Region;
use App\Models\Training;
use App\Models\TrainingCategory;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingEventWorkshopScore;
use App\Models\TrainingOrganizer;
use App\Models\User;
use App\Models\Woreda;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantTrainingParticipationExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_training_participation_export_matches_participation_query_csv(): void
    {
        $region = Region::query()->create(['name' => 'Addis Ababa']);
        $zone = Zone::query()->create([
            'region_id' => $region->id,
            'name' => 'Kolfe',
        ]);
        $woreda = Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Kolfe Health Center',
            'category' => 'Government/Public',
            'type' => 'Health Center/Clinic/Division',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
        ]);
        Participant::query()->create([
            'first_name' => 'Unused',
            'father_name' => 'Participant',
            'grandfather_name' => 'Record',
            'date_of_birth' => '1980-01-01',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'woreda_id' => $woreda->id,
            'organization_id' => $organization->id,
            'gender' => 'male',
            'mobile_phone' => '+251900000000',
            'profession' => 'Doctor',
        ]);
        $participant = Participant::query()->create([
            'first_name' => 'Sara',
            'father_name' => 'Bekele',
            'grandfather_name' => 'Alemu',
            'date_of_birth' => '1990-02-10',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'woreda_id' => $woreda->id,
            'organization_id' => $organization->id,
            'gender' => 'female',
            'home_phone' => '+251111111111',
            'mobile_phone' => '+251911111111',
            'email' => 'sara@example.test',
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
        $subawardee = ProjectSubawardee::query()->create([
            'project_id' => $organizer->id,
            'subawardee_name' => 'Regional Partner',
        ]);
        $event = TrainingEvent::query()->create([
            'event_name' => 'Clinical Mentorship Round 1',
            'training_id' => $training->id,
            'training_organizer_id' => $organizer->id,
            'organizer_type' => 'Subawardee',
            'project_subawardee_id' => $subawardee->id,
            'training_region_id' => $region->id,
            'participant_id' => $participant->id,
            'training_city' => 'Addis Ababa',
            'course_venue' => 'Training Hall',
            'workshop_count' => 8,
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-12',
            'status' => 'Completed',
        ]);
        $enrollment = TrainingEventParticipant::query()->create([
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
            'mid_test_score' => 55,
            'activity_completion_status' => 'Completed',
            'is_trainer' => true,
            'trainer_name' => 'Lead Trainer',
            'trainer_comments' => 'Strong participation',
        ]);

        TrainingEventWorkshopScore::query()->create([
            'training_event_participant_id' => $enrollment->id,
            'workshop_number' => 1,
            'pre_test_score' => 10,
            'mid_test_score' => 20,
            'post_test_score' => 30,
        ]);
        TrainingEventWorkshopScore::query()->create([
            'training_event_participant_id' => $enrollment->id,
            'workshop_number' => 8,
            'pre_test_score' => 70,
            'mid_test_score' => 80,
            'post_test_score' => 90,
        ]);
        $enrollment->forceFill(['final_score' => 88.5])->saveQuietly();

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.participants.training-participation.export'));

        $response->assertOk();

        [$header, $row] = $this->firstCsvRow($response->streamedContent());

        $this->assertSame([
            'participant_id',
            'participant_code',
            'first_name',
            'father_name',
            'grandfather_name',
            'participant_name',
            'gender',
            'date_of_birth',
            'age',
            'home_phone',
            'mobile_phone',
            'email',
            'profession',
            'participant_region',
            'participant_zone',
            'participant_woreda',
            'organization_name',
            'organization_category',
            'organization_type',
            'organization_region',
            'organization_zone',
            'organization_woreda',
            'training_event_id',
            'event_name',
            'training_title',
            'training_category',
            'training_modality',
            'training_type',
            'event_start_date',
            'event_end_date',
            'event_status',
            'event_region',
            'event_city',
            'event_venue',
            'event_organizer_type',
            'project_subawardee',
            'organizer_name',
            'project_code',
            'project_name',
            'project_long_name',
            'donor',
            'program',
            'participation_status',
            'is_trainer',
            'trainer_name',
            'trainer_comments',
            'final_score',
            'mid_test_score',
            'avg_pre_score',
            'avg_post_score',
            'workshop_count',
            'completed_workshops',
        ], $header);

        $this->assertSame((string) $enrollment->id, $row['participant_id']);
        $this->assertSame('Sara Bekele Alemu', $row['participant_name']);
        $this->assertSame('female', $row['gender']);
        $this->assertSame('Addis Ababa', $row['participant_region']);
        $this->assertSame('Kolfe', $row['participant_zone']);
        $this->assertSame('Woreda 1', $row['participant_woreda']);
        $this->assertSame('Kolfe Health Center', $row['organization_name']);
        $this->assertSame('Government/Public', $row['organization_category']);
        $this->assertSame('Health Center/Clinic/Division', $row['organization_type']);
        $this->assertSame('Addis Ababa', $row['organization_region']);
        $this->assertSame('Kolfe', $row['organization_zone']);
        $this->assertSame('Woreda 1', $row['organization_woreda']);
        $this->assertSame('Clinical Mentorship', $row['training_title']);
        $this->assertSame('Clinical', $row['training_category']);
        $this->assertSame('Blended', $row['training_modality']);
        $this->assertSame('ToT', $row['training_type']);
        $this->assertSame('Clinical Mentorship Round 1', $row['event_name']);
        $this->assertSame('2026-01-05', $row['event_start_date']);
        $this->assertSame('2026-01-12', $row['event_end_date']);
        $this->assertSame('Completed', $row['event_status']);
        $this->assertSame('Addis Ababa', $row['event_region']);
        $this->assertSame('Addis Ababa', $row['event_city']);
        $this->assertSame('Training Hall', $row['event_venue']);
        $this->assertSame('Subawardee', $row['event_organizer_type']);
        $this->assertSame('Regional Partner', $row['project_subawardee']);
        $this->assertSame('Health Systems', $row['organizer_name']);
        $this->assertSame('HSS-001', $row['project_code']);
        $this->assertSame('Health Systems', $row['project_name']);
        $this->assertSame('Health Systems Strengthening Project', $row['project_long_name']);
        $this->assertSame('USAID', $row['donor']);
        $this->assertSame('HSS', $row['program']);
        $this->assertSame('Completed', $row['participation_status']);
        $this->assertSame('yes', $row['is_trainer']);
        $this->assertSame('Lead Trainer', $row['trainer_name']);
        $this->assertSame('Strong participation', $row['trainer_comments']);
        $this->assertEqualsWithDelta(88.5, (float) $row['final_score'], 0.01);
        $this->assertEqualsWithDelta(55.0, (float) $row['mid_test_score'], 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $row['avg_pre_score'], 0.01);
        $this->assertEqualsWithDelta(60.0, (float) $row['avg_post_score'], 0.01);
        $this->assertSame('8', $row['workshop_count']);
        $this->assertSame('2', $row['completed_workshops']);
    }

    private function firstCsvRow(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($content)) ?: []));
        $header = str_getcsv($lines[0] ?? '');
        $values = str_getcsv($lines[1] ?? '');

        return [$header, array_combine($header, $values) ?: []];
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
