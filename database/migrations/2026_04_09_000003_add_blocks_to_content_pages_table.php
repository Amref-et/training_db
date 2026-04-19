<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_pages') || Schema::hasColumn('content_pages', 'blocks')) {
            return;
        }

        Schema::table('content_pages', function (Blueprint $table) {
            $table->longText('blocks')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_pages') || ! Schema::hasColumn('content_pages', 'blocks')) {
            return;
        }

        Schema::table('content_pages', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }
};