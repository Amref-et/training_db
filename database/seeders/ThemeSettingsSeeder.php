<?php

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class ThemeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = WebsiteSetting::current();

        $settings->fill([
            'site_name' => 'Amref HIL Training Portal',
            'site_tagline' => 'Training management, public reporting, and participant registration.',
            'header_cta_label' => 'Participant Registration',
            'header_cta_url' => '/participant-registration',
            'primary_color' => '#0f766e',
            'secondary_color' => '#0f172a',
            'accent_color' => '#f59e0b',
            'header_background_color' => '#ffffff',
            'header_text_color' => '#0f172a',
            'header_link_color' => '#334155',
            'body_background_color' => '#f8fafc',
            'body_text_color' => '#0f172a',
            'body_panel_color' => '#ffffff',
            'body_accent_color' => '#0f766e',
            'footer_title' => 'Amref HIL Training Portal',
            'footer_about' => 'Centralized platform for training delivery, participant tracking, dashboards, and CMS publishing.',
            'footer_background_color' => '#0f172a',
            'footer_text_color' => '#e2e8f0',
            'footer_link_color' => '#cbd5e1',
            'show_admin_link' => true,
            'show_login_link' => true,
            'login_eyebrow' => 'Administrator Access',
            'login_title' => 'Amref HIL Training Database',
            'login_subtitle' => 'Manage participants, projects, training events, dashboards, and public content from one workspace.',
            'login_form_title' => 'Sign in',
            'login_form_subtitle' => 'Use your administrator account to continue.',
            'login_submit_label' => 'Login',
            'login_back_label' => 'Back to website',
        ]);

        $settings->save();
    }
}
