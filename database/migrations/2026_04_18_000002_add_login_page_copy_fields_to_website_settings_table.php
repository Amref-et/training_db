<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_settings')) {
            return;
        }

        Schema::table('website_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('website_settings', 'login_form_title')) {
                $table->string('login_form_title')->nullable()->after('login_card_background_color');
            }

            if (! Schema::hasColumn('website_settings', 'login_form_subtitle')) {
                $table->string('login_form_subtitle')->nullable()->after('login_form_title');
            }

            if (! Schema::hasColumn('website_settings', 'login_email_label')) {
                $table->string('login_email_label')->nullable()->after('login_form_subtitle');
            }

            if (! Schema::hasColumn('website_settings', 'login_password_label')) {
                $table->string('login_password_label')->nullable()->after('login_email_label');
            }

            if (! Schema::hasColumn('website_settings', 'login_remember_label')) {
                $table->string('login_remember_label')->nullable()->after('login_password_label');
            }

            if (! Schema::hasColumn('website_settings', 'login_submit_label')) {
                $table->string('login_submit_label')->nullable()->after('login_remember_label');
            }

            if (! Schema::hasColumn('website_settings', 'login_back_label')) {
                $table->string('login_back_label')->nullable()->after('login_submit_label');
            }

            if (! Schema::hasColumn('website_settings', 'login_feature_1')) {
                $table->string('login_feature_1')->nullable()->after('login_back_label');
            }

            if (! Schema::hasColumn('website_settings', 'login_feature_2')) {
                $table->string('login_feature_2')->nullable()->after('login_feature_1');
            }

            if (! Schema::hasColumn('website_settings', 'login_feature_3')) {
                $table->string('login_feature_3')->nullable()->after('login_feature_2');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_settings')) {
            return;
        }

        Schema::table('website_settings', function (Blueprint $table) {
            $columns = [];

            foreach ([
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
            ] as $column) {
                if (Schema::hasColumn('website_settings', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
