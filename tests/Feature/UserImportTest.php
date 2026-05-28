<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_import_users_and_download_generated_temporary_passwords(): void
    {
        Storage::fake('local');

        $admin = $this->adminUser();
        $csv = implode("\n", [
            'name,email,role',
            'Imported User,imported.user@example.com,Editor',
            'Duplicate User,'.$admin->email.',Viewer',
            'Missing Role,missing-role@example.com,Not A Role',
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.import'), [
                'import_file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
            ]);

        $response
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'User import completed: 1 created, 2 skipped.')
            ->assertSessionHas('user_import_report');

        $imported = User::query()
            ->where('email', 'imported.user@example.com')
            ->firstOrFail();

        $this->assertSame('Imported User', $imported->name);
        $this->assertTrue($imported->hasRole('Editor'));

        $report = $response->baseResponse->getSession()->get('user_import_report');
        Storage::disk('local')->assertExists('user-import-results/'.$report['file_name']);

        $this
            ->actingAs($admin)
            ->get($report['url'])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = collect(preg_split('/\r\n|\r|\n/', trim(Storage::disk('local')->get('user-import-results/'.$report['file_name']))))
            ->filter()
            ->map(fn (string $line): array => str_getcsv($line))
            ->values();

        $this->assertSame([
            'line_number',
            'name',
            'email',
            'role',
            'status',
            'temporary_password',
            'message',
        ], $rows[0]);

        $this->assertSame('created', $rows[1][4]);
        $this->assertNotSame('', $rows[1][5]);
        $this->assertTrue(Hash::check($rows[1][5], $imported->password));
        $this->assertSame('skipped', $rows[2][4]);
        $this->assertSame('', $rows[2][5]);
        $this->assertSame('skipped', $rows[3][4]);
        $this->assertSame('', $rows[3][5]);
    }

    public function test_user_import_template_has_required_headers(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->get(route('admin.users.import-template'));

        $response->assertOk();

        $rows = collect(preg_split('/\r\n|\r|\n/', trim($response->streamedContent())))
            ->filter()
            ->map(fn (string $line): array => str_getcsv($line))
            ->values();

        $this->assertSame(['name', 'email', 'role'], $rows[0]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
