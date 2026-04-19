<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        ContentPage::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'HIL Training Website',
                'summary' => 'A configurable home page managed from the CMS.',
                'body' => '<p>Use the CMS to update this homepage content, publish additional pages, and keep the site current without editing code.</p>',
                'status' => 'published',
                'is_homepage' => true,
                'meta_title' => 'HIL Training Website',
            ]
        );
    }
}
