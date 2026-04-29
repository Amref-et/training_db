<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        $home = ContentPage::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Amref Training Portal',
                'summary' => 'Public website for training updates, registrations, and results.',
                'body' => '<p>The public homepage is driven by CMS blocks and dashboard widgets. Use the admin panel to edit the messaging, filter configuration, and linked pages.</p>',
                'blocks' => [
                    [
                        'type' => 'hero',
                        'eyebrow' => 'Amref Ethiopia',
                        'heading' => 'Amref Training Database',
                        'content' => 'Track training delivery, participant reach, workshop results, and project performance from one shared platform.',
                        'button_label' => 'Register Participants',
                        'button_url' => '/participant-registration',
                    ],
                    [
                        'type' => 'dashboard',
                        'title' => 'Training Performance Dashboard',
                        'intro' => 'Explore live dashboard widgets and filter performance by project, geography, status, and organizer.',
                        'selected_filters' => [
                            'training_organizer_id',
                            'organized_by',
                            'gender',
                            'region_id',
                            'organization_id',
                            'training_id',
                            'status',
                        ],
                        'show_breakdowns' => 'yes',
                    ],
                    [
                        'type' => 'cta',
                        'heading' => 'Need to register a new participant records?',
                        'content' => 'Use the public participant registration form to collect records with the same validation and ID-generation logic as the admin interface.',
                        'button_label' => 'Registration',
                        'button_url' => '/participant-registration',
                    ],
                ],
                'status' => 'published',
                'is_homepage' => true,
                'meta_title' => 'Amref Training Portal',
            ]
        );

        ContentPage::updateOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About the Program',
                'summary' => 'Overview of the Amref training portal.',
                'body' => '<p>This platform supports the planning, delivery, monitoring, and reporting of training interventions across projects, organizations, and geographic levels.</p>',
                'blocks' => [
                    [
                        'type' => 'rich_text',
                        'title' => 'Program Overview',
                        'content' => '<p>The Amref Training Database centralizes participant enrollment, project tracking, event delivery, workshop scoring, and public reporting.</p><p>Administrators can manage structured data while public users can access curated CMS pages, dashboards, and participant registration.</p>',
                    ],
                    [
                        'type' => 'feature_list',
                        'title' => 'What the Platform Supports',
                        'intro' => 'Core capabilities available across the administrative and public interfaces.',
                        'items' => [
                            'Training event planning and delivery tracking',
                            'Participant registration with hierarchy validation',
                            'Workshop-level pre, mid, and post score monitoring',
                            'Project, dashboard, calendar, and CMS publishing tools',
                        ],
                    ],
                ],
                'status' => 'published',
                'is_homepage' => false,
                'meta_title' => 'About the Amref Training Database',
            ]
        );

        ContentPage::updateOrCreate(
            ['slug' => 'training-calendar'],
            [
                'title' => 'Training Calendar',
                'summary' => 'Embedded calendar view of scheduled training events.',
                'body' => '<iframe src="/embed/training-events-calendar" title="Training Events Calendar" width="100%" height="900" style="border:0;max-width:100%;" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>',
                'status' => 'published',
                'is_homepage' => false,
                'meta_title' => 'Training Events Calendar',
            ]
        );

        ContentPage::updateOrCreate(
            ['slug' => 'contact'],
            [
                'title' => 'Contact',
                'summary' => 'Contact details for the training management team.',
                'body' => '<p>Use the administrative appearance settings to update footer contact information and site branding. This page can be expanded with operational contacts, support procedures, and escalation routes.</p>',
                'blocks' => [
                    [
                        'type' => 'callout',
                        'title' => 'Support',
                        'content' => 'For technical support, contact the system administrator listed in the admin user seeder or update this page with your deployment-specific support team details.',
                        'tone' => 'info',
                    ],
                ],
                'status' => 'published',
                'is_homepage' => false,
                'meta_title' => 'Contact the Training Team',
            ]
        );

        WebsiteSetting::query()->updateOrCreate(
            ['id' => 1],
            array_merge(WebsiteSetting::defaults(), [
                'site_name' => 'Amref Training Portal',
                'site_tagline' => 'Training management, participant tracking, and public reporting.',
                'header_cta_label' => 'Participant Registration',
                'header_cta_url' => '/participant-registration',
                'footer_title' => 'Amref Training Portal',
            ])
        );

        if ($home) {
            ContentPage::query()
                ->where('id', '!=', $home->id)
                ->update(['is_homepage' => false]);
        }
    }
}
