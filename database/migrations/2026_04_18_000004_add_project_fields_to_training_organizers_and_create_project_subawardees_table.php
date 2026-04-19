<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_organizers')) {
            Schema::table('training_organizers', function (Blueprint $table) {
                if (! Schema::hasColumn('training_organizers', 'project_code')) {
                    $table->string('project_code')->nullable()->unique()->after('title');
                }

                if (! Schema::hasColumn('training_organizers', 'project_name')) {
                    $table->string('project_name')->nullable()->after('project_code');
                }

                if (! Schema::hasColumn('training_organizers', 'organizer_type')) {
                    $table->string('organizer_type')->nullable()->after('project_name');
                }
            });

            DB::table('training_organizers')
                ->select(['id', 'title', 'project_code', 'project_name', 'organizer_type'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('training_organizers')
                            ->where('id', $row->id)
                            ->update([
                                'project_code' => $row->project_code ?: 'PROJ-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT),
                                'project_name' => $row->project_name ?: ($row->title ?: 'Project '.$row->id),
                                'organizer_type' => $row->organizer_type ?: 'The project',
                            ]);
                    }
                });
        }

        if (! Schema::hasTable('project_subawardees')) {
            Schema::create('project_subawardees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('training_organizers')->cascadeOnDelete();
                $table->string('subawardee_name');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_subawardees');

        if (Schema::hasTable('training_organizers')) {
            Schema::table('training_organizers', function (Blueprint $table) {
                if (Schema::hasColumn('training_organizers', 'project_code')) {
                    $table->dropUnique('training_organizers_project_code_unique');
                    $table->dropColumn('project_code');
                }

                if (Schema::hasColumn('training_organizers', 'project_name')) {
                    $table->dropColumn('project_name');
                }

                if (Schema::hasColumn('training_organizers', 'organizer_type')) {
                    $table->dropColumn('organizer_type');
                }
            });
        }
    }
};
