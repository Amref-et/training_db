<?php

namespace App\Http\Controllers;

use App\Models\WebsiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function edit(): View
    {
        return $this->appearanceView();
    }

    public function customCss(): View
    {
        return $this->appearanceView('custom-css');
    }

    public function customJs(): View
    {
        return $this->appearanceView('custom-js');
    }

    public function update(Request $request): RedirectResponse
    {
        $colorRule = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $settings = WebsiteSetting::current();
        $beforeState = $this->audit()->snapshotModel($settings);

        $validated = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'site_tagline' => 'nullable|string|max:255',
            'favicon_url' => 'nullable|string|max:255',
            'favicon_file' => 'nullable|file|mimes:ico,png,jpg,jpeg,webp,svg|max:2048',
            'header_logo_url' => 'nullable|string|max:255',
            'header_logo_height' => 'required|integer|min:24|max:220',
            'header_logo_file' => 'nullable|image|max:4096',
            'header_cta_label' => 'nullable|string|max:60',
            'header_cta_url' => 'nullable|url|max:255',
            'header_background_color' => $colorRule,
            'header_text_color' => $colorRule,
            'header_link_color' => $colorRule,
            'body_background_color' => $colorRule,
            'body_text_color' => $colorRule,
            'body_panel_color' => $colorRule,
            'body_accent_color' => $colorRule,
            'footer_title' => 'nullable|string|max:255',
            'footer_logo_url' => 'nullable|string|max:255',
            'footer_logo_file' => 'nullable|image|max:4096',
            'footer_about' => 'nullable|string',
            'footer_address' => 'nullable|string|max:255',
            'footer_email' => 'nullable|email|max:255',
            'footer_phone' => 'nullable|string|max:100',
            'footer_note' => 'nullable|string',
            'footer_background_color' => $colorRule,
            'footer_text_color' => $colorRule,
            'footer_link_color' => $colorRule,
            'radius_sm' => 'required|integer|min:0|max:100',
            'radius_md' => 'required|integer|min:0|max:100',
            'radius_lg' => 'required|integer|min:0|max:100',
            'radius_xl' => 'required|integer|min:0|max:120',
            'radius_pill' => 'required|integer|min:0|max:999',
            'footer_copyright' => 'nullable|string|max:255',
            'login_eyebrow' => 'nullable|string|max:80',
            'login_title' => 'nullable|string|max:255',
            'login_subtitle' => 'nullable|string|max:255',
            'login_background_start_color' => $colorRule,
            'login_background_end_color' => $colorRule,
            'login_background_accent_color' => $colorRule,
            'login_card_background_color' => $colorRule,
            'login_form_title' => 'nullable|string|max:255',
            'login_form_subtitle' => 'nullable|string|max:255',
            'login_email_label' => 'nullable|string|max:80',
            'login_password_label' => 'nullable|string|max:80',
            'login_remember_label' => 'nullable|string|max:80',
            'login_submit_label' => 'nullable|string|max:80',
            'login_back_label' => 'nullable|string|max:80',
            'login_feature_1' => 'nullable|string|max:255',
            'login_feature_2' => 'nullable|string|max:255',
            'login_feature_3' => 'nullable|string|max:255',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
        ]);

        $settings->fill(array_merge(WebsiteSetting::defaults(), $validated));

        if ($request->hasFile('header_logo_file')) {
            $settings->header_logo_url = $request->file('header_logo_file')->store('appearance/logos', 'public');
        }

        if ($request->hasFile('footer_logo_file')) {
            $settings->footer_logo_url = $request->file('footer_logo_file')->store('appearance/logos', 'public');
        }

        if ($request->hasFile('favicon_file')) {
            $settings->favicon_url = $request->file('favicon_file')->store('appearance/favicons', 'public');
        }

        // Keep legacy fields aligned for backward compatibility.
        $settings->primary_color = $validated['body_accent_color'];
        $settings->secondary_color = $validated['footer_background_color'];
        $settings->accent_color = $validated['header_link_color'];

        $settings->show_admin_link = $request->boolean('show_admin_link');
        $settings->show_login_link = $request->boolean('show_login_link');
        $settings->save();
        $settings->refresh();
        $this->audit()->logModelUpdated($settings, $beforeState, 'Appearance settings updated');

        $section = trim((string) $request->input('_section'));
        $redirectRoute = match ($section) {
            'custom-css' => 'admin.appearance.custom-css',
            'custom-js' => 'admin.appearance.custom-js',
            default => 'admin.appearance.edit',
        };

        return redirect()->route($redirectRoute)->with('success', 'Appearance settings updated successfully.');
    }

    private function appearanceView(?string $activeSection = null): View
    {
        return view('admin.appearance.edit', [
            'settings' => WebsiteSetting::current(),
            'activeSection' => $activeSection,
        ]);
    }
}
