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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationImportExportTest extends TestCase
{
    use RefreshDatabase;

    private const ORGANIZATION_IMPORT_TEMPLATE_HEADERS = [
        'region_id',
        'region_name',
        'zone_id',
        'zone_name',
        'woreda_id',
        'woreda_name',
        'organization_id',
        'organization',
        'category',
        'type',
        'city_town',
        'phone',
        'fax',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_mfr_facility_import_uses_external_ids_for_hierarchy_and_organizations(): void
    {
        $csv = implode("\n", [
            implode(',', self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS),
            '1,Addis Ababa City Administration,212,Kolfe Sub city,1401,Woreda 1,1000932,ALERT Comprehensive Specialized Hospital,,Hospital,,,',
            '1,Addis Ababa City Administration,157,Gulele Sub City,1401,Woreda 1,1000942,St. Peter General Hospital,,Hospital,,,',
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

        $this->assertSame(self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS, $rows[0]);

        $this->assertSame([
            '1',
            'Addis Ababa City Administration',
            '212',
            'Kolfe Sub city',
            '1401',
            'Woreda 1',
            '1000932',
            'ALERT Comprehensive Specialized Hospital',
            'Private',
            'Hospital',
            '',
            '',
            '',
        ], $rows[1]);
    }

    public function test_organization_import_template_matches_export_header(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.organizations.template'));

        $response->assertOk();

        $rows = collect(preg_split('/\r\n|\r|\n/', trim($response->streamedContent())))
            ->filter()
            ->map(fn (string $line): array => str_getcsv($line))
            ->values();

        $this->assertCount(1, $rows);
        $this->assertSame(self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS, $rows[0]);
    }

    public function test_hierarchy_and_organization_indexes_show_import_ids(): void
    {
        $region = Region::query()->create([
            'external_id' => '1',
            'name' => 'Addis Ababa',
        ]);
        $zone = Zone::query()->create([
            'external_id' => '212',
            'region_id' => $region->id,
            'name' => 'Kolfe',
        ]);
        $woreda = Woreda::query()->create([
            'external_id' => '1401',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);
        Organization::query()->create([
            'external_id' => '1000932',
            'name' => 'Kolfe Specialty Clinic',
            'category' => 'Private',
            'type' => 'Hospital',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
        ]);

        $user = $this->adminUser();

        $this
            ->actingAs($user)
            ->get(route('admin.regions.index'))
            ->assertOk()
            ->assertSee('Region ID')
            ->assertSee('1');

        $this
            ->actingAs($user)
            ->get(route('admin.zones.index'))
            ->assertOk()
            ->assertSee('Zone ID')
            ->assertSee('Region ID')
            ->assertSee('212')
            ->assertSee('1');

        $this
            ->actingAs($user)
            ->get(route('admin.woredas.index'))
            ->assertOk()
            ->assertSee('Woreda ID')
            ->assertSee('Zone ID')
            ->assertSee('Region ID')
            ->assertSee('1401')
            ->assertSee('212')
            ->assertSee('1');

        $this
            ->actingAs($user)
            ->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertSee('Organization ID')
            ->assertSee('Woreda ID')
            ->assertSee('Zone ID')
            ->assertSee('Region ID')
            ->assertSee('1000932')
            ->assertSee('1401')
            ->assertSee('212')
            ->assertSee('1')
            ->assertSee('Choose import mode')
            ->assertSee('Update existing records')
            ->assertSee('Force overwrite existing records');
    }

    public function test_organization_import_normalizes_common_mfr_category_and_type_values(): void
    {
        $csv = implode("\n", [
            implode(',', self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS),
            '1,Addis Ababa,212,Kolfe,1401,Woreda 1,1000932,Kolfe Specialty Clinic,Public Facility,Specialty Clinic,,,',
            '2,Oromia,300,East Shewa,400,Woreda 2,1000933,Unclassified Facility,Unknown Ownership,Unmapped Facility,,,',
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('mfr-facilities.csv', $csv),
            ]);

        $response
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('success', 'Organization import completed: 2 created, 0 updated.')
            ->assertSessionMissing('warning');

        $this->assertDatabaseHas('organizations', [
            'external_id' => '1000932',
            'name' => 'Kolfe Specialty Clinic',
            'category' => 'Government/Public',
            'type' => 'Health Center/Clinic/Division',
        ]);

        $this->assertDatabaseHas('organizations', [
            'external_id' => '1000933',
            'name' => 'Unclassified Facility',
            'category' => 'Private',
            'type' => 'Other (specify)',
        ]);
    }

    public function test_organization_update_import_preserves_existing_values_when_csv_cells_are_blank(): void
    {
        $region = Region::query()->create([
            'external_id' => '1',
            'name' => 'Addis Ababa',
        ]);
        $zone = Zone::query()->create([
            'external_id' => '212',
            'region_id' => $region->id,
            'name' => 'Kolfe',
        ]);
        $woreda = Woreda::query()->create([
            'external_id' => '1401',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);
        Organization::query()->create([
            'external_id' => '1000932',
            'name' => 'Kolfe Specialty Clinic',
            'category' => 'Government/Public',
            'type' => 'Hospital',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
            'city_town' => 'Old City',
            'phone' => '0111111111',
            'fax' => '0222222222',
        ]);

        $csv = implode("\n", [
            implode(',', self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS),
            '1,Addis Ababa,212,Kolfe,1401,Woreda 1,1000932,Kolfe Specialty Clinic,,,,,',
        ]);

        $this
            ->actingAs($this->adminUser())
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('update-mode.csv', $csv),
                'import_mode' => 'update',
            ])
            ->assertRedirect(route('admin.organizations.index'));

        $this->assertDatabaseHas('organizations', [
            'external_id' => '1000932',
            'name' => 'Kolfe Specialty Clinic',
            'category' => 'Government/Public',
            'type' => 'Hospital',
            'city_town' => 'Old City',
            'phone' => '0111111111',
            'fax' => '0222222222',
        ]);
    }

    public function test_organization_overwrite_import_replaces_existing_values_when_csv_cells_are_blank(): void
    {
        $region = Region::query()->create([
            'external_id' => '1',
            'name' => 'Addis Ababa',
        ]);
        $zone = Zone::query()->create([
            'external_id' => '212',
            'region_id' => $region->id,
            'name' => 'Kolfe',
        ]);
        $woreda = Woreda::query()->create([
            'external_id' => '1401',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'name' => 'Woreda 1',
        ]);
        Organization::query()->create([
            'external_id' => '1000932',
            'name' => 'Kolfe Specialty Clinic',
            'category' => 'Government/Public',
            'type' => 'Hospital',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
            'city_town' => 'Old City',
            'phone' => '0111111111',
            'fax' => '0222222222',
        ]);

        $csv = implode("\n", [
            implode(',', self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS),
            '1,Addis Ababa,212,Kolfe,1401,Woreda 1,1000932,Kolfe Specialty Clinic,,,,,',
        ]);

        $this
            ->actingAs($this->adminUser())
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('overwrite-mode.csv', $csv),
                'import_mode' => 'overwrite',
            ])
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('success', 'Organization import completed: 0 created, 1 updated.');

        $this->assertDatabaseHas('organizations', [
            'external_id' => '1000932',
            'name' => 'Kolfe Specialty Clinic',
            'category' => 'Private',
            'type' => 'Other (specify)',
            'city_town' => null,
            'phone' => null,
            'fax' => null,
        ]);
    }

    public function test_organization_import_writes_skipped_rows_report(): void
    {
        Storage::fake('local');

        $user = $this->adminUser();
        $csv = implode("\n", [
            implode(',', self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS),
            '1,Addis Ababa,212,Kolfe,1401,Woreda 1,1000932,Kolfe Specialty Clinic,Private,Hospital,,,',
            '1,Addis Ababa,212,Kolfe,1401,Woreda 1,1000933,,Private,Hospital,,,',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('mfr-facilities.csv', $csv),
            ]);

        $response
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('success', 'Organization import completed: 1 created, 0 updated, 1 skipped.')
            ->assertSessionHas('warning')
            ->assertSessionHas('organization_import_report');

        $report = $response->baseResponse->getSession()->get('organization_import_report');
        Storage::disk('local')->assertExists('organization-import-reports/'.$report['file_name']);

        $download = $this
            ->actingAs($user)
            ->get(route('admin.organizations.import-report', ['report' => $report['file_name']]));

        $download->assertOk();

        $rows = collect(preg_split('/\r\n|\r|\n/', trim($download->streamedContent())))
            ->filter()
            ->map(fn (string $line): array => str_getcsv($line))
            ->values();

        $this->assertSame(array_merge([
            'line_number',
            'status',
            'reason',
        ], self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS), $rows[0]);

        $this->assertSame([
            '3',
            'skipped_not_created',
            'Organization name is required.',
            '1',
            'Addis Ababa',
            '212',
            'Kolfe',
            '1401',
            'Woreda 1',
            '1000933',
            '',
            'Private',
            'Hospital',
            '',
            '',
            '',
        ], $rows[1]);

        $this->assertSame(1, Organization::query()->count());
        $this->assertDatabaseMissing('organizations', [
            'external_id' => '1000933',
        ]);
    }

    public function test_mfr_facility_reimport_updates_existing_hierarchy_and_organization_by_external_ids(): void
    {
        $user = $this->adminUser();

        $initialCsv = implode("\n", [
            implode(',', self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS),
            '1,Old Region,212,Old Zone,1401,Old Woreda,1000932,Old Facility,Private,Hospital,Old City,0111111111,0222222222',
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('initial-mfr.csv', $initialCsv),
            ])
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('success', 'Organization import completed: 1 created, 0 updated.');

        $updateCsv = implode("\n", [
            implode(',', self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS),
            '1,Updated Region,212,Updated Zone,1401,Updated Woreda,1000932,Updated Facility,Government/Public,Hospital,New City,0333333333,0444444444',
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.organizations.index'))
            ->post(route('admin.organizations.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('updated-mfr.csv', $updateCsv),
            ])
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionHas('success', 'Organization import completed: 0 created, 1 updated.');

        $this->assertSame(1, Region::query()->where('external_id', '1')->count());
        $this->assertSame(1, Zone::query()->where('external_id', '212')->count());
        $this->assertSame(1, Woreda::query()->where('external_id', '1401')->count());
        $this->assertSame(1, Organization::query()->where('external_id', '1000932')->count());

        $region = Region::query()->where('external_id', '1')->firstOrFail();
        $zone = Zone::query()->where('external_id', '212')->firstOrFail();
        $woreda = Woreda::query()->where('external_id', '1401')->firstOrFail();

        $this->assertSame('Updated Region', $region->name);
        $this->assertSame('Updated Zone', $zone->name);
        $this->assertSame('Updated Woreda', $woreda->name);

        $this->assertDatabaseHas('organizations', [
            'external_id' => '1000932',
            'name' => 'Updated Facility',
            'category' => 'Government/Public',
            'type' => 'Hospital',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'zone' => $zone->name,
            'woreda_id' => $woreda->id,
            'city_town' => 'New City',
            'phone' => '0333333333',
            'fax' => '0444444444',
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
