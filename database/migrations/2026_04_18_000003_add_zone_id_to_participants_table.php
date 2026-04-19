<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participants') || ! Schema::hasTable('zones')) {
            return;
        }

        if (! Schema::hasColumn('participants', 'zone_id')) {
            Schema::table('participants', function (Blueprint $table) {
                $table->foreignId('zone_id')->nullable()->after('region_id')->constrained('zones')->nullOnDelete();
            });
        }

        DB::table('participants')
            ->select(['id', 'woreda_id', 'organization_id'])
            ->whereNull('zone_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $woredaIds = collect($rows)->pluck('woreda_id')->filter()->unique()->values();
                $organizationIds = collect($rows)->pluck('organization_id')->filter()->unique()->values();

                $zonesByWoreda = $woredaIds->isEmpty()
                    ? collect()
                    : DB::table('woredas')
                        ->whereIn('id', $woredaIds)
                        ->pluck('zone_id', 'id');

                $zonesByOrganization = $organizationIds->isEmpty()
                    ? collect()
                    : DB::table('organizations')
                        ->whereIn('id', $organizationIds)
                        ->pluck('zone_id', 'id');

                foreach ($rows as $row) {
                    $zoneId = $zonesByOrganization[$row->organization_id] ?? $zonesByWoreda[$row->woreda_id] ?? null;

                    if ($zoneId !== null) {
                        DB::table('participants')
                            ->where('id', $row->id)
                            ->update(['zone_id' => $zoneId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('participants') && Schema::hasColumn('participants', 'zone_id')) {
            Schema::table('participants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('zone_id');
            });
        }
    }
};
