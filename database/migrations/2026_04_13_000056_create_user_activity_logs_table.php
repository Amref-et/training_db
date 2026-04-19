<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_activity_logs')) {
            return;
        }

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 255)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path', 2048)->nullable();
            $table->string('route_name', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at'], 'user_activity_logs_user_occurred_idx');
            $table->index(['route_name', 'occurred_at'], 'user_activity_logs_route_occurred_idx');
            $table->index(['method', 'occurred_at'], 'user_activity_logs_method_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
