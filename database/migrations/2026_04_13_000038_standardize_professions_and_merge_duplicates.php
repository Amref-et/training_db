<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participants') || ! Schema::hasColumn('participants', 'profession')) {
            return;
        }

        $standardProfessions = [
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

        $standardByNormalized = [];
        foreach ($standardProfessions as $name) {
            $standardByNormalized[$this->normalize($name)] = $name;
        }

        $aliasMap = [
            'accountant' => 'Finance related',
            'accounting' => 'Finance related',
            'ba business manegement' => 'Administration/Management related',
            'ba business management' => 'Administration/Management related',
            'ba in management' => 'Administration/Management related',
            'bsc' => 'Other (specify)',
            'bsc economics' => 'Finance related',
            'bsc environmental health' => 'Environmental health related',
            'bsc hit' => 'SI related (M&E, Surveillance, IT)',
            'bsc mathematics' => 'Other (specify)',
            'bsc midwif' => 'Midwife',
            'bsc midwifery' => 'Midwife',
            'bsc nurse' => 'Nurse',
            'c/n' => 'Nurse',
            'clinical midwifery' => 'Midwife',
            'clinical nurse' => 'Nurse',
            'emergency' => 'Other (specify)',
            'emergency focal' => 'Public Health/Program related',
            'enviromental health officer' => 'Environmental health related',
            'epi focal' => 'Public Health/Program related',
            'fp focal' => 'Public Health/Program related',
            'general mph' => 'Public Health/Program related',
            'general practitioner' => 'Physician',
            'health information technician' => 'SI related (M&E, Surveillance, IT)',
            'hep officer' => 'Health Extension Worker',
            'hew' => 'Health Extension Worker',
            'hit' => 'SI related (M&E, Surveillance, IT)',
            'hiv officer' => 'Public Health/Program related',
            'hmis focal' => 'SI related (M&E, Surveillance, IT)',
            'ho' => 'Health Officer',
            'hrm' => 'Administration/Management related',
            'ict' => 'SI related (M&E, Surveillance, IT)',
            'laboratory' => 'Laboratory Professional',
            'm. laboratory technician' => 'Laboratory Professional',
            'malaria officer' => 'Public Health/Program related',
            'master of economics' => 'Finance related',
            'mba' => 'Administration/Management related',
            'mch' => 'Public Health/Program related',
            'mch focal' => 'Public Health/Program related',
            'mch nutrition focal' => 'Public Health/Program related',
            'md' => 'Physician',
            'medical doctor' => 'Physician',
            'mph' => 'Public Health/Program related',
            'mph in health economics' => 'Public Health/Program related',
            'mph in nutrition' => 'Public Health/Program related',
            'mph nutrition' => 'Public Health/Program related',
            'mph-nutrition' => 'Public Health/Program related',
            'opd' => 'Other (specify)',
            'other (mba)' => 'Other (specify)',
            'other (specify in next )field' => 'Other (specify)',
            'other (specify in next)field' => 'Other (specify)',
            'other specify in next field' => 'Other (specify)',
            'other' => 'Other (specify)',
            'pharmacy' => 'Pharmacy professional',
            'pharmacy technician' => 'Pharmacy professional',
            'public health officer' => 'Public Health/Program related',
            'sociology' => 'Sociology/Psychology related',
            'team lead' => 'Administration/Management related',
            'woho head' => 'Health Officer',
            'woreda health office' => 'Health Officer',
        ];

        DB::table('participants')
            ->select(['id', 'profession'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($aliasMap, $standardByNormalized): void {
                foreach ($rows as $row) {
                    $raw = trim((string) ($row->profession ?? ''));
                    $normalized = $this->normalize($raw);

                    if ($normalized === '') {
                        $target = 'Other (specify)';
                    } elseif (isset($aliasMap[$normalized])) {
                        $target = $aliasMap[$normalized];
                    } elseif (isset($standardByNormalized[$normalized])) {
                        $target = $standardByNormalized[$normalized];
                    } else {
                        $target = 'Other (specify)';
                    }

                    if ($target !== $row->profession) {
                        DB::table('participants')
                            ->where('id', $row->id)
                            ->update([
                                'profession' => $target,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        if (! Schema::hasTable('professions')) {
            return;
        }

        $now = now();
        $payload = collect($standardProfessions)
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

        DB::table('professions')->upsert(
            $payload,
            ['name'],
            ['sort_order', 'is_active', 'updated_at']
        );

        DB::table('professions')
            ->whereNotIn('name', $standardProfessions)
            ->delete();
    }

    public function down(): void
    {
        // Irreversible data standardization migration.
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
};

