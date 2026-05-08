<?php

namespace Tests\Feature;

use App\Models\TrainingOrganizer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TrainingOrganizerAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_project_form_includes_metadata_fields_and_creatable_program_dropdown(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-organizers.create'));

        $response
            ->assertOk()
            ->assertSee('Project Long Name')
            ->assertSee('Donor')
            ->assertSee('Program')
            ->assertSee('DPC')
            ->assertSee('RMNCATH-N')
            ->assertSee('js-creatable-select', false);
    }

    public function test_admin_can_save_project_metadata_with_custom_program(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->from(route('admin.training-organizers.create'))
            ->post(route('admin.training-organizers.store'), [
                'project_code' => 'PROJ-META',
                'project_name' => 'Short Project Name',
                'project_long_name' => 'Complete Project Long Name',
                'donor' => 'USAID',
                'program' => 'Custom Program Area',
                'subawardees' => ['Subawardee One'],
            ]);

        $response
            ->assertRedirect(route('admin.training-organizers.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Project created successfully.');

        $this->assertDatabaseHas('training_organizers', [
            'project_code' => 'PROJ-META',
            'project_name' => 'Short Project Name',
            'project_long_name' => 'Complete Project Long Name',
            'donor' => 'USAID',
            'program' => 'Custom Program Area',
        ]);

        $organizer = TrainingOrganizer::query()
            ->where('project_code', 'PROJ-META')
            ->firstOrFail();

        $this->assertSame('Short Project Name', $organizer->title);
        $this->assertDatabaseHas('project_subawardees', [
            'project_id' => $organizer->id,
            'subawardee_name' => 'Subawardee One',
        ]);
    }

    public function test_project_index_includes_import_and_export_controls(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-organizers.index'));

        $response
            ->assertOk()
            ->assertSee(route('admin.training-organizers.export'), false)
            ->assertSee(route('admin.training-organizers.import'), false)
            ->assertSee('Import CSV');
    }

    public function test_project_export_includes_metadata_and_subawardees(): void
    {
        $organizer = TrainingOrganizer::query()->create([
            'project_code' => 'PROJ-EXPORT',
            'project_name' => 'Export Project',
            'project_long_name' => 'Export Project Long Name',
            'donor' => 'CDC',
            'program' => 'DPC',
        ]);
        $organizer->subawardees()->createMany([
            ['subawardee_name' => 'Subawardee A'],
            ['subawardee_name' => 'Subawardee B'],
        ]);

        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.training-organizers.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = collect(preg_split('/\r\n|\r|\n/', trim($response->streamedContent())))
            ->filter()
            ->map(fn (string $line): array => str_getcsv($line))
            ->values();

        $this->assertSame([
            'project_code',
            'project_name',
            'project_long_name',
            'donor',
            'program',
            'subawardees',
        ], $rows[0]);

        $this->assertSame([
            'PROJ-EXPORT',
            'Export Project',
            'Export Project Long Name',
            'CDC',
            'DPC',
            'Subawardee A; Subawardee B',
        ], $rows[1]);
    }

    public function test_project_import_creates_and_updates_project_metadata(): void
    {
        $existing = TrainingOrganizer::query()->create([
            'project_code' => 'PROJ-EXISTING',
            'project_name' => 'Existing Project',
            'project_long_name' => 'Existing Long Name',
            'donor' => 'Old Donor',
            'program' => 'HSS',
        ]);
        $existing->subawardees()->create([
            'subawardee_name' => 'Old Subawardee',
        ]);

        $csv = implode("\n", [
            'project_code,project_name,project_long_name,donor,program,subawardees',
            'PROJ-NEW,New Project,New Project Long Name,USAID,Custom Program,"Sub A; Sub B"',
            'PROJ-EXISTING,Updated Project,Updated Project Long Name,CDC,WASH,Updated Subawardee',
        ]);

        $file = UploadedFile::fake()->createWithContent('training-organizers.csv', $csv);

        $response = $this
            ->actingAs($this->adminUser())
            ->from(route('admin.training-organizers.index'))
            ->post(route('admin.training-organizers.import'), [
                'import_file' => $file,
            ]);

        $response
            ->assertRedirect(route('admin.training-organizers.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Project import completed: 1 created, 1 updated.');

        $this->assertDatabaseHas('training_organizers', [
            'project_code' => 'PROJ-NEW',
            'project_name' => 'New Project',
            'project_long_name' => 'New Project Long Name',
            'donor' => 'USAID',
            'program' => 'Custom Program',
        ]);

        $this->assertDatabaseHas('training_organizers', [
            'project_code' => 'PROJ-EXISTING',
            'project_name' => 'Updated Project',
            'project_long_name' => 'Updated Project Long Name',
            'donor' => 'CDC',
            'program' => 'WASH',
        ]);

        $newOrganizer = TrainingOrganizer::query()
            ->where('project_code', 'PROJ-NEW')
            ->firstOrFail();

        $this->assertDatabaseHas('project_subawardees', [
            'project_id' => $newOrganizer->id,
            'subawardee_name' => 'Sub A',
        ]);
        $this->assertDatabaseHas('project_subawardees', [
            'project_id' => $newOrganizer->id,
            'subawardee_name' => 'Sub B',
        ]);

        $this->assertDatabaseMissing('project_subawardees', [
            'project_id' => $existing->id,
            'subawardee_name' => 'Old Subawardee',
        ]);
        $this->assertDatabaseHas('project_subawardees', [
            'project_id' => $existing->id,
            'subawardee_name' => 'Updated Subawardee',
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
