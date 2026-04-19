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

        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasColumn('participants', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('participants', 'father_name')) {
                $table->string('father_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('participants', 'grandfather_name')) {
                $table->string('grandfather_name')->nullable()->after('father_name');
            }
            if (! Schema::hasColumn('participants', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('grandfather_name');
            }
            if (! Schema::hasColumn('participants', 'age')) {
                $table->unsignedTinyInteger('age')->nullable()->after('date_of_birth');
            }
        });

        DB::table('participants')
            ->select(['id', 'name', 'first_name', 'father_name', 'grandfather_name', 'date_of_birth', 'age'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $reference = Carbon::create(now()->year, 7, 1)->startOfDay();

                foreach ($rows as $row) {
                    $updates = [];

                    $firstName = trim((string) ($row->first_name ?? ''));
                    $fatherName = trim((string) ($row->father_name ?? ''));
                    $grandfatherName = trim((string) ($row->grandfather_name ?? ''));
                    $fullName = trim((string) ($row->name ?? ''));

                    if ($fullName !== '' && $firstName === '' && $fatherName === '' && $grandfatherName === '') {
                        $parts = preg_split('/\s+/', $fullName) ?: [];
                        $updates['first_name'] = $parts[0] ?? null;
                        $updates['father_name'] = $parts[1] ?? null;
                        $updates['grandfather_name'] = count($parts) > 2 ? implode(' ', array_slice($parts, 2)) : null;
                        $firstName = (string) ($updates['first_name'] ?? '');
                        $fatherName = (string) ($updates['father_name'] ?? '');
                        $grandfatherName = (string) ($updates['grandfather_name'] ?? '');
                    }

                    if ($fullName === '') {
                        $recomposed = trim(implode(' ', array_values(array_filter([$firstName, $fatherName, $grandfatherName]))));
                        if ($recomposed !== '') {
                            $updates['name'] = $recomposed;
                        }
                    }

                    if (! empty($row->date_of_birth)) {
                        $dob = Carbon::parse($row->date_of_birth)->startOfDay();
                        $age = $dob->diffInYears($reference);
                        if ($row->age === null || (int) $row->age !== $age) {
                            $updates['age'] = $age;
                        }
                    } elseif ($row->age !== null) {
                        $year = now()->year - (int) $row->age;
                        $updates['date_of_birth'] = Carbon::create($year, 7, 1)->toDateString();
                    }

                    if ($updates !== []) {
                        DB::table('participants')->where('id', $row->id)->update($updates);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('participants')) {
            return;
        }

        Schema::table('participants', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['first_name', 'father_name', 'grandfather_name', 'date_of_birth', 'age'] as $column) {
                if (Schema::hasColumn('participants', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

