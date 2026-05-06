<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Region;
use App\Models\Training;
use App\Models\TrainingEvent;
use App\Models\TrainingEventJoinRequest;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingOrganizer;
use App\Models\User;
use App\Models\Woreda;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingEventJoinRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_request_to_join_training_event(): void
    {
        [$participant, $event] = $this->participantAndEvent();

        $response = $this
            ->from('/training-event-join-request')
            ->post('/training-event-join-request', [
                'training_event_id' => $event->id,
                'participant_id' => $participant->id,
                'participant_name' => $participant->name,
                'mobile_phone' => '0911223344',
                'requested_message' => 'Please include me in this event.',
            ]);

        $response
            ->assertRedirect(route('training-event-join-requests.create'))
            ->assertSessionHas('success', 'Your request has been submitted and is pending approval.');

        $this->assertDatabaseHas('training_event_join_requests', [
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => 'Please include me in this event.',
        ]);

        $this->assertDatabaseMissing('training_event_participants', [
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
        ]);
    }

    public function test_participant_name_autosuggest_returns_matching_participants(): void
    {
        [$participant] = $this->participantAndEvent();

        $response = $this->getJson('/training-event-join-request/participant-options?q=Amina');

        $response
            ->assertOk()
            ->assertJsonPath('options.0.value', $participant->id)
            ->assertJsonPath('options.0.label', $participant->name)
            ->assertJsonPath('options.0.hint', 'Registered phone ending 3344');
    }

    public function test_join_request_form_can_send_unregistered_participants_to_registration(): void
    {
        [, $event] = $this->participantAndEvent();

        $response = $this->get('/training-event-join-request?training_event_id='.$event->id);

        $response
            ->assertOk()
            ->assertSee('data-registration-request-url="'.route('training-event-join-requests.register').'"', false)
            ->assertSee('Register and request event');
    }

    public function test_unregistered_participant_registration_submits_pending_training_request(): void
    {
        [$existingParticipant, $event] = $this->participantAndEvent();

        $response = $this
            ->from('/training-event-join-request')
            ->post('/training-event-join-request/register', [
                'training_event_id' => $event->id,
                'participant_name' => 'Selam Tesfaye Lema',
                'mobile_phone' => '0922334455',
                'requested_message' => 'Please enroll me after registration.',
            ]);

        $response
            ->assertRedirect(route('participant-registration.create'))
            ->assertSessionHas('pending_training_event_join_request.training_event_id', $event->id);

        $this->get('/participant-registration')
            ->assertOk()
            ->assertSee('Complete participant registration to submit your request for May Training.')
            ->assertSee('value="Selam"', false)
            ->assertSee('value="Tesfaye"', false)
            ->assertSee('value="Lema"', false)
            ->assertSee('value="0922334455"', false);

        $registrationResponse = $this->post('/participant-registration', [
            'first_name' => 'Selam',
            'father_name' => 'Tesfaye',
            'grandfather_name' => 'Lema',
            'age' => 31,
            'region_id' => $existingParticipant->region_id,
            'zone_id' => $existingParticipant->zone_id,
            'woreda_id' => $existingParticipant->woreda_id,
            'organization_id' => $existingParticipant->organization_id,
            'gender' => 'female',
            'mobile_phone' => '0922334455',
            'profession' => $existingParticipant->profession,
        ]);

        $registrationResponse
            ->assertRedirect(route('participant-registration.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Registration submitted successfully. Your request to join May Training has also been submitted and is pending approval.')
            ->assertSessionMissing('pending_training_event_join_request');

        $participant = Participant::query()
            ->where('mobile_phone', '0922334455')
            ->firstOrFail();

        $this->assertDatabaseHas('training_event_join_requests', [
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => 'Please enroll me after registration.',
        ]);
    }

    public function test_join_request_rejects_name_phone_mismatch(): void
    {
        [$participant, $event] = $this->participantAndEvent();

        $response = $this
            ->from('/training-event-join-request')
            ->post('/training-event-join-request', [
                'training_event_id' => $event->id,
                'participant_id' => $participant->id,
                'participant_name' => $participant->name,
                'mobile_phone' => '0999999999',
            ]);

        $response
            ->assertRedirect('/training-event-join-request')
            ->assertSessionHasErrors('participant_name');

        $this->assertDatabaseMissing('training_event_join_requests', [
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
        ]);
    }

    public function test_admin_approval_enrolls_requested_participant_in_training_workflow_step_two(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        [$participant, $event] = $this->participantAndEvent();
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        $joinRequest = TrainingEventJoinRequest::query()->create([
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => 'Please include me.',
            'requested_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/admin/training-workflow/join-requests/'.$joinRequest->id.'/approve');

        $response
            ->assertRedirect(route('admin.training-workflow.index', ['event_id' => $event->id, 'step' => 2]))
            ->assertSessionHas('success', 'Join request approved and participant enrolled in Step 2.');

        $enrollment = TrainingEventParticipant::query()
            ->where('training_event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->firstOrFail();

        $joinRequest->refresh();

        $this->assertSame(TrainingEventJoinRequest::STATUS_APPROVED, $joinRequest->status);
        $this->assertSame($enrollment->id, $joinRequest->enrollment_id);
        $this->assertSame($user->id, $joinRequest->reviewed_by);
        $this->assertNotNull($joinRequest->reviewed_at);
    }

    private function participantAndEvent(): array
    {
        $region = Region::query()->create([
            'name' => 'Addis Ababa',
        ]);

        $zone = Zone::query()->create([
            'region_id' => $region->id,
            'name' => 'Addis Ababa Zone',
            'description' => null,
        ]);

        $woreda = Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Bole',
            'description' => null,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Health Bureau',
            'category' => 'Government/Public',
            'type' => 'MOH/RHB/ZHD/Wor. HO',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
            'city_town' => 'Addis Ababa',
            'phone' => null,
            'fax' => null,
        ]);

        $profession = Profession::query()->firstOrCreate(
            ['name' => 'Nurse'],
            ['description' => null, 'sort_order' => 1, 'is_active' => true]
        );

        $participant = Participant::query()->create([
            'first_name' => 'Amina',
            'father_name' => 'Bekele',
            'grandfather_name' => 'Chala',
            'age' => 28,
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'woreda_id' => $woreda->id,
            'organization_id' => $organization->id,
            'gender' => 'female',
            'mobile_phone' => '0911223344',
            'email' => null,
            'profession' => $profession->name,
        ]);

        $training = Training::query()->create([
            'title' => 'HIV Case Management',
            'description' => null,
            'modality' => 'Face 2 face',
            'type' => 'Basic',
        ]);

        $organizer = TrainingOrganizer::query()->create([
            'title' => 'Project Alpha',
            'project_code' => 'PROJ-ALPHA',
            'project_name' => 'Project Alpha',
        ]);

        $event = TrainingEvent::query()->create([
            'event_name' => 'May Training',
            'training_id' => $training->id,
            'training_organizer_id' => $organizer->id,
            'training_region_id' => $region->id,
            'training_city' => 'Addis Ababa',
            'course_venue' => 'Main Hall',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
            'status' => 'Pending',
        ]);

        return [$participant, $event];
    }
}
