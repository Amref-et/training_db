<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_event_join_requests')) {
            return;
        }

        Schema::create('training_event_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_event_id')->constrained('training_events')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('requested_message')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('training_event_participants')->nullOnDelete();
            $table->timestamps();

            $table->unique(['training_event_id', 'participant_id'], 'training_event_join_requests_event_participant_unique');
            $table->index(['training_event_id', 'status'], 'training_event_join_requests_event_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_event_join_requests');
    }
};
