<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Region;
use App\Models\Woreda;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicParticipantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_participant_registration_accepts_missing_email(): void
    {
        [$region, $zone, $woreda, $organization, $profession] = $this->participantDependencies();

        $response = $this->post('/participant-registration', [
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
            'profession' => $profession->name,
        ]);

        $response
            ->assertRedirect(route('participant-registration.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('participant_registration');

        $participant = Participant::query()->firstOrFail();

        $this->assertSame('Amina Bekele Chala', $participant->name);
        $this->assertNull($participant->email);
    }

    public function test_public_participant_registration_validates_phone_numbers_and_age(): void
    {
        [$region, $zone, $woreda, $organization, $profession] = $this->participantDependencies();

        $response = $this
            ->from('/participant-registration')
            ->post('/participant-registration', [
                'first_name' => 'Amina',
                'father_name' => 'Bekele',
                'grandfather_name' => 'Chala',
                'age' => 121,
                'region_id' => $region->id,
                'zone_id' => $zone->id,
                'woreda_id' => $woreda->id,
                'organization_id' => $organization->id,
                'gender' => 'female',
                'home_phone' => '123',
                'mobile_phone' => 'not-a-phone',
                'profession' => $profession->name,
            ]);

        $response
            ->assertRedirect('/participant-registration')
            ->assertSessionHasErrors(['age', 'home_phone', 'mobile_phone']);

        $this->assertDatabaseCount('participants', 0);
    }

    public function test_organization_search_options_include_hierarchy_for_autofill(): void
    {
        [$region, $zone, $woreda, $organization] = $this->participantDependencies();

        $response = $this->getJson('/participant-registration/organization-options?q=Health');

        $response
            ->assertOk()
            ->assertJsonPath('options.0.value', $organization->id)
            ->assertJsonPath('options.0.region_id', $region->id)
            ->assertJsonPath('options.0.zone_id', $zone->id)
            ->assertJsonPath('options.0.woreda_id', $woreda->id);

        $this->get('/participant-registration')
            ->assertOk()
            ->assertSee('applyOrganizationHierarchy', false)
            ->assertSeeInOrder([
                'for="organization_id"',
                'for="region_id"',
                'for="zone_id"',
                'for="woreda_id"',
            ], false);
    }

    private function participantDependencies(): array
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

        return [$region, $zone, $woreda, $organization, $profession];
    }
}
