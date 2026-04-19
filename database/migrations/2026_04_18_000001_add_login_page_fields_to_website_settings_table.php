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
            if (! Schema::hasColumn('website_settings', 'login_eyebrow')) {
                $table->string('login_eyebrow')->nullable()->after('show_login_link');
            }

            if (! Schema::hasColumn('website_settings', 'login_title')) {
                $table->string('login_title')->nullable()->after('login_eyebrow');
            }

            if (! Schema::hasColumn('website_settings', 'login_subtitle')) {
                $table->string('login_subtitle')->nullable()->after('login_title');
            }

            if (! Schema::hasColumn('website_settings', 'login_background_start_color')) {
                $table->string('login_background_start_color', 7)->default('#082f49')->after('login_subtitle');
            }

            if (! Schema::hasColumn('website_settings', 'login_background_end_color')) {
                $table->string('login_background_end_color', 7)->default('#0f766e')->after('login_background_start_color');
            }

            if (! Schema::hasColumn('website_settings', 'login_background_accent_color')) {
                $table->string('login_background_accent_color', 7)->default('#d97706')->after('login_background_end_color');
            }

            if (! Schema::hasColumn('website_settings', 'login_card_background_color')) {
                $table->string('login_card_background_color', 7)->default('#ffffff')->after('login_background_accent_color');
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

            foreach ([
                'login_eyebrow',
                'login_title',
                'login_subtitle',
                'login_background_start_color',
                'login_background_end_color',
                'login_background_accent_color',
                'login_card_background_color',
            ] as $column) {
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
