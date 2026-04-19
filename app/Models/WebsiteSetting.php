<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WebsiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name',
        'site_tagline',
        'favicon_url',
        'header_logo_url',
        'header_logo_height',
        'header_cta_label',
        'header_cta_url',
        'header_background_color',
        'header_text_color',
        'header_link_color',
        'body_background_color',
        'body_text_color',
        'body_panel_color',
        'body_accent_color',
        'primary_color',
        'secondary_color',
        'accent_color',
        'footer_title',
        'footer_logo_url',
        'footer_about',
        'footer_address',
        'footer_email',
        'footer_phone',
        'footer_note',
        'footer_background_color',
        'footer_text_color',
        'footer_link_color',
        'radius_sm',
        'radius_md',
        'radius_lg',
        'radius_xl',
        'radius_pill',
        'footer_copyright',
        'show_admin_link',
        'show_login_link',
        'login_eyebrow',
        'login_title',
        'login_subtitle',
        'login_background_start_color',
        'login_background_end_color',
        'login_background_accent_color',
        'login_card_background_color',
        'login_form_title',
        'login_form_subtitle',
        'login_email_label',
        'login_password_label',
        'login_remember_label',
        'login_submit_label',
        'login_back_label',
        'login_feature_1',
        'login_feature_2',
        'login_feature_3',
        'custom_css',
        'custom_js',
    ];

    protected $casts = [
        'show_admin_link' => 'boolean',
        'show_login_link' => 'boolean',
        'radius_sm' => 'integer',
        'radius_md' => 'integer',
        'radius_lg' => 'integer',
        'radius_xl' => 'integer',
        'radius_pill' => 'integer',
        'header_logo_height' => 'integer',
    ];

    public static function defaults(): array
    {
        return [
            'site_name' => config('app.name', 'HIL Website'),
            'site_tagline' => 'High-impact learning and implementation platform.',
            'favicon_url' => null,
            'header_logo_url' => null,
            'header_logo_height' => 56,
            'header_cta_label' => 'Get Started',
            'header_cta_url' => '/',
            'header_background_color' => '#ffffff',
            'header_text_color' => '#0f172a',
            'header_link_color' => '#334155',
            'body_background_color' => '#f8fafc',
            'body_text_color' => '#0f172a',
            'body_panel_color' => '#ffffff',
            'body_accent_color' => '#0f766e',
            'primary_color' => '#0f766e',
            'secondary_color' => '#0f172a',
            'accent_color' => '#f59e0b',
            'footer_title' => config('app.name', 'HIL Website'),
            'footer_logo_url' => null,
            'footer_about' => 'Empowering teams with practical training, coaching, and measurable field results.',
            'footer_address' => null,
            'footer_email' => null,
            'footer_phone' => null,
            'footer_note' => null,
            'footer_background_color' => '#0f172a',
            'footer_text_color' => '#e2e8f0',
            'footer_link_color' => '#cbd5e1',
            'radius_sm' => 10,
            'radius_md' => 14,
            'radius_lg' => 18,
            'radius_xl' => 24,
            'radius_pill' => 999,
            'footer_copyright' => 'All rights reserved.',
            'show_admin_link' => true,
            'show_login_link' => true,
            'login_eyebrow' => 'Admin Access',
            'login_title' => null,
            'login_subtitle' => 'Use your administrator account to manage training, participants, projects, and reporting.',
            'login_background_start_color' => '#082f49',
            'login_background_end_color' => '#0f766e',
            'login_background_accent_color' => '#d97706',
            'login_card_background_color' => '#ffffff',
            'login_form_title' => 'Welcome back',
            'login_form_subtitle' => 'Enter your credentials to continue to the administrative workspace.',
            'login_email_label' => 'Email',
            'login_password_label' => 'Password',
            'login_remember_label' => 'Remember me',
            'login_submit_label' => 'Login',
            'login_back_label' => 'Back to website',
            'login_feature_1' => 'Centralized access to training operations, participants, projects, and reporting.',
            'login_feature_2' => 'Brand-consistent authentication experience managed from the appearance settings.',
            'login_feature_3' => 'Secure administrator entry point with direct access back to the public website.',
            'custom_css' => null,
            'custom_js' => null,
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('website_settings')) {
            return new self(self::defaults());
        }

        return self::query()->firstOrCreate(
            ['id' => 1],
            self::defaults()
        );
    }
}
