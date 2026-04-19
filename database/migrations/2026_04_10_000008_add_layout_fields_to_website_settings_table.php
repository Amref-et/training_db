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
            if (! Schema::hasColumn('website_settings', 'header_background_color')) {
                $table->string('header_background_color', 7)->default('#ffffff')->after('header_cta_url');
            }

            if (! Schema::hasColumn('website_settings', 'header_text_color')) {
                $table->string('header_text_color', 7)->default('#0f172a')->after('header_background_color');
            }

            if (! Schema::hasColumn('website_settings', 'header_link_color')) {
                $table->string('header_link_color', 7)->default('#334155')->after('header_text_color');
            }

            if (! Schema::hasColumn('website_settings', 'body_background_color')) {
                $table->string('body_background_color', 7)->default('#f8fafc')->after('header_link_color');
            }

            if (! Schema::hasColumn('website_settings', 'body_text_color')) {
                $table->string('body_text_color', 7)->default('#0f172a')->after('body_background_color');
            }

            if (! Schema::hasColumn('website_settings', 'body_panel_color')) {
                $table->string('body_panel_color', 7)->default('#ffffff')->after('body_text_color');
            }

            if (! Schema::hasColumn('website_settings', 'body_accent_color')) {
                $table->string('body_accent_color', 7)->default('#0f766e')->after('body_panel_color');
            }

            if (! Schema::hasColumn('website_settings', 'footer_logo_url')) {
                $table->string('footer_logo_url')->nullable()->after('footer_title');
            }

            if (! Schema::hasColumn('website_settings', 'footer_background_color')) {
                $table->string('footer_background_color', 7)->default('#0f172a')->after('footer_note');
            }

            if (! Schema::hasColumn('website_settings', 'footer_text_color')) {
                $table->string('footer_text_color', 7)->default('#e2e8f0')->after('footer_background_color');
            }

            if (! Schema::hasColumn('website_settings', 'footer_link_color')) {
                $table->string('footer_link_color', 7)->default('#cbd5e1')->after('footer_text_color');
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
                'header_background_color',
                'header_text_color',
                'header_link_color',
                'body_background_color',
                'body_text_color',
                'body_panel_color',
                'body_accent_color',
                'footer_logo_url',
                'footer_background_color',
                'footer_text_color',
                'footer_link_color',
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
