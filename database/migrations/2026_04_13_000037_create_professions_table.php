<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('professions')) {
            Schema::create('professions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $defaults = [
            'Administration/Management related',
            'Finance related',
            'Midwife',
            'SI related (M&E, Surveillance, IT)',
            'Anesthetist/Anesthesiologist',
            'Health Assistant',
            'Nurse',
            'Sociology/Psychology related',
            'Case Manager/Peer Educator/Expert Patient',
            'Health Extension Worker',
            'Pharmacy professional',
            'Student',
            'Community Volunteer',
            'Health Officer',
            'Physician',
            'Traditional Healer/Birth Attendant',
            'Community/Peer Leader',
            'Journalist',
            'Physiotherapist',
            'Trainer/Teacher/Instructor/Tutor',
            'Counselor',
            'Kebele Health Worker',
            'Public Health/Program related',
            'Youth Worker',
            'Dentist',
            'Laboratory Professional',
            'Religious Leader',
            'Environmental health related',
            'Mentor Mother',
            'Other (specify)',
        ];

        $now = now();

        $payload = collect($defaults)
            ->values()
            ->map(fn (string $name, int $index) => [
                'name' => $name,
                'description' => null,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if (! empty($payload)) {
            DB::table('professions')->upsert(
                $payload,
                ['name'],
                ['sort_order', 'is_active', 'updated_at']
            );
        }

        if (Schema::hasTable('participants') && Schema::hasColumn('participants', 'profession')) {
            $existingProfessionNames = DB::table('participants')
                ->whereNotNull('profession')
                ->whereRaw('TRIM(profession) <> ""')
                ->distinct()
                ->orderBy('profession')
                ->pluck('profession')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();

            if ($existingProfessionNames->isNotEmpty()) {
                $startOrder = (int) DB::table('professions')->max('sort_order');
                $extras = $existingProfessionNames->values()->map(function (string $name, int $index) use ($now, $startOrder) {
                    return [
                        'name' => $name,
                        'description' => null,
                        'sort_order' => $startOrder + $index + 1,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DB::table('professions')->upsert(
                    $extras,
                    ['name'],
                    ['updated_at']
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('professions');
    }
};

