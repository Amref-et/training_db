<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('organizations', 'category')) {
            return;
        }

        $mappings = [
            'Government' => 'Government/Public',
            'NGO' => 'NGO/CSO',
            'International' => 'UN Agency',
        ];

        foreach ($mappings as $from => $to) {
            DB::table('organizations')->where('category', $from)->update(['category' => $to]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('organizations', 'category')) {
            return;
        }

        $mappings = [
            'Government/Public' => 'Government',
            'NGO/CSO' => 'NGO',
            'UN Agency' => 'International',
        ];

        foreach ($mappings as $from => $to) {
            DB::table('organizations')->where('category', $from)->update(['category' => $to]);
        }
    }
};

