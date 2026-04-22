<?php

use App\Http\Controllers\ManagedResourceController;
use App\Services\LegacyHilSqlPreparationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('organizations:import-hierarchy {path : Absolute path to the CSV file}', function (string $path) {
    if (! File::exists($path)) {
        $this->error('CSV file not found: '.$path);

        return self::FAILURE;
    }

    $controller = app(ManagedResourceController::class);

    try {
        $before = [
            'regions' => DB::table('regions')->count(),
            'zones' => DB::table('zones')->count(),
            'woredas' => DB::table('woredas')->count(),
            'organizations' => DB::table('organizations')->count(),
        ];

        $result = $controller->importOrganizationsFromCsv($path);

        $after = [
            'regions' => DB::table('regions')->count(),
            'zones' => DB::table('zones')->count(),
            'woredas' => DB::table('woredas')->count(),
            'organizations' => DB::table('organizations')->count(),
        ];
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return self::FAILURE;
    }

    $this->info('Organization hierarchy import completed.');
    $this->line('Organizations: '.$result['created'].' created, '.$result['updated'].' updated, '.$result['skipped'].' skipped.');
    $this->line('Regions added: '.($after['regions'] - $before['regions']));
    $this->line('Zones added: '.($after['zones'] - $before['zones']));
    $this->line('Woredas added: '.($after['woredas'] - $before['woredas']));
    $this->line('Organizations added: '.($after['organizations'] - $before['organizations']));

    if (! empty($result['errors'])) {
        $previewErrors = array_slice($result['errors'], 0, 10);
        $this->newLine();
        $this->warn('First import issues:');
        foreach ($previewErrors as $error) {
            $this->line('- '.$error);
        }

        if (count($result['errors']) > count($previewErrors)) {
            $this->line('... and '.(count($result['errors']) - count($previewErrors)).' more issue(s).');
        }
    }

    return self::SUCCESS;
})->purpose('Import region, zone, woreda, and organization hierarchy from a CSV file');

Artisan::command('hil:prepare-legacy-import {path : Absolute path to the legacy HIL SQL dump} {--output= : Optional absolute output directory}', function (string $path) {
    if (! File::exists($path)) {
        $this->error('SQL file not found: '.$path);

        return self::FAILURE;
    }

    try {
        $result = app(LegacyHilSqlPreparationService::class)->prepare(
            $path,
            $this->option('output') ?: null
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return self::FAILURE;
    }

    $this->info('Legacy HIL data preparation completed.');
    $this->line('Output directory: '.$result['output_directory']);
    $this->line('Organization rows ready: '.$result['prepared_counts']['organization_rows_ready']);
    $this->line('Participant rows ready: '.$result['prepared_counts']['participants_ready']);
    $this->line('Participant rows needing review: '.$result['prepared_counts']['participants_review']);
    $this->line('Training-event staging rows: '.$result['prepared_counts']['training_events_staging']);
    $this->line('Capstone-project staging rows: '.$result['prepared_counts']['capstone_projects_staging']);

    return self::SUCCESS;
})->purpose('Analyze a legacy HIL SQL dump and generate upload-ready CSVs plus staging files');
