<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZonesTableSeeder extends Seeder
{
    public function run(): void
    {
        $zonesByRegion = [
            'Addis Ababa' => ['Addis Ababa Zone'],
            'Afar' => ['Afar Zone 1'],
            'Amhara' => ['Bahir Dar Zone'],
            'Benishangul Gumuz' => ['Assosa Zone'],
            'Central Ethiopia' => ['Hossana Zone'],
            'Dire Dawa' => ['Dire Dawa Zone'],
            'Gambella' => ['Gambella Zone'],
            'Harari' => ['Harar Zone'],
            'Oromiya' => ['Bale Zone'],
            'Sidama' => ['Hawassa Zone'],
            'Somali' => ['Jigjiga Zone'],
            'South Ethiopia' => ['Arba Minch Zone'],
            'Southwest Ethiopia' => ['Kaffa Zone'],
            'Tigray' => ['Mekelle Zone'],
        ];

        foreach ($zonesByRegion as $regionName => $zones) {
            $region = Region::query()->where('name', $regionName)->first();

            if (! $region) {
                continue;
            }

            foreach ($zones as $zoneName) {
                Zone::query()->updateOrCreate(
                    ['name' => $zoneName],
                    [
                        'region_id' => $region->id,
                        'name' => $zoneName,
                        'description' => $zoneName.' for '.$regionName.'.',
                    ]
                );
            }
        }
    }
}
