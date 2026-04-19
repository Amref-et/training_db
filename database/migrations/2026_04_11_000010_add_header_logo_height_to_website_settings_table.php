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
            if (! Schema::hasColumn('website_settings', 'header_logo_height')) {
                $table->unsignedSmallInteger('header_logo_height')->default(56)->after('header_logo_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_settings')) {
            return;
        }

        Schema::table('website_settings', function (Blueprint $table) {
            if (Schema::hasColumn('website_settings', 'header_logo_height')) {
                $table->dropColumn('header_logo_height');
            }
        });
    }
};
