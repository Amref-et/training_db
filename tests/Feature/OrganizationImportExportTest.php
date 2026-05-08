<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Region;
use App\Models\User;
use App\Models\Woreda;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrganizationImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_mfr_facility_import_uses_external_ids_for_hierarchy_and_organizations(): void
    {
        $csv = implode("\n", [
            'region,zone,woreda,organization_id,organization,facility,name,category,type,region_id,region_name,zone_id,zone_name,woreda_id,woreda_name,city_town,phone,fax',
            'Addis Ababa City Administration,Kolfe Sub city,Woreda 1,1000932,ALERT Comprehensive Specialized Hospital,ALERT Comprehensive Specialized Hospital,ALERT Comprehensive Specialized Hospital,,Hospital,1,Addis Ababa City Administration,212,Kolfe Sub city,1401,Woreda 1,,,',
            'Addis Ababa City Administration,Gulele Sub City,Woreda 1,1000942,St. Peter General Hospital,St. Peter General Hospital,St. Peter General Hospital,,Hospital,1,Addis Ababa City Administration,157,Gulele Sub City,1401,Woreda 1,,,',
        ]);

        $file = UploadedFile::fake()->createWithContent('mfr-facilities.csv', $csv);

        $response = $this
            ->actingAs($this->adminUser())
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.import'), [
                'import_file' => $file,
            ]);

        $response
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('success', 'Organization import completed: 2 created, 0 updated.');

        $region = Region::query()->where('external_id', '1')->firstOrFail();
        $kolfeZone = Zone::query()->where('external_id', '212')->firstOrFail();
        $guleleZone = Zone::query()->where('external_id', '157')->firstOrFail();

        $this->assertSame('Addis Ababa City Administration', $region->name);
        $this->assertSame($region->id, $kolfeZone->region_id);
        $this->assertSame($region->id, $guleleZone->region_id);

        $kolfeWoreda = Woreda::query()
            ->where('external_id', '1401')
            ->where('zone_id', $kolfeZone->id)
            ->firstOrFail();
        $guleleWoreda = Woreda::query()
            ->where('external_id', '1401')
            ->where('zone_id', $guleleZone->id)
            ->firstOrFail();

        $this->assertNotSame($kolfeWoreda->id, $guleleWoreda->id);

        $this->assertDatabaseHas('organizations', [
            'external_id' => '1000932',
            'name' => 'ALERT Comprehensive Specialized Hospital',
            'region_id' => $region->id,
            'zone_id' => $kolfeZone->id,
            'woreda_id' => $kolfeWoreda->id,
            'category' => 'Private',
            'type' => 'Hospital',
        ]);

        $this->assertDatabaseHas('organizations', [
            'external_id' => '1000942',
            'name' => 'St. Peter General Hospital',
            'region_id' => $region->id,
            'zone_id' => $guleleZone->id,
            'woreda_id' => $guleleWoreda->id,
            'category' => 'Private',
            'type' => 'Hospital',
        ]);
    }

    public function test_organization_export_includes_external_id_template_columns(): void
    {
        $region = Region::query()->create([
            'external_id' => '1',
            'name' => 'Addis Ababa City Administration',
        ]);
        $zone = Zone::query()->create([
            'external_id' => '212',
            'region_id' => $region->id,
            'name' => 'Kolfe Sub city',
        ]);
        $woreda = Woreda::query()->create([
            'external_id' => '1401',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);
        Organization::query()->create([
            'external_id' => '1000932',
            'name' => 'ALERT Comprehensive Specialized Hospital',
            'category' => 'Private',
            'type' => 'Hospital',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.organizations.export'));

        $response->assertOk();

        $rows = collect(preg_split('/\r\n|\r|\n/', trim($response->streamedContent())))
            ->filter()
            ->map(fn (string $line): array => str_getcsv($line))
            ->values();

        $this->assertSame([
            'region',
            'zone',
            'woreda',
            'organization_id',
            'organization',
            'facility',
            'name',
            'category',
            'type',
            'region_id',
            'region_name',
            'zone_id',
            'zone_name',
            'zone',
            'woreda_id',
            'woreda_name',
            'city_town',
            'phone',
            'fax',
        ], $rows[0]);

        $this->assertSame('1000932', $rows[1][3]);
        $this->assertSame('1', $rows[1][9]);
        $this->assertSame('212', $rows[1][11]);
        $this->assertSame('1401', $rows[1][14]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
