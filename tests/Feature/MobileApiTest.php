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
use App\Models\TrainingEventWorkshopScore;
use App\Models\TrainingOrganizer;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Models\Woreda;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_issues_sanctum_token_and_logout_revokes_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'mobile@example.test',
        ]);
        $user->syncRoles(['Admin']);

        $loginResponse = $this->postJson('/api/mobile/login', [
            'email' => 'mobile@example.test',
            'password' => 'password',
            'device_name' => 'Ionic Dev App',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'mobile@example.test')
            ->assertJsonPath('data.abilities.0', 'reference-data:read')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'expires_at',
                    'abilities',
                    'user' => ['id', 'name', 'email', 'roles', 'permissions'],
                ],
            ]);

        $token = (string) $loginResponse->json('data.access_token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Ionic Dev App',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'mobile@example.test')
            ->assertJsonPath('data.token.name', 'Ionic Dev App');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/me')
            ->assertUnauthorized();
    }

    public function test_mobile_participant_registration_can_submit_join_request_without_web_session(): void
    {
        [$participantSeed, $event] = $this->participantAndEvent();

        $response = $this->postJson('/api/mobile/participant-registration', [
            'first_name' => 'Selam',
            'father_name' => 'Tesfaye',
            'grandfather_name' => 'Lema',
            'age' => 31,
            'region_id' => $participantSeed->region_id,
            'zone_id' => $participantSeed->zone_id,
            'woreda_id' => $participantSeed->woreda_id,
            'organization_id' => $participantSeed->organization_id,
            'gender' => 'female',
            'mobile_phone' => '0922334455',
            'profession' => $participantSeed->profession,
            'training_event_id' => $event->id,
            'requested_message' => 'Please enroll me after registration.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.join_request.status', 'pending');

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

    public function test_mobile_join_request_endpoint_matches_participant_by_name_and_phone(): void
    {
        [$participant, $event] = $this->participantAndEvent();

        $response = $this->postJson('/api/mobile/training-event-join-request', [
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
            'participant_name' => $participant->name,
            'mobile_phone' => '0911223344',
            'requested_message' => 'Please include me in this event.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.join_request.status', TrainingEventJoinRequest::STATUS_PENDING);

        $this->assertDatabaseHas('training_event_join_requests', [
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => 'Please include me in this event.',
        ]);
    }

    public function test_mobile_authenticated_user_can_enroll_selected_participant_into_event(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'enroller@example.test',
        ]);
        $user->syncRoles(['Admin']);

        [$participant, $event] = $this->participantAndEvent();

        $loginResponse = $this->postJson('/api/mobile/login', [
            'email' => 'enroller@example.test',
            'password' => 'password',
            'device_name' => 'Ionic Dev App',
        ]);

        $token = (string) $loginResponse->json('data.access_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/training-events/'.$event->id.'/enrollments', [
                'participant_id' => $participant->id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'enrolled')
            ->assertJsonPath('data.participant.id', $participant->id)
            ->assertJsonPath('message', 'Participant enrolled successfully.');

        $this->assertDatabaseHas('training_event_participants', [
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
        ]);

        $duplicateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/training-events/'.$event->id.'/enrollments', [
                'participant_id' => $participant->id,
            ]);

        $duplicateResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'already_enrolled')
            ->assertJsonPath('message', 'Participant is already enrolled in the selected training event.');

        $this->assertSame(1, TrainingEventParticipant::query()->count());
    }

    public function test_mobile_training_events_can_be_filtered_to_ongoing_and_upcoming(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'events@example.test',
        ]);
        $user->syncRoles(['Admin']);

        [, $upcomingEvent] = $this->participantAndEvent();
        $upcomingEvent->update([
            'event_name' => 'Upcoming Training',
            'status' => 'Up coming',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        $ongoingEvent = TrainingEvent::query()->create([
            'event_name' => 'Ongoing Training',
            'training_id' => $upcomingEvent->training_id,
            'training_organizer_id' => $upcomingEvent->training_organizer_id,
            'training_region_id' => $upcomingEvent->training_region_id,
            'training_city' => $upcomingEvent->training_city,
            'course_venue' => $upcomingEvent->course_venue,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'Ongoing',
        ]);

        $completedEvent = TrainingEvent::query()->create([
            'event_name' => 'Completed Training',
            'training_id' => $upcomingEvent->training_id,
            'training_organizer_id' => $upcomingEvent->training_organizer_id,
            'training_region_id' => $upcomingEvent->training_region_id,
            'training_city' => $upcomingEvent->training_city,
            'course_venue' => $upcomingEvent->course_venue,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(8)->toDateString(),
            'status' => 'Completed',
        ]);

        $loginResponse = $this->postJson('/api/mobile/login', [
            'email' => 'events@example.test',
            'password' => 'password',
            'device_name' => 'Ionic Dev App',
        ]);

        $token = (string) $loginResponse->json('data.access_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/training-events?per_page=25&statuses[]=Ongoing&statuses[]=Up%20coming');

        $response->assertOk();

        $eventIds = collect($response->json('data'))->pluck('id');

        $this->assertTrue($eventIds->contains($ongoingEvent->id));
        $this->assertTrue($eventIds->contains($upcomingEvent->id));
        $this->assertFalse($eventIds->contains($completedEvent->id));
    }

    public function test_mobile_training_workflow_can_review_requests_and_save_workshop_scores(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'workflow@example.test',
        ]);
        $user->syncRoles(['Admin']);

        [$participant, $event] = $this->participantAndEvent();

        $joinRequest = TrainingEventJoinRequest::query()->create([
            'training_event_id' => $event->id,
            'participant_id' => $participant->id,
            'status' => TrainingEventJoinRequest::STATUS_PENDING,
            'requested_message' => 'Please approve me.',
            'requested_at' => now(),
        ]);

        $loginResponse = $this->postJson('/api/mobile/login', [
            'email' => 'workflow@example.test',
            'password' => 'password',
            'device_name' => 'Ionic Dev App',
        ]);

        $token = (string) $loginResponse->json('data.access_token');

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/training-workflow/events')
            ->assertOk()
            ->assertJsonPath('data.events.0.event.id', $event->id)
            ->assertJsonPath('data.events.0.pending_join_requests_count', 1);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/training-workflow/events/'.$event->id.'/join-requests/'.$joinRequest->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.join_requests.0.status', TrainingEventJoinRequest::STATUS_APPROVED)
            ->assertJsonPath('data.enrollments.0.participant.id', $participant->id);

        $enrollment = TrainingEventParticipant::query()->firstOrFail();

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/training-workflow/events/'.$event->id.'/workshop-count', [
                'workshop_count' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.event.workshop_count', 2)
            ->assertJsonPath('data.workshops.1.workshop_number', 2);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/training-workflow/events/'.$event->id.'/workshops', [
                'workshop_number' => 1,
                'scores' => [
                    [
                        'enrollment_id' => $enrollment->id,
                        'pre_test_score' => 40,
                        'mid_test_score' => 50,
                        'post_test_score' => 60,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.workshops.0.progress.completed', 1)
            ->assertJsonPath('data.enrollments.0.scores.0.post_test_score', 60);

        $this->assertDatabaseHas('training_event_workshop_scores', [
            'training_event_participant_id' => $enrollment->id,
            'workshop_number' => 1,
            'pre_test_score' => 40,
            'mid_test_score' => 50,
            'post_test_score' => 60,
        ]);

        $this->assertNull($enrollment->fresh()->final_score);

        $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/training-workflow/events/'.$event->id.'/workshops', [
                'workshop_number' => 2,
                'scores' => [
                    [
                        'enrollment_id' => $enrollment->id,
                        'pre_test_score' => 50,
                        'post_test_score' => 80,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.summary.with_final_scores', 1)
            ->assertJsonPath('data.enrollments.0.final_score', 70);

        $this->assertSame(2, TrainingEventWorkshopScore::query()->count());
        $this->assertSame('70.00', (string) $enrollment->fresh()->final_score);
    }

    public function test_mobile_training_workflow_can_update_closeout(): void
    {
        Storage::fake('public');

        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'closeout@example.test',
        ]);
        $user->syncRoles(['Admin']);

        [, $event] = $this->participantAndEvent();

        $loginResponse = $this->postJson('/api/mobile/login', [
            'email' => 'closeout@example.test',
            'password' => 'password',
            'device_name' => 'Ionic Dev App',
        ]);

        $token = (string) $loginResponse->json('data.access_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('Accept', 'application/json')
            ->post('/api/mobile/training-workflow/events/'.$event->id.'/closeout', [
                'status' => 'Completed',
                'training_event_report' => UploadedFile::fake()->create('final-report.pdf', 64, 'application/pdf'),
                'training_event_pictures' => [
                    UploadedFile::fake()->image('event-photo.jpg'),
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.event.status', 'Completed')
            ->assertJsonPath('data.closeout.pictures.0.path', fn ($path) => is_string($path) && str_contains($path, 'training-events/'.$event->id.'/pictures/'))
            ->assertJsonPath('message', 'Training event closeout updated.');

        $event->refresh();

        $this->assertNotNull($event->training_event_report_path);
        $this->assertCount(1, $event->training_event_picture_paths);
        Storage::disk('public')->assertExists($event->training_event_report_path);
        Storage::disk('public')->assertExists($event->training_event_picture_paths[0]);
    }

    public function test_mobile_public_option_endpoints_return_json_for_ionic_forms(): void
    {
        [$participant, $event] = $this->participantAndEvent();

        $this->getJson('/api/mobile/participant-registration/options')
            ->assertOk()
            ->assertJsonPath('data.regions.0.id', $participant->region_id)
            ->assertJsonFragment(['name' => $participant->profession]);

        $this->getJson('/api/mobile/participant-registration/organization-options?q=Health')
            ->assertOk()
            ->assertJsonPath('data.options.0.value', $participant->organization_id);

        $this->getJson('/api/mobile/training-event-join-request/options')
            ->assertOk()
            ->assertJsonPath('data.events.0.id', $event->id);

        $this->getJson('/api/mobile/training-event-join-request/participant-options?q=Amina')
            ->assertOk()
            ->assertJsonPath('data.options.0.value', $participant->id)
            ->assertJsonPath('data.options.0.mobile_phone', '0911223344');
    }

    public function test_mobile_appearance_endpoint_returns_brand_settings_and_logo_urls(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['id' => 1],
            array_merge(WebsiteSetting::defaults(), [
                'site_name' => 'HIL Field App',
                'site_tagline' => 'Training operations in the field',
                'header_logo_url' => 'https://example.test/logo.png',
                'body_accent_color' => '#0f766e',
                'login_form_title' => 'Field sign in',
            ])
        );

        $this->getJson('/api/mobile/appearance')
            ->assertOk()
            ->assertJsonPath('data.site.name', 'HIL Field App')
            ->assertJsonPath('data.site.tagline', 'Training operations in the field')
            ->assertJsonPath('data.logos.header_url', 'https://example.test/logo.png')
            ->assertJsonPath('data.colors.body_accent', '#0f766e')
            ->assertJsonPath('data.login.form_title', 'Field sign in');
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
