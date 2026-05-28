<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fab_faq_items')) {
            return;
        }

        Schema::table('fab_faq_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fab_faq_items', 'visibility')) {
                $table->string('visibility', 20)->default('both')->after('type');
            }

            if (! Schema::hasColumn('fab_faq_items', 'link_label')) {
                $table->string('link_label')->nullable()->after('answer');
            }

            if (! Schema::hasColumn('fab_faq_items', 'link_url')) {
                $table->string('link_url')->nullable()->after('link_label');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fab_faq_items')) {
            return;
        }

        Schema::table('fab_faq_items', function (Blueprint $table) {
            $columns = [];

            foreach (['link_url', 'link_label', 'visibility'] as $column) {
                if (Schema::hasColumn('fab_faq_items', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
