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

        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('const strictParticipantHierarchy = true;', false)
            ->assertSee('strictParticipantHierarchy && !regionId', false)
            ->assertSee('strictParticipantHierarchy && !zoneId', false)
            ->assertSee('Selecting an organization fills Region, Zone, and Woreda when available.', false)
            ->assertSee('setSelectValue(regionSelect, regionId);', false)
            ->assertSee('await filterZones(zoneId || null);', false)
            ->assertSee('await filterWoredas(woredaId || null);', false)
            ->assertSee(route('admin.participants.organization-options'), false)
            ->assertSee(route('admin.participants.zone-options'), false)
            ->assertSee(route('admin.participants.woreda-options'), false);

        $this->assertLessThan(
            strpos($html, 'id="select-region-id"'),
            strpos($html, 'id="select-organization-id"')
        );
        $this->assertStringNotContainsString('requireWoredaForOrganizations = strictParticipantHierarchy', $html);
        $this->assertStringNotContainsString('Kolfe</option>', $html);
        $this->assertStringNotContainsString('Woreda 1</option>', $html);
        $this->assertStringNotContainsString('organizationSelect.tomselect.load', $html);
    }

    public function test_participant_zone_options_filter_to_selected_region(): void
    {
        $selectedRegion = Region::query()->create(['name' => 'Addis Ababa']);
        $otherRegion = Region::query()->create(['name' => 'Oromia']);
        $includedZone = Zone::query()->create([
            'region_id' => $selectedRegion->id,
            'name' => 'Kolfe',
        ]);
        $excludedZone = Zone::query()->create([
            'region_id' => $otherRegion->id,
            'name' => 'Adama',
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->getJson(route('admin.participants.zone-options', [
                'region_id' => $selectedRegion->id,
            ]));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'value' => $includedZone->id,
                'label' => $includedZone->name,
            ])
            ->assertJsonMissing([
                'value' => $excludedZone->id,
                'label' => $excludedZone->name,
            ]);
    }

    public function test_participant_woreda_options_filter_to_selected_zone(): void
    {
        $region = Region::query()->create(['name' => 'Addis Ababa']);
        $selectedZone = Zone::query()->create([
            'region_id' => $region->id,
            'name' => 'Kolfe',
        ]);
        $otherZone = Zone::query()->create([
            'region_id' => $region->id,
            'name' => 'Bole',
        ]);
        $includedWoreda = Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $selectedZone->id,
            'name' => 'Woreda 1',
        ]);
        $excludedWoreda = Woreda::query()->create([
            'region_id' => $region->id,
            'zone_id' => $otherZone->id,
            'name' => 'Woreda 2',
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->getJson(route('admin.participants.woreda-options', [
                'region_id' => $region->id,
                'zone_id' => $selectedZone->id,
            ]));

        $response
            ->assertOk()
            ->assertJsonFragment([
                'value' => $includedWoreda->id,
                'label' => $includedWoreda->name,
            ])
            ->assertJsonMissing([
                'value' => $excludedWoreda->id,
                'label' => $excludedWoreda->name,
            ]);
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
