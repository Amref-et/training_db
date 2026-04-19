<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('participants') && ! Schema::hasColumn('participants', 'home_phone')) {
            Schema::table('participants', function (Blueprint $table) {
                $table->string('home_phone')->nullable()->after('gender');
            });
        }

        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                if (! Schema::hasColumn('organizations', 'region_id')) {
                    $table->foreignId('region_id')->nullable()->after('type')->constrained('regions')->nullOnDelete();
                }

                if (! Schema::hasColumn('organizations', 'zone')) {
                    $table->string('zone')->nullable()->after('region_id');
                }

                if (! Schema::hasColumn('organizations', 'woreda_id')) {
                    $table->foreignId('woreda_id')->nullable()->after('zone')->constrained('woredas')->nullOnDelete();
                }

                if (! Schema::hasColumn('organizations', 'city_town')) {
                    $table->string('city_town')->nullable()->after('woreda_id');
                }

                if (! Schema::hasColumn('organizations', 'phone')) {
                    $table->string('phone')->nullable()->after('city_town');
                }

                if (! Schema::hasColumn('organizations', 'fax')) {
                    $table->string('fax')->nullable()->after('phone');
                }
            });
        }

        if (Schema::hasTable('training_events')) {
            Schema::table('training_events', function (Blueprint $table) {
                if (! Schema::hasColumn('training_events', 'training_region_id')) {
                    $table->foreignId('training_region_id')->nullable()->after('training_organizer_id')->constrained('regions')->nullOnDelete();
                }

                if (! Schema::hasColumn('training_events', 'training_city')) {
                    $table->string('training_city')->nullable()->after('training_region_id');
                }

                if (! Schema::hasColumn('training_events', 'course_venue')) {
                    $table->string('course_venue')->nullable()->after('training_city');
                }
            });
        }

        if (Schema::hasTable('training_event_participants')) {
            Schema::table('training_event_participants', function (Blueprint $table) {
                if (! Schema::hasColumn('training_event_participants', 'mid_test_score')) {
                    $table->decimal('mid_test_score', 5, 2)->nullable()->after('final_score');
                }

                if (! Schema::hasColumn('training_event_participants', 'activity_completion_status')) {
                    $table->string('activity_completion_status')->nullable()->after('mid_test_score');
                }

                if (! Schema::hasColumn('training_event_participants', 'is_trainer')) {
                    $table->boolean('is_trainer')->default(false)->after('activity_completion_status');
                }

                if (! Schema::hasColumn('training_event_participants', 'trainer_comments')) {
                    $table->longText('trainer_comments')->nullable()->after('is_trainer');
                }

                if (! Schema::hasColumn('training_event_participants', 'trainer_name')) {
                    $table->string('trainer_name')->nullable()->after('trainer_comments');
                }

                if (! Schema::hasColumn('training_event_participants', 'trainer_signature')) {
                    $table->string('trainer_signature')->nullable()->after('trainer_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_event_participants')) {
            Schema::table('training_event_participants', function (Blueprint $table) {
                foreach ([
                    'mid_test_score',
                    'activity_completion_status',
                    'is_trainer',
                    'trainer_comments',
                    'trainer_name',
                    'trainer_signature',
                ] as $column) {
                    if (Schema::hasColumn('training_event_participants', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('training_events')) {
            Schema::table('training_events', function (Blueprint $table) {
                if (Schema::hasColumn('training_events', 'training_region_id')) {
                    $table->dropConstrainedForeignId('training_region_id');
                }

                foreach (['training_city', 'course_venue'] as $column) {
                    if (Schema::hasColumn('training_events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                if (Schema::hasColumn('organizations', 'region_id')) {
                    $table->dropConstrainedForeignId('region_id');
                }

                if (Schema::hasColumn('organizations', 'woreda_id')) {
                    $table->dropConstrainedForeignId('woreda_id');
                }

                foreach (['zone', 'city_town', 'phone', 'fax'] as $column) {
                    if (Schema::hasColumn('organizations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('participants') && Schema::hasColumn('participants', 'home_phone')) {
            Schema::table('participants', function (Blueprint $table) {
                $table->dropColumn('home_phone');
            });
        }
    }
};

