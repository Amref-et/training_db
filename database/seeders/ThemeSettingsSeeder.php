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
            'site_name' => 'Amref training database',
            'site_tagline' => 'Track progress, outcomes, and impact of a training.',
            'favicon_url' => null,
            'header_logo_url' => 'appearance/logos/jAwQPW03gkckVE5w9wdDWgjTZuJsOs67pWpyZLpH.png',
            'header_logo_height' => 56,
            'header_cta_label' => 'Get Started',
            'header_cta_url' => null,
            'primary_color' => '#000000',
            'secondary_color' => '#0f172a',
            'accent_color' => '#d4d8dd',
            'header_background_color' => '#000000',
            'header_text_color' => '#ffffff',
            'header_link_color' => '#d4d8dd',
            'body_background_color' => '#f7f7f7',
            'body_text_color' => '#0f172a',
            'body_panel_color' => '#ffffff',
            'body_accent_color' => '#000000',
            'footer_title' => 'Amref training database',
            'footer_logo_url' => null,
            'footer_about' => '<p>Empowering professionals with practical training, coaching, and measurable results.</p>',
            'footer_address' => 'Ethiopia',
            'footer_email' => 'bekalu.assamnew@amref.org',
            'footer_phone' => '0917804269',
            'footer_note' => null,
            'footer_background_color' => '#0f172a',
            'footer_text_color' => '#e2e8f0',
            'footer_link_color' => '#cbd5e1',
            'radius_sm' => 0,
            'radius_md' => 20,
            'radius_lg' => 0,
            'radius_xl' => 0,
            'radius_pill' => 999,
            'footer_copyright' => 'All rights reserved.',
            'show_admin_link' => true,
            'show_login_link' => true,
            'public_home_dashboard_tab_id' => 1,
            'login_eyebrow' => null,
            'login_title' => null,
            'login_subtitle' => null,
            'login_background_start_color' => '#082f49',
            'login_background_end_color' => '#082f49',
            'login_background_accent_color' => '#082f49',
            'login_card_background_color' => '#ffffff',
            'login_form_title' => null,
            'login_form_subtitle' => null,
            'login_email_label' => null,
            'login_password_label' => null,
            'login_remember_label' => null,
            'login_submit_label' => null,
            'login_back_label' => null,
            'login_feature_1' => null,
            'login_feature_2' => null,
            'login_feature_3' => null,
            'custom_css' => null,
            'custom_js' => null,
        ]);

        $settings->save();
    }
}
