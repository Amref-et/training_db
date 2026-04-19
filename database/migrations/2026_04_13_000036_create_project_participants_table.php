<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_participants')) {
            Schema::create('project_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'participant_id'], 'project_participants_project_participant_unique');
            });
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'participant_id')) {
            DB::table('projects')
                ->select(['id', 'participant_id', 'created_at', 'updated_at'])
                ->whereNotNull('participant_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    $payload = collect($rows)->map(function ($row): array {
                        return [
                            'project_id' => $row->id,
                            'participant_id' => $row->participant_id,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ];
                    })->all();

                    if (! empty($payload)) {
                        DB::table('project_participants')->upsert(
                            $payload,
                            ['project_id', 'participant_id'],
                            ['updated_at']
                        );
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_participants');
    }
};

