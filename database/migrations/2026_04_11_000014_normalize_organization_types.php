<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('organizations', 'type')) {
            return;
        }

        $mappings = [
            'Education' => 'School/University',
            'Health' => 'Health Center/Clinic/Division',
            'Agriculture' => 'Other Government org.',
            'Other' => 'Other (specify)',
        ];

        foreach ($mappings as $from => $to) {
            DB::table('organizations')->where('type', $from)->update(['type' => $to]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('organizations', 'type')) {
            return;
        }

        $mappings = [
            'School/University' => 'Education',
            'Health Center/Clinic/Division' => 'Health',
            'Other Government org.' => 'Agriculture',
            'Other (specify)' => 'Other',
        ];

        foreach ($mappings as $from => $to) {
            DB::table('organizations')->where('type', $from)->update(['type' => $to]);
        }
    }
};

