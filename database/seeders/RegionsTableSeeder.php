<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionsTableSeeder extends Seeder
{
    public function run()
    {
        $regions = [
            'Addis Ababa', 'Afar', 'Amhara', 'Benishangul-Gumuz',
            'Central Ethiopia', 'Dire Dawa', 'Gambela', 'Harari',
            'Oromia', 'Sidama', 'Somali', 'South Ethiopia Regional State'
        ];

        foreach ($regions as $region) {
            Region::create(['region_name' => $region]);
        }
    }
}