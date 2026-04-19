<?php

namespace Database\Seeders;

use App\Models\TrainingOrganizer;
use Illuminate\Database\Seeder;

class TrainingOrganizersTableSeeder extends Seeder
{
    public function run()
    {
        for ($i = 1; $i <= 10; $i++) {
            TrainingOrganizer::create([
                'training_organizer_name' => "Training Organizer $i"
            ]);
        }
    }
}
