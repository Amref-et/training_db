<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            'Addis Ababa',
            'Afar',
            'Amhara',
            'Benishangul Gumuz',
            'Central Ethiopia',
            'Dire Dawa',
            'Gambella',
            'Harari',
            'Oromiya',
            'Sidama',
            'Somali',
            'South Ethiopia',
            'Southwest Ethiopia',
            'Tigray',
        ];

        foreach ($regions as $name) {
            Region::query()->updateOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
