<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_rounds')) {
            return;
        }

        Schema::create('training_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_event_id')->constrained('training_events')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('workshop_title')->nullable();
            $table->date('round_start_date')->nullable();
            $table->date('round_end_date')->nullable();
            $table->decimal('pre_training_score', 5, 2)->nullable();
            $table->decimal('post_training_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['training_event_id', 'round_number'], 'training_rounds_event_round_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_rounds');
    }
};

