<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationsTableSeeder extends Seeder
{
    public function run()
    {
        $categories = ['Government', 'NGO', 'Private', 'International'];
        $types = ['Education', 'Health', 'Agriculture', 'Other'];

        for ($i = 1; $i <= 20; $i++) {
            Organization::create([
                'organization_name' => "Organization $i",
                'organization_category' => $categories[array_rand($categories)],
                'organization_type' => $types[array_rand($types)]
            ]);
        }
    }
}