<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_settings')) {
            return;
        }

        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->nullable();
            $table->string('site_tagline')->nullable();
            $table->string('header_logo_url')->nullable();
            $table->string('header_cta_label')->nullable();
            $table->string('header_cta_url')->nullable();
            $table->string('primary_color', 7)->default('#0f766e');
            $table->string('secondary_color', 7)->default('#0f172a');
            $table->string('accent_color', 7)->default('#f59e0b');
            $table->string('footer_title')->nullable();
            $table->text('footer_about')->nullable();
            $table->string('footer_address')->nullable();
            $table->string('footer_email')->nullable();
            $table->string('footer_phone')->nullable();
            $table->longText('footer_note')->nullable();
            $table->string('footer_copyright')->nullable();
            $table->boolean('show_admin_link')->default(true);
            $table->boolean('show_login_link')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
