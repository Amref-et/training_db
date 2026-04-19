<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participants')) {
            return;
        }

        if (! Schema::hasColumn('participants', 'participant_code')) {
            Schema::table('participants', function (Blueprint $table) {
                $table->string('participant_code', 32)->nullable()->after('name');
            });
        }

        DB::table('participants')
            ->select(['id', 'first_name', 'father_name', 'grandfather_name', 'date_of_birth', 'mobile_phone', 'participant_code'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if (! empty($row->participant_code)) {
                        continue;
                    }

                    $firstInitial = $this->initial($row->first_name);
                    $fatherInitial = $this->initial($row->father_name);
                    $grandfatherInitial = $this->initial($row->grandfather_name);
                    [$year, $month] = $this->yearMonth($row->date_of_birth);
                    $last4 = $this->last4Digits($row->mobile_phone);

                    $base = $firstInitial.$fatherInitial.$grandfatherInitial.$year.$month.$last4;
                    $code = $base;
                    $counter = 1;

                    while (
                        DB::table('participants')
                            ->where('participant_code', $code)
                            ->where('id', '<>', $row->id)
                            ->exists()
                    ) {
                        $code = $base.str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
                        $counter++;
                    }

                    DB::table('participants')
                        ->where('id', $row->id)
                        ->update(['participant_code' => $code]);
                }
            }, 'id');

        DB::statement('ALTER TABLE participants MODIFY participant_code VARCHAR(32) NOT NULL');

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'participants')
            ->where('index_name', 'participants_participant_code_unique')
            ->exists();

        if (! $indexExists) {
            Schema::table('participants', function (Blueprint $table) {
                $table->unique('participant_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('participants') || ! Schema::hasColumn('participants', 'participant_code')) {
            return;
        }

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'participants')
            ->where('index_name', 'participants_participant_code_unique')
            ->exists();

        Schema::table('participants', function (Blueprint $table) use ($indexExists) {
            if ($indexExists) {
                $table->dropUnique('participants_participant_code_unique');
            }

            $table->dropColumn('participant_code');
        });
    }

    private function initial(mixed $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return 'X';
        }

        return strtoupper(mb_substr($text, 0, 1));
    }

    private function yearMonth(mixed $dobValue): array
    {
        if (empty($dobValue)) {
            return ['0000', '00'];
        }

        try {
            $dob = Carbon::parse((string) $dobValue);
        } catch (\Throwable) {
            return ['0000', '00'];
        }

        return [$dob->format('Y'), $dob->format('m')];
    }

    private function last4Digits(mixed $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $tail = substr($digits, -4);

        return str_pad((string) $tail, 4, '0', STR_PAD_LEFT);
    }
};

