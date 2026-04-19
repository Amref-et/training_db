<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zones') && Schema::hasTable('regions') && ! Schema::hasColumn('zones', 'region_id')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->foreignId('region_id')->nullable()->after('name')->constrained('regions')->nullOnDelete();
            });
        }

        if (Schema::hasTable('woredas') && Schema::hasTable('zones') && ! Schema::hasColumn('woredas', 'zone_id')) {
            Schema::table('woredas', function (Blueprint $table) {
                $table->foreignId('zone_id')->nullable()->after('region_id')->constrained('zones')->nullOnDelete();
            });
        }

        if (Schema::hasTable('organizations') && Schema::hasTable('zones') && ! Schema::hasColumn('organizations', 'zone_id')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->foreignId('zone_id')->nullable()->after('region_id')->constrained('zones')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('regions') || ! Schema::hasTable('zones')) {
            return;
        }

        $regions = DB::table('regions')->orderBy('id')->get(['id', 'name']);
        if ($regions->isEmpty()) {
            return;
        }

        $defaultZoneByRegion = [];
        foreach ($regions as $region) {
            $defaultZoneName = 'Default Zone - '.trim((string) $region->name);

            $zoneId = DB::table('zones')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($defaultZoneName)])
                ->value('id');

            if (! $zoneId) {
                $zoneId = DB::table('zones')->insertGetId([
                    'name' => $defaultZoneName,
                    'description' => 'Auto-created for hierarchy backfill',
                    'region_id' => $region->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('zones')->where('id', $zoneId)->update([
                    'region_id' => DB::raw('COALESCE(region_id, '.(int) $region->id.')'),
                    'updated_at' => now(),
                ]);
            }

            $defaultZoneByRegion[(int) $region->id] = (int) $zoneId;
        }

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'zone') && Schema::hasColumn('organizations', 'region_id')) {
            DB::statement("
                UPDATE zones z
                INNER JOIN (
                    SELECT zone AS zone_name, MAX(region_id) AS mapped_region_id
                    FROM organizations
                    WHERE zone IS NOT NULL AND zone <> '' AND region_id IS NOT NULL
                    GROUP BY zone
                ) map ON LOWER(map.zone_name) = LOWER(z.name)
                SET z.region_id = COALESCE(z.region_id, map.mapped_region_id)
            ");
        }

        if (Schema::hasTable('woredas') && Schema::hasColumn('woredas', 'zone_id') && Schema::hasColumn('woredas', 'region_id')) {
            foreach ($defaultZoneByRegion as $regionId => $defaultZoneId) {
                DB::table('woredas')
                    ->where('region_id', $regionId)
                    ->whereNull('zone_id')
                    ->update([
                        'zone_id' => $defaultZoneId,
                        'updated_at' => now(),
                    ]);
            }

            DB::statement("
                UPDATE woredas w
                INNER JOIN zones z ON z.id = w.zone_id
                SET w.region_id = z.region_id
                WHERE z.region_id IS NOT NULL
                  AND (w.region_id IS NULL OR w.region_id <> z.region_id)
            ");
        }

        if (Schema::hasColumn('zones', 'region_id')) {
            DB::statement("
                UPDATE zones z
                INNER JOIN (
                    SELECT zone_id, MAX(region_id) AS region_id
                    FROM woredas
                    WHERE zone_id IS NOT NULL AND region_id IS NOT NULL
                    GROUP BY zone_id
                ) map ON map.zone_id = z.id
                SET z.region_id = COALESCE(z.region_id, map.region_id)
            ");

            $firstRegionId = (int) $regions->first()->id;
            DB::table('zones')
                ->whereNull('region_id')
                ->update([
                    'region_id' => $firstRegionId,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'zone_id') && Schema::hasColumn('organizations', 'woreda_id')) {
            DB::statement("
                UPDATE organizations o
                INNER JOIN woredas w ON w.id = o.woreda_id
                SET o.zone_id = COALESCE(w.zone_id, o.zone_id),
                    o.region_id = COALESCE(w.region_id, o.region_id)
                WHERE o.woreda_id IS NOT NULL
            ");
        }

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'zone') && Schema::hasColumn('organizations', 'zone_id')) {
            DB::statement("
                UPDATE organizations o
                INNER JOIN zones z ON LOWER(z.name) = LOWER(o.zone)
                SET o.zone_id = COALESCE(o.zone_id, z.id),
                    o.region_id = COALESCE(o.region_id, z.region_id)
                WHERE o.zone IS NOT NULL AND o.zone <> ''
            ");
        }

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'region_id') && Schema::hasColumn('organizations', 'zone_id')) {
            foreach ($defaultZoneByRegion as $regionId => $defaultZoneId) {
                DB::table('organizations')
                    ->where('region_id', $regionId)
                    ->whereNull('zone_id')
                    ->whereNull('woreda_id')
                    ->update([
                        'zone_id' => $defaultZoneId,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'zone_id') && Schema::hasColumn('organizations', 'zone')) {
            DB::statement("
                UPDATE organizations o
                INNER JOIN zones z ON z.id = o.zone_id
                SET o.zone = z.name,
                    o.region_id = COALESCE(z.region_id, o.region_id)
                WHERE o.zone_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'zone_id')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('zone_id');
            });
        }

        if (Schema::hasTable('woredas') && Schema::hasColumn('woredas', 'zone_id')) {
            Schema::table('woredas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('zone_id');
            });
        }

        if (Schema::hasTable('zones') && Schema::hasColumn('zones', 'region_id')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->dropConstrainedForeignId('region_id');
            });
        }
    }
};
