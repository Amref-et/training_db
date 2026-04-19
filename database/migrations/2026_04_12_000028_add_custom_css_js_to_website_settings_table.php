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
            if (! Schema::hasColumn('website_settings', 'custom_css')) {
                $table->longText('custom_css')->nullable()->after('show_login_link');
            }

            if (! Schema::hasColumn('website_settings', 'custom_js')) {
                $table->longText('custom_js')->nullable()->after('custom_css');
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

            if (Schema::hasColumn('website_settings', 'custom_js')) {
                $columns[] = 'custom_js';
            }

            if (Schema::hasColumn('website_settings', 'custom_css')) {
                $columns[] = 'custom_css';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

