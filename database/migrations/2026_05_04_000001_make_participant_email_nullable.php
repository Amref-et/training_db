<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participants') || ! Schema::hasColumn('participants', 'email')) {
            return;
        }

        Schema::table('participants', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        DB::table('participants')
            ->where('email', '')
            ->update(['email' => null]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('participants') || ! Schema::hasColumn('participants', 'email')) {
            return;
        }

        DB::table('participants')
            ->select(['id'])
            ->whereNull('email')
            ->orderBy('id')
            ->chunkById(200, function ($participants): void {
                foreach ($participants as $participant) {
                    DB::table('participants')
                        ->where('id', $participant->id)
                        ->update(['email' => 'participant-'.$participant->id.'@example.invalid']);
                }
            });

        Schema::table('participants', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
