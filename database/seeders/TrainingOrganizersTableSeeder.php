<?php

namespace Database\Seeders;

use App\Models\ProjectSubawardee;
use App\Models\TrainingOrganizer;
use Illuminate\Database\Seeder;

class TrainingOrganizersTableSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            [
                'project_code' => 'IHSS-001',
                'project_name' => 'iHSS',
                'subawardees' => ['Mamela', 'Consortium Partner A'],
            ],
            [
                'project_code' => 'RMNCH-002',
                'project_name' => 'RMNCH Strengthening',
                'subawardees' => ['Regional Implementer North'],
            ],
            [
                'project_code' => 'YLDP-003',
                'project_name' => 'Youth Leadership Development',
                'subawardees' => ['Youth Network Ethiopia'],
            ],
            [
                'project_code' => 'PHC-004',
                'project_name' => 'Primary Health Care Support',
                'subawardees' => [],
            ],
            [
                'project_code' => 'MNH-005',
                'project_name' => 'Maternal and Newborn Health Initiative',
                'subawardees' => ['Safe Birth Alliance'],
            ],
        ];

        foreach ($projects as $definition) {
            $organizer = TrainingOrganizer::query()->updateOrCreate(
                ['project_code' => $definition['project_code']],
                [
                    'project_code' => $definition['project_code'],
                    'project_name' => $definition['project_name'],
                    'title' => $definition['project_name'],
                ]
            );

            foreach ($definition['subawardees'] as $index => $name) {
                ProjectSubawardee::query()->updateOrCreate(
                    [
                        'project_id' => $organizer->id,
                        'subawardee_name' => $name,
                    ],
                    [
                        'project_id' => $organizer->id,
                        'subawardee_name' => $name,
                    ]
                );
            }
        }
    }
}
