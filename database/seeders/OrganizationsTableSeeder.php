<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Woreda;
use Illuminate\Database\Seeder;

class OrganizationsTableSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['name' => 'Amref Health Africa', 'category' => 'NGO', 'type' => 'Health', 'woreda' => 'Bole'],
            ['name' => 'Federal Ministry of Health', 'category' => 'Government', 'type' => 'Health', 'woreda' => 'Bole'],
            ['name' => 'Regional Health Bureau - Amhara', 'category' => 'Government', 'type' => 'Health', 'woreda' => 'Bahir Dar Zuria'],
            ['name' => 'Regional Health Bureau - Oromiya', 'category' => 'Government', 'type' => 'Health', 'woreda' => 'Robe'],
            ['name' => 'Jigjiga University', 'category' => 'Government', 'type' => 'Education', 'woreda' => 'Jigjiga'],
            ['name' => 'Hawassa University', 'category' => 'Government', 'type' => 'Education', 'woreda' => 'Hawassa Zuria'],
            ['name' => 'Bonga General Hospital', 'category' => 'Government', 'type' => 'Health Facility', 'woreda' => 'Bonga'],
            ['name' => 'Dire Dawa Health Office', 'category' => 'Government', 'type' => 'Health', 'woreda' => 'Dire Dawa Urban'],
            ['name' => 'Assosa Referral Hospital', 'category' => 'Government', 'type' => 'Health Facility', 'woreda' => 'Assosa'],
            ['name' => 'Mekelle University College of Health Sciences', 'category' => 'Government', 'type' => 'Education', 'woreda' => 'Mekelle'],
        ];

        foreach ($definitions as $definition) {
            $woreda = Woreda::query()
                ->with('zone')
                ->where('name', $definition['woreda'])
                ->first();

            if (! $woreda) {
                continue;
            }

            Organization::query()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    'name' => $definition['name'],
                    'category' => $definition['category'],
                    'type' => $definition['type'],
                    'region_id' => $woreda->region_id,
                    'zone_id' => $woreda->zone_id,
                    'zone' => optional($woreda->zone)->name,
                    'woreda_id' => $woreda->id,
                    'city_town' => $woreda->name,
                    'phone' => null,
                    'fax' => null,
                ]
            );
        }
    }
}
