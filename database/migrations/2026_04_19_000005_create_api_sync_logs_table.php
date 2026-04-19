<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_sync_logs')) {
            return;
        }

        Schema::create('api_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_integration_id')->nullable()->constrained('api_integrations')->nullOnDelete();
            $table->string('direction', 20)->default('outbound');
            $table->string('entity_type', 120)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('endpoint')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['api_integration_id', 'synced_at'], 'api_sync_logs_integration_synced_idx');
            $table->index(['entity_type', 'entity_id'], 'api_sync_logs_entity_idx');
            $table->index(['status', 'synced_at'], 'api_sync_logs_status_synced_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
    }
};
