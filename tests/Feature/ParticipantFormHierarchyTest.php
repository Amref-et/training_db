<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Region;
use App\Models\User;
use App\Models\Woreda;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantFormHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_participant_create_form_enforces_hierarchical_select_flow(): void
    {
        $region = Region::query()->create(['name' => 'Addis Ababa']);
        $zone = Zone::query()->create([
            'region_id' => $region->id,
            'name' => 'Kolfe',
        ]);
        Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.participants.create'));

        $response
            ->assertOk()
            ->assertSee('const strictParticipantHierarchy = true;', false)
            ->assertSee('strictParticipantHierarchy && !regionId', false)
            ->assertSee('strictParticipantHierarchy && !zoneId', false)
            ->assertSee('requireWoredaForOrganizations = strictParticipantHierarchy', false)
            ->assertSee('data-region-id="'.$region->id.'"', false)
            ->assertSee('data-zone-id="'.$zone->id.'"', false)
            ->assertSee(route('admin.participants.organization-options'), false);
    }

    public function test_participant_organization_options_filter_to_selected_woreda(): void
    {
        $region = Region::query()->create(['name' => 'Addis Ababa']);
        $zone = Zone::query()->create([
            'region_id' => $region->id,
            'name' => 'Kolfe',
        ]);
        $selectedWoreda = Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);
        $otherWoreda = Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 2',
        ]);
        $includedOrganization = Organization::query()->create([
            'name' => 'Woreda 1 Health Center',
            'category' => 'Government/Public',
            'type' => 'Health Center/Clinic/Division',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $selectedWoreda->id,
        ]);
        $excludedOrganization = Organization::query()->create([
            'name' => 'Woreda 2 Health Center',
            'category' => 'Government/Public',
            'type' => 'Health Center/Clinic/Division',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $otherWoreda->id,
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->getJson(route('admin.participants.organization-options', [
                'region_id' => $region->id,
                'zone_id' => $zone->id,
                'woreda_id' => $selectedWoreda->id,
            ]));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'value' => $includedOrganization->id,
                'label' => $includedOrganization->name,
            ])
            ->assertJsonMissing([
                'value' => $excludedOrganization->id,
                'label' => $excludedOrganization->name,
            ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
