<?php

namespace Database\Seeders;

use App\Models\Profession;
use Illuminate\Database\Seeder;

class ProfessionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $professions = [
            'Physician',
            'Nurse',
            'Midwife',
            'Health Officer',
            'Pharmacy professional',
            'Laboratory professional',
            'Public Health Professional',
            'Monitoring and Evaluation Specialist',
            'Program Manager',
            'Trainer',
        ];

        foreach ($professions as $index => $name) {
            Profession::query()->updateOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'description' => $name.' profession reference value.',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
