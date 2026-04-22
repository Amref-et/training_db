<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_settings') || Schema::hasColumn('website_settings', 'public_home_dashboard_tab_id')) {
            return;
        }

        Schema::table('website_settings', function (Blueprint $table) {
            $table->foreignId('public_home_dashboard_tab_id')
                ->nullable()
                ->after('show_login_link')
                ->constrained('dashboard_tabs')
                ->nullOnDelete();
        });

        $defaultTabId = DB::table('dashboard_tabs')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        if ($defaultTabId) {
            DB::table('website_settings')->update([
                'public_home_dashboard_tab_id' => $defaultTabId,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_settings') || ! Schema::hasColumn('website_settings', 'public_home_dashboard_tab_id')) {
            return;
        }

        Schema::table('website_settings', function (Blueprint $table) {
            $table->dropForeign(['public_home_dashboard_tab_id']);
            $table->dropColumn('public_home_dashboard_tab_id');
        });
    }
};
