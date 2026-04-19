<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zones')) {
            Schema::create('zones', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('organizations', 'zone')) {
            return;
        }

        $zoneNames = DB::table('organizations')
            ->select('zone')
            ->whereNotNull('zone')
            ->where('zone', '!=', '')
            ->distinct()
            ->pluck('zone');

        foreach ($zoneNames as $zoneName) {
            $trimmed = trim((string) $zoneName);
            if ($trimmed === '') {
                continue;
            }

            DB::table('zones')->updateOrInsert(
                ['name' => $trimmed],
                ['description' => null, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
