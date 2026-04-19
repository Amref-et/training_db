<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Region;
use App\Models\User;
use App\Models\Woreda;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ParticipantImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-17 09:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_participant_export_includes_csv_header_and_record_values(): void
    {
        $user = $this->adminUser();
        [$region, $zone, $woreda, $organization, $profession] = $this->participantDependencies();

        $participant = Participant::query()->create([
            'first_name' => 'Alice',
            'father_name' => 'Bekele',
            'grandfather_name' => 'Chala',
            'date_of_birth' => '2000-06-15',
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'woreda_id' => $woreda->id,
            'organization_id' => $organization->id,
            'gender' => 'female',
            'home_phone' => '0111002000',
            'mobile_phone' => '0911223344',
            'email' => 'alice@example.com',
            'profession' => $profession->name,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.participants.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            'participant_code,first_name,father_name,grandfather_name,date_of_birth,age,gender,home_phone,mobile_phone,email,profession,region_id,region_name,zone_id,zone_name,woreda_id,woreda_name,organization_id,organization_name',
            $csv
        );
        $this->assertStringContainsString($participant->participant_code.',Alice,Bekele,Chala,2000-06-15,26,female,0111002000,0911223344,alice@example.com,'.$profession->name, $csv);
        $this->assertStringContainsString(','.$region->id.','.$region->name.','.$zone->id.','.$zone->name.','.$woreda->id.','.$woreda->name.','.$organization->id.','.$organization->name, $csv);
    }

    public function test_participant_import_preserves_model_generated_code_and_birth_age_logic(): void
    {
        $user = $this->adminUser();
        [$region, $zone, $woreda, $organization, $profession] = $this->participantDependencies();

        $existing = Participant::query()->create([
            'first_name' => 'Bravo',
            'father_name' => 'Desta',
            'grandfather_name' => 'Eshetu',
            'age' => 20,
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'woreda_id' => $woreda->id,
            'organization_id' => $organization->id,
            'gender' => 'male',
            'home_phone' => null,
            'mobile_phone' => '0900001111',
            'email' => 'existing@example.com',
            'profession' => $profession->name,
        ]);

        $csv = implode("\n", [
            'participant_code,first_name,father_name,grandfather_name,date_of_birth,age,gender,home_phone,mobile_phone,email,profession,region_id,region_name,zone_id,zone_name,woreda_id,woreda_name,organization_id,organization_name',
            ',New,Import,Person,,30,female,0111111111,0987654321,new-person@example.com,'.$profession->name.','.$region->id.','.$region->name.','.$zone->id.','.$zone->name.','.$woreda->id.','.$woreda->name.','.$organization->id.','.$organization->name,
            $existing->participant_code.',Bravo,Desta,Eshetu,2001-08-10,999,male,0222222222,0900001111,existing@example.com,'.$profession->name.','.$region->id.','.$region->name.','.$zone->id.','.$zone->name.','.$woreda->id.','.$woreda->name.','.$organization->id.','.$organization->name,
        ]);

        $file = UploadedFile::fake()->createWithContent('participants.csv', $csv);

        $response = $this
            ->actingAs($user)
            ->from(route('admin.participants.index'))
            ->post(route('admin.participants.import'), [
                'import_file' => $file,
            ]);

        $response->assertRedirect(route('admin.participants.index'));
        $response->assertSessionHas('success', 'Participant import completed: 1 created, 1 updated.');

        $created = Participant::query()->where('email', 'new-person@example.com')->firstOrFail();
        $updated = $existing->fresh();

        $this->assertSame('1996-07-01', $created->date_of_birth?->toDateString());
        $this->assertSame(30, $created->age);
        $this->assertSame('NIP1996074321', $created->participant_code);
        $this->assertSame($zone->id, $created->zone_id);

        $this->assertSame($existing->participant_code, $updated->participant_code);
        $this->assertSame('2001-08-10', $updated->date_of_birth?->toDateString());
        $this->assertSame(24, $updated->age);
        $this->assertSame('0222222222', $updated->home_phone);
        $this->assertSame($zone->id, $updated->zone_id);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
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
