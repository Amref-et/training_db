<?php

namespace Tests\Feature;

use App\Models\FabFaqItem;
use App\Models\User;
use App\Models\WebsiteSetting;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabFaqChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_appearance_settings_can_enable_public_fab_chatbot(): void
    {
        $response = $this
            ->actingAs($this->adminUser())
            ->put(route('admin.appearance.update'), array_merge($this->appearancePayload(), [
                'fab_chat_enabled' => '1',
            ]));

        $response->assertRedirect(route('admin.appearance.edit'));
        $this->assertTrue((bool) WebsiteSetting::current()->fab_chat_enabled);
    }

    public function test_default_faq_items_are_generated_for_core_features(): void
    {
        $tree = FabFaqItem::tree(true);

        $this->assertTrue($tree->pluck('title')->contains('Participant Support'));
        $this->assertTrue($tree->pluck('title')->contains('Training Events'));
        $this->assertTrue($tree->pluck('title')->contains('Administration'));
        $this->assertTrue($tree->pluck('title')->contains('Website And Content'));
        $this->assertTrue($tree->pluck('title')->contains('Projects And Learning Materials'));
        $this->assertTrue($tree->pluck('title')->contains('System Monitoring'));
        $this->assertDatabaseHas('fab_faq_items', [
            'type' => FabFaqItem::TYPE_QUESTION,
            'title' => 'Can admins import users?',
            'visibility' => FabFaqItem::VISIBILITY_ADMIN,
            'link_url' => '/admin/users',
        ]);
        $this->assertDatabaseHas('fab_faq_items', [
            'type' => FabFaqItem::TYPE_QUESTION,
            'title' => 'How do I enable the floating FAQ chatbot?',
            'visibility' => FabFaqItem::VISIBILITY_ADMIN,
            'link_url' => '/admin/appearance',
        ]);
        $this->assertDatabaseHas('fab_faq_items', [
            'type' => FabFaqItem::TYPE_QUESTION,
            'title' => 'How do I register as a participant?',
            'visibility' => FabFaqItem::VISIBILITY_PUBLIC,
            'link_url' => '/participant-registration',
        ]);
    }

    public function test_default_faq_command_can_overwrite_existing_faq_items(): void
    {
        FabFaqItem::query()->create([
            'type' => FabFaqItem::TYPE_CATEGORY,
            'title' => 'Custom Only',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this
            ->artisan('fab-faqs:seed-default')
            ->assertExitCode(1);

        $this->assertDatabaseHas('fab_faq_items', [
            'title' => 'Custom Only',
        ]);

        $this
            ->artisan('fab-faqs:seed-default --force')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('fab_faq_items', [
            'title' => 'Custom Only',
        ]);
        $this->assertDatabaseHas('fab_faq_items', [
            'type' => FabFaqItem::TYPE_QUESTION,
            'title' => 'Can admins import users?',
        ]);
    }

    public function test_admin_can_manage_hierarchical_faq_items_and_reorder_siblings(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->post(route('admin.fab-faqs.store'), [
                'type' => FabFaqItem::TYPE_CATEGORY,
                'visibility' => FabFaqItem::VISIBILITY_BOTH,
                'title' => 'Training',
                'sort_order' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.fab-faqs.index'));

        $category = FabFaqItem::query()->where('title', 'Training')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.fab-faqs.store'), [
                'parent_id' => $category->id,
                'type' => FabFaqItem::TYPE_CATEGORY,
                'visibility' => FabFaqItem::VISIBILITY_BOTH,
                'title' => 'Enrollment',
                'sort_order' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.fab-faqs.index'));

        $subcategory = FabFaqItem::query()->where('title', 'Enrollment')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.fab-faqs.store'), [
                'parent_id' => $subcategory->id,
                'type' => FabFaqItem::TYPE_QUESTION,
                'visibility' => FabFaqItem::VISIBILITY_PUBLIC,
                'title' => 'How do I join a training?',
                'answer' => 'Use the public training request form.',
                'link_label' => 'Request enrollment',
                'link_url' => '/training-event-join-request',
                'sort_order' => 20,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.fab-faqs.index'));

        $secondQuestion = FabFaqItem::query()->create([
            'parent_id' => $subcategory->id,
            'type' => FabFaqItem::TYPE_QUESTION,
            'visibility' => FabFaqItem::VISIBILITY_PUBLIC,
            'title' => 'Where do I see my status?',
            'answer' => 'The team will contact you after review.',
            'sort_order' => 30,
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.fab-faqs.move', $secondQuestion), [
                'direction' => 'up',
            ])
            ->assertRedirect();

        $firstQuestion = FabFaqItem::query()->where('title', 'How do I join a training?')->firstOrFail();
        $this->assertLessThan($firstQuestion->refresh()->sort_order, $secondQuestion->refresh()->sort_order);

        $this
            ->actingAs($admin)
            ->get(route('admin.fab-faqs.index'))
            ->assertOk()
            ->assertSee('Training')
            ->assertSee('Enrollment')
            ->assertSee('How do I join a training?')
            ->assertSee('Public')
            ->assertSee('Request enrollment');
    }

    public function test_public_page_renders_enabled_fab_chatbot_with_active_faq_tree(): void
    {
        WebsiteSetting::current()->forceFill(['fab_chat_enabled' => true])->save();
        $category = FabFaqItem::query()->create([
            'type' => FabFaqItem::TYPE_CATEGORY,
            'visibility' => FabFaqItem::VISIBILITY_BOTH,
            'title' => 'Training',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $subcategory = FabFaqItem::query()->create([
            'parent_id' => $category->id,
            'type' => FabFaqItem::TYPE_CATEGORY,
            'visibility' => FabFaqItem::VISIBILITY_BOTH,
            'title' => 'Enrollment',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        FabFaqItem::query()->create([
            'parent_id' => $subcategory->id,
            'type' => FabFaqItem::TYPE_QUESTION,
            'visibility' => FabFaqItem::VISIBILITY_PUBLIC,
            'title' => 'How do I join?',
            'answer' => 'Use the public training request form.',
            'link_label' => 'Request enrollment',
            'link_url' => '/training-event-join-request',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        FabFaqItem::query()->create([
            'parent_id' => $subcategory->id,
            'type' => FabFaqItem::TYPE_QUESTION,
            'visibility' => FabFaqItem::VISIBILITY_ADMIN,
            'title' => 'Can admins import users?',
            'answer' => 'Open Users to import accounts.',
            'link_label' => 'Open users',
            'link_url' => '/admin/users',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-fab-chatbot', false)
            ->assertSee('data-fab-audience="public"', false)
            ->assertSee('FAQ Assistant')
            ->assertSee('Training')
            ->assertSee('Enrollment')
            ->assertSee('How do I join?')
            ->assertSee('Use the public training request form.')
            ->assertSee('Request enrollment')
            ->assertSee('\/training-event-join-request', false)
            ->assertDontSee('Can admins import users?')
            ->assertDontSee('\/admin\/users', false);

        $adminHtml = view('website.partials.fab-chatbot', [
            'settings' => WebsiteSetting::current(),
            'audience' => 'admin',
        ])->render();

        $this->assertStringContainsString('data-fab-audience="admin"', $adminHtml);
        $this->assertStringContainsString('Can admins import users?', $adminHtml);
        $this->assertStringContainsString('\/admin\/users', $adminHtml);
        $this->assertStringNotContainsString('How do I join?', $adminHtml);
        $this->assertStringNotContainsString('\/training-event-join-request', $adminHtml);

        WebsiteSetting::current()->forceFill(['fab_chat_enabled' => false])->save();

        $this
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-fab-chatbot', false);
    }

    private function appearancePayload(): array
    {
        return [
            'site_name' => 'HIL CMS',
            'site_tagline' => 'Training platform',
            'header_logo_height' => 56,
            'header_cta_label' => 'Get Started',
            'header_cta_url' => 'https://example.com',
            'header_background_color' => '#ffffff',
            'header_text_color' => '#0f172a',
            'header_link_color' => '#334155',
            'body_background_color' => '#f8fafc',
            'body_text_color' => '#0f172a',
            'body_panel_color' => '#ffffff',
            'body_accent_color' => '#0f766e',
            'footer_title' => 'HIL CMS',
            'footer_background_color' => '#0f172a',
            'footer_text_color' => '#e2e8f0',
            'footer_link_color' => '#cbd5e1',
            'radius_sm' => 10,
            'radius_md' => 14,
            'radius_lg' => 18,
            'radius_xl' => 24,
            'radius_pill' => 999,
            'footer_copyright' => 'All rights reserved.',
            'login_background_start_color' => '#082f49',
            'login_background_end_color' => '#0f766e',
            'login_background_accent_color' => '#d97706',
            'login_card_background_color' => '#ffffff',
            'show_admin_link' => '1',
            'show_login_link' => '1',
        ];
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        return $user;
    }
}
