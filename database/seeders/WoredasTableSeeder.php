<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Woreda;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class WoredasTableSeeder extends Seeder
{
    public function run(): void
    {
        $woredasByZone = [
            'Addis Ababa Zone' => ['Bole', 'Yeka', 'Kolfe Keranio'],
            'Afar Zone 1' => ['Dubti', 'Asayita', 'Afambo'],
            'Bahir Dar Zone' => ['Bahir Dar Zuria', 'Mecha', 'Yilmana Densa'],
            'Assosa Zone' => ['Assosa', 'Bambasi', 'Sherkole'],
            'Hossana Zone' => ['Hossana', 'Soro', 'Lemo'],
            'Dire Dawa Zone' => ['Dire Dawa Urban', 'Legehida', 'Shinile Fringe'],
            'Gambella Zone' => ['Gambella Zuria', 'Abobo', 'Itang'],
            'Harar Zone' => ['Harar Urban', 'Erer', 'Sofi'],
            'Bale Zone' => ['Goba', 'Robe', 'Sinana'],
            'Hawassa Zone' => ['Hawassa Zuria', 'Aleta Wondo', 'Shebedino'],
            'Jigjiga Zone' => ['Jigjiga', 'Babile', 'Gursum'],
            'Arba Minch Zone' => ['Arba Minch Zuria', 'Chencha', 'Mirab Abaya'],
            'Kaffa Zone' => ['Bonga', 'Decha', 'Chena'],
            'Mekelle Zone' => ['Mekelle', 'Enderta', 'Hintalo Wajirat'],
        ];

        foreach ($woredasByZone as $zoneName => $woredas) {
            $zone = Zone::query()->where('name', $zoneName)->first();

            if (! $zone) {
                continue;
            }

            foreach ($woredas as $index => $woredaName) {
                Woreda::query()->updateOrCreate(
                    ['name' => $woredaName, 'zone_id' => $zone->id],
                    [
                        'region_id' => $zone->region_id,
                        'zone_id' => $zone->id,
                        'name' => $woredaName,
                        'description' => $woredaName.' woreda in '.$zoneName.'.',
                    ]
                );
            }
        }

        $regionFallbacks = [
            'Addis Ababa' => ['Lideta', 'Kirkos'],
            'Oromiya' => ['Adama', 'Shashemene'],
        ];

        foreach ($regionFallbacks as $regionName => $names) {
            $region = Region::query()->where('name', $regionName)->first();
            if (! $region) {
                continue;
            }

            $zone = Zone::query()
                ->where('region_id', $region->id)
                ->orderBy('id')
                ->first();

            if (! $zone) {
                continue;
            }

            foreach ($names as $name) {
                Woreda::query()->updateOrCreate(
                    ['name' => $name, 'zone_id' => $zone->id],
                    [
                        'region_id' => $region->id,
                        'zone_id' => $zone->id,
                        'name' => $name,
                        'description' => $name.' woreda in '.$regionName.'.',
                    ]
                );
            }
        }
    }
}
