<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_rounds')) {
            return;
        }

        Schema::table('training_rounds', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['pre_training_score', 'post_training_score'] as $column) {
                if (Schema::hasColumn('training_rounds', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_rounds')) {
            return;
        }

        Schema::table('training_rounds', function (Blueprint $table) {
            if (! Schema::hasColumn('training_rounds', 'pre_training_score')) {
                $table->decimal('pre_training_score', 5, 2)->nullable()->after('round_end_date');
            }

            if (! Schema::hasColumn('training_rounds', 'post_training_score')) {
                $table->decimal('post_training_score', 5, 2)->nullable()->after('pre_training_score');
            }
        });
    }
};

