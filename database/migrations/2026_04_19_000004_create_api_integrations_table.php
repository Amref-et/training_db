<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_integrations')) {
            return;
        }

        Schema::create('api_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('provider', 60);
            $table->string('base_url')->nullable();
            $table->string('api_version', 20)->nullable();
            $table->string('auth_type', 30)->default('basic');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('bearer_token')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('token_url')->nullable();
            $table->string('event_endpoint')->nullable();
            $table->string('program_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->json('mappings')->nullable();
            $table->timestamps();
        });

        DB::table('api_integrations')->insert([
            'code' => 'dhis2',
            'name' => 'DHIS2',
            'provider' => 'dhis2',
            'api_version' => '40',
            'auth_type' => 'basic',
            'event_endpoint' => '/api/events',
            'is_active' => false,
            'settings' => json_encode([
                'default_org_unit' => null,
                'org_unit_strategy' => 'default',
                'org_unit_map' => [],
                'default_headers' => [],
            ], JSON_UNESCAPED_SLASHES),
            'mappings' => json_encode([
                'event_name' => null,
                'training_title' => null,
                'project_name' => null,
                'organized_by' => null,
                'participant_count' => null,
                'avg_final_score' => null,
                'status' => null,
                'venue' => null,
                'city' => null,
                'workshop_count' => null,
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integrations');
    }
};
