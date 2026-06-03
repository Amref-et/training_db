<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\WebsiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AppearanceController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = WebsiteSetting::current();

        return response()->json([
            'data' => [
                'site' => [
                    'name' => $settings->site_name ?: config('app.name', 'HIL Website'),
                    'tagline' => $settings->site_tagline,
                ],
                'logos' => [
                    'header_url' => $this->mediaUrl($settings->header_logo_url),
                    'footer_url' => $this->mediaUrl($settings->footer_logo_url),
                    'favicon_url' => $this->mediaUrl($settings->favicon_url),
                    'header_height' => (int) ($settings->header_logo_height ?: 56),
                ],
                'colors' => [
                    'header_background' => $settings->header_background_color ?: '#ffffff',
                    'header_text' => $settings->header_text_color ?: '#0f172a',
                    'header_link' => $settings->header_link_color ?: '#334155',
                    'body_background' => $settings->body_background_color ?: '#f8fafc',
                    'body_text' => $settings->body_text_color ?: '#0f172a',
                    'body_panel' => $settings->body_panel_color ?: '#ffffff',
                    'body_accent' => $settings->body_accent_color ?: '#0f766e',
                    'footer_background' => $settings->footer_background_color ?: '#0f172a',
                    'footer_text' => $settings->footer_text_color ?: '#e2e8f0',
                    'footer_link' => $settings->footer_link_color ?: '#cbd5e1',
                ],
                'radii' => [
                    'sm' => (int) ($settings->radius_sm ?? 10),
                    'md' => (int) ($settings->radius_md ?? 14),
                    'lg' => (int) ($settings->radius_lg ?? 18),
                    'xl' => (int) ($settings->radius_xl ?? 24),
                    'pill' => (int) ($settings->radius_pill ?? 999),
                ],
                'login' => [
                    'eyebrow' => $settings->login_eyebrow,
                    'title' => $settings->login_title,
                    'subtitle' => $settings->login_subtitle,
                    'background_start' => $settings->login_background_start_color ?: '#082f49',
                    'background_end' => $settings->login_background_end_color ?: '#0f766e',
                    'background_accent' => $settings->login_background_accent_color ?: '#d97706',
                    'card_background' => $settings->login_card_background_color ?: '#ffffff',
                    'form_title' => $settings->login_form_title,
                    'form_subtitle' => $settings->login_form_subtitle,
                    'email_label' => $settings->login_email_label ?: 'Email',
                    'password_label' => $settings->login_password_label ?: 'Password',
                    'submit_label' => $settings->login_submit_label ?: 'Login',
                    'feature_1' => $settings->login_feature_1,
                    'feature_2' => $settings->login_feature_2,
                    'feature_3' => $settings->login_feature_3,
                ],
            ],
        ]);
    }

    private function mediaUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return url(ltrim($value, '/'));
        }

        $storageUrl = Storage::disk('public')->url($value);

        return str_starts_with($storageUrl, 'http://') || str_starts_with($storageUrl, 'https://')
            ? $storageUrl
            : url(ltrim($storageUrl, '/'));
    }
}
