<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\WebsiteMenuItem;
use Illuminate\Database\Seeder;

class WebsiteMenuSeeder extends Seeder
{
    public function run(): void
    {
        $home = ContentPage::query()->where('slug', 'home')->first();
        $about = ContentPage::query()->where('slug', 'about')->first();
        $calendar = ContentPage::query()->where('slug', 'training-calendar')->first();

        $items = [
            [
                'title' => 'Home',
                'sort_order' => 1,
                'page_id' => $home?->id,
                'url' => null,
            ],
            [
                'title' => 'About',
                'sort_order' => 2,
                'page_id' => $about?->id,
                'url' => null,
            ],
            [
                'title' => 'Training Calendar',
                'sort_order' => 3,
                'page_id' => $calendar?->id,
                'url' => null,
            ],
            [
                'title' => 'Participant Registration',
                'sort_order' => 4,
                'page_id' => null,
                'url' => './participant-registration',
            ],
        ];

        foreach ($items as $item) {
            WebsiteMenuItem::updateOrCreate(
                ['title' => $item['title'], 'parent_id' => null],
                [
                    'url' => $item['url'],
                    'page_id' => $item['page_id'],
                    'sort_order' => $item['sort_order'],
                    'target' => '_self',
                    'is_active' => true,
                ]
            );
        }
    }
}
