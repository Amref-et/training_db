<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_settings')) {
            return;
        }

        Schema::table('website_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('website_settings', 'radius_sm')) {
                $table->unsignedSmallInteger('radius_sm')->default(10)->after('footer_link_color');
            }

            if (! Schema::hasColumn('website_settings', 'radius_md')) {
                $table->unsignedSmallInteger('radius_md')->default(14)->after('radius_sm');
            }

            if (! Schema::hasColumn('website_settings', 'radius_lg')) {
                $table->unsignedSmallInteger('radius_lg')->default(18)->after('radius_md');
            }

            if (! Schema::hasColumn('website_settings', 'radius_xl')) {
                $table->unsignedSmallInteger('radius_xl')->default(24)->after('radius_lg');
            }

            if (! Schema::hasColumn('website_settings', 'radius_pill')) {
                $table->unsignedSmallInteger('radius_pill')->default(999)->after('radius_xl');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_settings')) {
            return;
        }

        Schema::table('website_settings', function (Blueprint $table) {
            $columns = [];

            foreach (['radius_sm', 'radius_md', 'radius_lg', 'radius_xl', 'radius_pill'] as $column) {
                if (Schema::hasColumn('website_settings', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
