<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Woreda;
use Illuminate\Database\Seeder;

class WoredasTableSeeder extends Seeder
{
    public function run()
    {
        $regions = Region::all();

        foreach ($regions as $region) {
            for ($i = 1; $i <= 5; $i++) {
                Woreda::create([
                    'region_id' => $region->region_id,
                    'woreda_name' => "Woreda $i - {$region->region_name}",
                    'woreda_description' => "Description for Woreda $i in {$region->region_name}"
                ]);
            }
        }
    }
}