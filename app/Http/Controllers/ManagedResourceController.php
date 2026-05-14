<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Region;
use App\Models\TrainingOrganizer;
use App\Models\Woreda;
use App\Models\Zone;
use App\Support\ResourceRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagedResourceController extends Controller
{
    private const CSV_ORGANIZATION_DEFAULT_CATEGORY = 'Private';

    private const CSV_ORGANIZATION_DEFAULT_TYPE = 'Other (specify)';

    private const ORGANIZATION_IMPORT_TEMPLATE_HEADERS = [
        'region_id',
        'region_name',
        'zone_id',
        'zone_name',
        'woreda_id',
        'woreda_name',
        'organization_id',
        'organization',
        'category',
        'type',
        'city_town',
        'phone',
        'fax',
    ];

    private const TRAINING_SUMMARY_IMPORT_TEMPLATE_HEADERS = [
        'Region',
        'Training Title',
        'Training Start Date',
        'Category',
        'Training End Date',
        'Training Organizer',
        'Participant Name',
        'Participant ID',
        'Gender',
        'Mobile Phone',
        'Participant Email',
        'Participant Profession',
        'Participant Organisation Name',
        'Participant Organisation Phone',
        'Participant Organisation Fax',
        'Participant Organisation Category',
        'Participant Organisation Type',
        'Pre-Training Assessment Score',
        'Mid-Training Assessment Score',
        'Post-Training Assessment Score',
        'Trainer/Training organizer Name',
        'Comments/Evaluation regarding this participant',
    ];

    private const ORGANIZATION_IMPORT_REPORT_DIRECTORY = 'organization-import-reports';

    private const ORGANIZATION_IMPORT_MODE_UPDATE = 'update';

    private const ORGANIZATION_IMPORT_MODE_OVERWRITE = 'overwrite';

    public function index(Request $request, string $resource): View
    {
        $config = ResourceRegistry::get($resource);
        $modelClass = $config['model'];
        if ($resource === 'training_events') {
            return $this->trainingEventsGroupedIndex($request, $config);
        }

        $query = $this->buildConfiguredQuery($modelClass, $config);
        $this->applySearch($query, $config, $request->string('q')->toString());

        $records = $query
            ->orderBy($config['order_by'] ?? $modelClass::make()->getKeyName(), $config['order_direction'] ?? 'asc')
            ->paginate(12)
            ->withQueryString();

        return view('admin.resources.index', [
            'config' => $config,
            'resource' => $resource,
            'records' => $records,
            'query' => $request->string('q')->toString(),
        ]);
    }

    private function trainingEventsGroupedIndex(Request $request, array $config): View
    {
        $searchTerm = $request->string('q')->toString();

        $eventScores = DB::table('training_event_participants as tep')
            ->selectRaw('tep.training_event_id, COUNT(*) as participants_count, AVG(tep.final_score) as avg_event_final')
            ->groupBy('tep.training_event_id');

        $groupQuery = DB::table('training_events as te')
            ->leftJoinSub($eventScores, 'es', fn ($join) => $join->on('es.training_event_id', '=', 'te.id'))
            ->leftJoin('trainings as t', 't.id', '=', 'te.training_id')
            ->leftJoin('training_organizers as o', 'o.id', '=', 'te.training_organizer_id')
            ->leftJoin('project_subawardees as psa', 'psa.id', '=', 'te.project_subawardee_id')
            ->selectRaw("
                LOWER(TRIM(COALESCE(te.event_name, ''))) as event_name_key,
                COALESCE(te.training_id, 0) as training_id,
                COALESCE(te.training_organizer_id, 0) as training_organizer_id,
                COALESCE(te.organizer_type, 'The project') as organizer_type,
                COALESCE(te.project_subawardee_id, 0) as project_subawardee_id,
                MAX(COALESCE(te.event_name, '-')) as event_name,
                MAX(COALESCE(t.title, '-')) as training_title,
                MAX(COALESCE(o.project_name, o.title, '-')) as organizer_title,
                MAX(
                    CASE
                        WHEN COALESCE(te.organizer_type, 'The project') = 'Subawardee'
                            THEN COALESCE(psa.subawardee_name, '-')
                        ELSE COALESCE(o.project_name, o.title, '-')
                    END
                ) as organized_by_title,
                COUNT(*) as events_count,
                COALESCE(SUM(COALESCE(es.participants_count, 0)), 0) as participants_total,
                AVG(es.avg_event_final) as avg_final_score,
                MIN(te.start_date) as start_date_min,
                MAX(te.end_date) as end_date_max
            ")
            ->groupByRaw("LOWER(TRIM(COALESCE(te.event_name, ''))), COALESCE(te.training_id, 0), COALESCE(te.training_organizer_id, 0), COALESCE(te.organizer_type, 'The project'), COALESCE(te.project_subawardee_id, 0)");

        $this->applyGroupedTrainingEventSearch($groupQuery, $config, $searchTerm);

        $records = $groupQuery
            ->orderByDesc('start_date_min')
            ->paginate(12)
            ->withQueryString();

        $groupKeys = collect($records->items())
            ->map(fn ($row) => [
                'event_name_key' => (string) ($row->event_name_key ?? ''),
                'training_id' => (int) ($row->training_id ?? 0),
                'training_organizer_id' => (int) ($row->training_organizer_id ?? 0),
                'organizer_type' => (string) ($row->organizer_type ?? 'The project'),
                'project_subawardee_id' => (int) ($row->project_subawardee_id ?? 0),
            ]);

        $eventsByGroupKey = collect();
        if ($groupKeys->isNotEmpty()) {
            $detailQuery = $this->buildConfiguredQuery($config['model'], $config);
            $detailQuery->where(function (Builder $builder) use ($groupKeys) {
                foreach ($groupKeys as $key) {
                    $builder->orWhere(function (Builder $subQuery) use ($key) {
                        $subQuery
                            ->whereRaw("LOWER(TRIM(COALESCE(event_name, ''))) = ?", [$key['event_name_key']])
                            ->where('training_id', $key['training_id'])
                            ->where('training_organizer_id', $key['training_organizer_id'])
                            ->whereRaw("COALESCE(organizer_type, 'The project') = ?", [$key['organizer_type']])
                            ->whereRaw('COALESCE(project_subawardee_id, 0) = ?', [$key['project_subawardee_id']]);
                    });
                }
            });

            $eventsByGroupKey = $detailQuery
                ->orderBy($config['order_by'] ?? 'created_at', $config['order_direction'] ?? 'desc')
                ->get()
                ->groupBy(fn ($event) => $this->trainingEventGroupKey(
                    (string) ($event->event_name ?? ''),
                    (int) ($event->training_id ?? 0),
                    (int) ($event->training_organizer_id ?? 0),
                    (string) ($event->organizer_type ?? 'The project'),
                    (int) ($event->project_subawardee_id ?? 0)
                ));
        }

        $records->setCollection(
            $records->getCollection()->map(function ($groupRow) use ($eventsByGroupKey) {
                $groupKey = $this->trainingEventGroupKey(
                    (string) ($groupRow->event_name_key ?? ''),
                    (int) ($groupRow->training_id ?? 0),
                    (int) ($groupRow->training_organizer_id ?? 0),
                    (string) ($groupRow->organizer_type ?? 'The project'),
                    (int) ($groupRow->project_subawardee_id ?? 0)
                );
                $events = $eventsByGroupKey->get($groupKey, collect())->values();

                return [
                    'group_key' => sha1($groupKey),
                    'event_name' => (string) ($groupRow->event_name ?? '-'),
                    'training_title' => (string) ($groupRow->training_title ?? '-'),
                    'organizer_title' => (string) ($groupRow->organizer_title ?? '-'),
                    'organized_by' => (string) ($groupRow->organized_by_title ?? '-'),
                    'events_count' => (int) ($groupRow->events_count ?? 0),
                    'participants_total' => (int) ($groupRow->participants_total ?? 0),
                    'avg_final_score' => $groupRow->avg_final_score !== null ? round((float) $groupRow->avg_final_score, 1) : null,
                    'start_date_min' => $groupRow->start_date_min,
                    'end_date_max' => $groupRow->end_date_max,
                    'statuses' => $events->pluck('status')->filter()->unique()->values()->all(),
                    'events' => $events,
                ];
            })
        );

        return view('admin.training-events.index', [
            'config' => $config,
            'resource' => 'training_events',
            'records' => $records,
            'query' => $searchTerm,
        ]);
    }

    private function trainingEventGroupKey(string $eventName, int $trainingId, int $organizerId, string $organizerType = 'The project', int $projectSubawardeeId = 0): string
    {
        return mb_strtolower(trim($eventName)).'|'.$trainingId.'|'.$organizerId.'|'.mb_strtolower(trim($organizerType)).'|'.$projectSubawardeeId;
    }

    private function buildConfiguredQuery(string $modelClass, array $config): Builder
    {
        $query = $modelClass::query()->with($config['eager'] ?? []);

        if (! empty($config['with_count'])) {
            $query->withCount($config['with_count']);
        }

        if (! empty($config['with_avg'])) {
            foreach ($config['with_avg'] as $averageConfig) {
                $relation = $averageConfig['relation'] ?? null;
                $column = $averageConfig['column'] ?? null;
                $alias = $averageConfig['as'] ?? null;

                if (! $relation || ! $column) {
                    continue;
                }

                if ($alias) {
                    $query->withAvg($relation.' as '.$alias, $column);
                    continue;
                }

                $query->withAvg($relation, $column);
            }
        }

        return $query;
    }

    public function create(string $resource): View
    {
        $config = ResourceRegistry::get($resource);

        return view('admin.resources.form', [
            'config' => $config,
            'resource' => $resource,
            'record' => null,
            'fieldOptions' => $this->fieldOptions($config),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $config = ResourceRegistry::get($resource);
        $modelClass = $config['model'];

        $data = $this->preparePayload($request, $config);
        $this->applyHierarchyConstraints($resource, $data);
        $relationPayload = $this->extractRelationPayload($data, $config);

        $model = $modelClass::create($data);
        $this->syncRelations($model, $relationPayload);
        $model->refresh();
        $this->audit()->logModelCreated($model, $config['singular'].' created', [
            'resource' => $resource,
            'relations' => $relationPayload,
        ]);

        return redirect()->route('admin.'.$config['path'].'.index')->with('success', $config['singular'].' created successfully.');
    }

    public function edit(string $resource, string $record): View
    {
        $config = ResourceRegistry::get($resource);
        $model = $this->findRecord($config, $record);

        return view('admin.resources.form', [
            'config' => $config,
            'resource' => $resource,
            'record' => $model,
            'fieldOptions' => $this->fieldOptions($config, $model),
        ]);
    }

    public function participantOrganizationOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && (
                $user->hasPermission('participants.view')
                || $user->hasPermission('participants.create')
                || $user->hasPermission('participants.update')
            ),
            403
        );

        $queryTerm = trim($request->string('q')->toString());
        $selectedId = $this->nullableInt($request->input('selected_id'));
        $regionId = $this->nullableInt($request->input('region_id'));
        $zoneId = $this->nullableInt($request->input('zone_id'));
        $woredaId = $this->nullableInt($request->input('woreda_id'));

        $query = Organization::query()
            ->select(['id', 'name', 'region_id', 'zone_id', 'woreda_id'])
            ->with('region:id,name');

        if ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        if ($zoneId !== null) {
            $query->where('zone_id', $zoneId);
        }

        if ($woredaId !== null) {
            $query->where('woreda_id', $woredaId);
        }

        if ($queryTerm !== '') {
            $query->where('name', 'like', '%'.$queryTerm.'%');
        }

        $limit = $queryTerm !== '' ? 50 : (($regionId !== null || $zoneId !== null || $woredaId !== null) ? 50 : 20);
        $options = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($selectedId !== null && ! $options->contains('id', $selectedId)) {
            $selected = Organization::query()
                ->select(['id', 'name', 'region_id', 'zone_id', 'woreda_id'])
                ->with('region:id,name')
                ->find($selectedId);

            if ($selected) {
                $options->prepend($selected);
            }
        }

        return response()->json([
            'options' => $options
                ->unique('id')
                ->values()
                ->map(fn (Organization $organization) => $this->formatSelectOption($organization, 'name', 'id', [
                    'format_label' => 'organization_with_region',
                ]))
                ->all(),
        ]);
    }

    public function participantZoneOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && (
                $user->hasPermission('participants.view')
                || $user->hasPermission('participants.create')
                || $user->hasPermission('participants.update')
            ),
            403
        );

        $queryTerm = trim($request->string('q')->toString());
        $selectedId = $this->nullableInt($request->input('selected_id'));
        $regionId = $this->nullableInt($request->input('region_id'));

        if ($regionId === null && $selectedId === null) {
            return response()->json(['options' => []]);
        }

        $query = Zone::query()->select(['id', 'name', 'region_id']);

        if ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        if ($queryTerm !== '') {
            $query->where('name', 'like', '%'.$queryTerm.'%');
        }

        $options = $query
            ->orderBy('name')
            ->limit(50)
            ->get();

        if ($selectedId !== null && ! $options->contains('id', $selectedId)) {
            $selected = Zone::query()
                ->select(['id', 'name', 'region_id'])
                ->find($selectedId);

            if ($selected && ($regionId === null || (int) $selected->region_id === $regionId)) {
                $options->prepend($selected);
            }
        }

        return response()->json([
            'options' => $options
                ->unique('id')
                ->values()
                ->map(fn (Zone $zone) => $this->formatSelectOption($zone, 'name', 'id'))
                ->all(),
        ]);
    }

    public function participantWoredaOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && (
                $user->hasPermission('participants.view')
                || $user->hasPermission('participants.create')
                || $user->hasPermission('participants.update')
            ),
            403
        );

        $queryTerm = trim($request->string('q')->toString());
        $selectedId = $this->nullableInt($request->input('selected_id'));
        $regionId = $this->nullableInt($request->input('region_id'));
        $zoneId = $this->nullableInt($request->input('zone_id'));

        if ($zoneId === null && $selectedId === null) {
            return response()->json(['options' => []]);
        }

        $query = Woreda::query()->select(['id', 'name', 'region_id', 'zone_id']);

        if ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        if ($zoneId !== null) {
            $query->where('zone_id', $zoneId);
        }

        if ($queryTerm !== '') {
            $query->where('name', 'like', '%'.$queryTerm.'%');
        }

        $options = $query
            ->orderBy('name')
            ->limit(50)
            ->get();

        if ($selectedId !== null && ! $options->contains('id', $selectedId)) {
            $selected = Woreda::query()
                ->select(['id', 'name', 'region_id', 'zone_id'])
                ->find($selectedId);

            if (
                $selected
                && ($regionId === null || (int) $selected->region_id === $regionId)
                && ($zoneId === null || (int) $selected->zone_id === $zoneId)
            ) {
                $options->prepend($selected);
            }
        }

        return response()->json([
            'options' => $options
                ->unique('id')
                ->values()
                ->map(fn (Woreda $woreda) => $this->formatSelectOption($woreda, 'name', 'id'))
                ->all(),
        ]);
    }

    public function participantSearchOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && (
                $user->hasPermission('participants.view')
                || $user->hasPermission('participants.create')
                || $user->hasPermission('participants.update')
            ),
            403
        );

        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['options' => []]);
        }

        $participants = Participant::query()
            ->select(['id', 'name', 'first_name', 'father_name', 'grandfather_name', 'mobile_phone'])
            ->where(function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query
                    ->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('father_name', 'like', $like)
                    ->orWhere('grandfather_name', 'like', $like);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'options' => $participants
                ->map(fn (Participant $participant): array => [
                    'value' => $participant->id,
                    'label' => $this->participantDisplayLabel($participant),
                    'hint' => $this->phoneHint($participant->mobile_phone),
                    'mobile_phone' => (string) $participant->mobile_phone,
                ])
                ->values()
                ->all(),
        ]);
    }

    private function participantDisplayLabel(Participant $participant): string
    {
        $name = trim((string) $participant->name);

        if ($name !== '') {
            return $name;
        }

        $nameParts = array_filter([
            $participant->first_name,
            $participant->father_name,
            $participant->grandfather_name,
        ]);

        return trim(implode(' ', $nameParts)) ?: 'Participant #'.$participant->id;
    }

    private function phoneHint(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits === ''
            ? 'Registered phone unavailable'
            : 'Registered phone ending '.substr($digits, -4);
    }

    public function update(Request $request, string $resource, string $record): RedirectResponse
    {
        $config = ResourceRegistry::get($resource);
        $model = $this->findRecord($config, $record);
        $beforeState = $this->audit()->snapshotModel($model);

        $data = $this->preparePayload($request, $config, $model);
        $this->applyHierarchyConstraints($resource, $data);
        $relationPayload = $this->extractRelationPayload($data, $config);

        $model->update($data);
        $this->syncRelations($model, $relationPayload);
        $model->refresh();
        $this->audit()->logModelUpdated($model, $beforeState, $config['singular'].' updated', [
            'resource' => $resource,
            'relations' => $relationPayload,
        ]);

        return redirect()->route('admin.'.$config['path'].'.index')->with('success', $config['singular'].' updated successfully.');
    }

    public function destroy(string $resource, string $record): RedirectResponse
    {
        $config = ResourceRegistry::get($resource);
        $model = $this->findRecord($config, $record);
        $beforeState = $this->audit()->snapshotModel($model);
        $modelKey = $model->getKey();
        $modelClass = get_class($model);
        $modelLabel = trim((string) ($beforeState['project_name'] ?? $beforeState['event_name'] ?? $beforeState['title'] ?? $beforeState['name'] ?? $beforeState['email'] ?? ''));

        $this->deleteAttachedFiles($config, $model);
        $model->delete();
        $this->audit()->logModelDeleted($modelClass, $modelKey, $modelLabel !== '' ? $modelLabel : $config['singular'].' #'.$modelKey, $beforeState, $config['singular'].' deleted', [
            'resource' => $resource,
        ]);

        return redirect()->route('admin.'.$config['path'].'.index')->with('success', $config['singular'].' deleted successfully.');
    }

    public function downloadFile(string $resource, string $record, string $field)
    {
        $config = ResourceRegistry::get($resource);
        $model = $this->findRecord($config, $record);
        $fileField = $this->fileFieldConfig($config, $field);

        abort_unless($fileField !== null, 404);

        $path = (string) data_get($model, $field, '');
        abort_if($path === '', 404);

        $disk = $fileField['disk'] ?? 'public';
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, basename($path));
    }

    public function exportTrainingOrganizers(): StreamedResponse
    {
        $fileName = 'training-organizers-export-'.now()->format('Ymd-His').'.csv';
        $this->audit()->logCustom('Projects exported', 'training_organizers.export', [
            'auditable_type' => TrainingOrganizer::class,
            'metadata' => [
                'exported_records' => TrainingOrganizer::query()->count(),
                'file_name' => $fileName,
            ],
        ]);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'project_code',
                'project_name',
                'project_long_name',
                'donor',
                'program',
                'status',
                'subawardees',
            ]);

            TrainingOrganizer::query()
                ->with('subawardees')
                ->orderBy('id')
                ->chunkById(500, function ($organizers) use ($handle) {
                    foreach ($organizers as $organizer) {
                        fputcsv($handle, [
                            (string) $organizer->project_code,
                            (string) $organizer->project_name,
                            (string) $organizer->project_long_name,
                            (string) $organizer->donor,
                            (string) $organizer->program,
                            $organizer->is_active ? 'Active' : 'Inactive',
                            $organizer->subawardees
                                ->pluck('subawardee_name')
                                ->filter()
                                ->implode('; '),
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importTrainingOrganizers(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $path = $validated['import_file']->getRealPath();

        try {
            $result = $this->importTrainingOrganizersFromCsv((string) $path);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->audit()->logCustom('Projects imported', 'training_organizers.import', [
            'auditable_type' => TrainingOrganizer::class,
            'metadata' => $result,
        ]);

        $successMessage = 'Project import completed: '.$result['created'].' created, '.$result['updated'].' updated';
        if ($result['skipped'] > 0) {
            $successMessage .= ', '.$result['skipped'].' skipped.';
        } else {
            $successMessage .= '.';
        }

        $redirect = back()->with('success', $successMessage);

        if (! empty($result['errors'])) {
            $previewErrors = array_slice($result['errors'], 0, 8);
            $moreCount = max(0, count($result['errors']) - count($previewErrors));
            $message = implode(' ', $previewErrors);
            if ($moreCount > 0) {
                $message .= ' ... and '.$moreCount.' more issue(s).';
            }
            $redirect->with('error', $message);
        }

        return $redirect;
    }

    public function importTrainingSummary(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $path = $validated['import_file']->getRealPath();
        $exitCode = Artisan::call('app:import-training-summary', ['--file' => $path]);
        $commandOutput = trim(Artisan::output());

        if ($exitCode !== 0) {
            return back()->with('error', 'Training summary import failed: ' . $commandOutput);
        }

        $successMessage = 'Training summary import completed.';
        if ($commandOutput !== '') {
            $successMessage .= ' ' . preg_replace('/\s+/', ' ', $commandOutput);
        }

        return back()->with('success', $successMessage);
    }

    public function importTrainingOrganizersFromCsv(string $path): array
    {
        $handle = $path !== '' ? fopen($path, 'r') : false;
        if ($handle === false) {
            throw new \RuntimeException('Unable to read import file.');
        }

        try {
            $headerRow = fgetcsv($handle);

            if (! is_array($headerRow) || empty($headerRow)) {
                throw new \RuntimeException('Invalid CSV file: missing header row.');
            }

            $headerMap = [];
            foreach ($headerRow as $index => $column) {
                $normalized = $this->normalizeCsvHeader((string) $column);
                if ($normalized !== '' && ! array_key_exists($normalized, $headerMap)) {
                    $headerMap[$normalized] = $index;
                }
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $line = 1;
            $errors = [];
            $seenProjectCodes = [];

            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->csvRowIsBlank($row)) {
                    continue;
                }

                $projectCode = $this->csvCell($row, $headerMap, ['project_code', 'project_id']);
                $projectName = $this->csvCell($row, $headerMap, ['project_name', 'project']);
                $projectLongName = $this->csvCell($row, $headerMap, ['project_long_name', 'long_name']);
                $donor = $this->csvCell($row, $headerMap, ['donor']);
                $program = $this->csvCell($row, $headerMap, ['program']);
                $statusRaw = $this->csvCell($row, $headerMap, ['status', 'is_active', 'active']);
                $status = $this->normalizeCsvBoolean($statusRaw);
                $subawardees = $this->csvTrainingOrganizerSubawardees($row, $headerMap);

                $rowErrors = [];

                if ($projectCode === '') {
                    $rowErrors[] = 'Project code is required.';
                } elseif (mb_strlen($projectCode) > 255) {
                    $rowErrors[] = 'Project code must not exceed 255 characters.';
                }

                if ($projectName === '') {
                    $rowErrors[] = 'Project name is required.';
                } elseif (mb_strlen($projectName) > 255) {
                    $rowErrors[] = 'Project name must not exceed 255 characters.';
                }

                foreach ([
                    'Project long name' => $projectLongName,
                    'Donor' => $donor,
                    'Program' => $program,
                ] as $label => $value) {
                    if (mb_strlen($value) > 255) {
                        $rowErrors[] = $label.' must not exceed 255 characters.';
                    }
                }

                foreach ($subawardees as $subawardee) {
                    if (mb_strlen($subawardee) > 255) {
                        $rowErrors[] = 'Subawardee must not exceed 255 characters.';
                        break;
                    }
                }

                if ($statusRaw !== '' && $status === null) {
                    $rowErrors[] = 'Status must be Active/Inactive, Yes/No, or 1/0.';
                }

                $projectCodeKey = mb_strtolower($projectCode);
                if ($projectCodeKey !== '' && isset($seenProjectCodes[$projectCodeKey])) {
                    $rowErrors[] = 'Project code is duplicated in this import file.';
                }

                if (! empty($rowErrors)) {
                    $skipped++;
                    $errors[] = 'Line '.$line.': '.implode(' ', $rowErrors);
                    continue;
                }

                $seenProjectCodes[$projectCodeKey] = true;

                $payload = [
                    'project_code' => $projectCode,
                    'project_name' => $projectName,
                    'project_long_name' => $projectLongName !== '' ? $projectLongName : null,
                    'donor' => $donor !== '' ? $donor : null,
                    'program' => $program !== '' ? $program : null,
                ];

                if ($status !== null) {
                    $payload['is_active'] = $status;
                }

                $organizer = TrainingOrganizer::query()
                    ->whereRaw('LOWER(project_code) = ?', [$projectCodeKey])
                    ->first();

                if ($organizer) {
                    $organizer->update($payload);
                    $updated++;
                } else {
                    $organizer = TrainingOrganizer::query()->create($payload);
                    $created++;
                }

                $this->syncTrainingOrganizerSubawardees($organizer, $subawardees);
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } finally {
            fclose($handle);
        }
    }

    public function exportOrganizations(): StreamedResponse
    {
        $fileName = 'organizations-export-'.now()->format('Ymd-His').'.csv';
        $this->audit()->logCustom('Organizations exported', 'organizations.export', [
            'auditable_type' => Organization::class,
            'metadata' => [
                'exported_records' => Organization::query()->count(),
                'file_name' => $fileName,
            ],
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ];

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS);

            Organization::query()
                ->with(['region', 'zoneDefinition', 'woreda'])
                ->orderBy('id')
                ->chunkById(500, function ($organizations) use ($handle) {
                    foreach ($organizations as $organization) {
                        $zoneName = (string) data_get($organization, 'zoneDefinition.name', (string) $organization->zone);
                        $regionExternalId = (string) (data_get($organization, 'region.external_id') ?: $organization->region_id);
                        $zoneExternalId = (string) (data_get($organization, 'zoneDefinition.external_id') ?: $organization->zone_id);
                        $woredaExternalId = (string) (data_get($organization, 'woreda.external_id') ?: $organization->woreda_id);
                        fputcsv($handle, [
                            $regionExternalId,
                            (string) data_get($organization, 'region.name', ''),
                            $zoneExternalId,
                            $zoneName,
                            $woredaExternalId,
                            (string) data_get($organization, 'woreda.name', ''),
                            (string) ($organization->external_id ?: $organization->id),
                            (string) $organization->name,
                            (string) $organization->category,
                            (string) $organization->type,
                            (string) $organization->city_town,
                            (string) $organization->phone,
                            (string) $organization->fax,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, $headers);
    }

    public function downloadOrganizationImportTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS);
            fclose($handle);
        }, 'organizations-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadTrainingSummaryTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::TRAINING_SUMMARY_IMPORT_TEMPLATE_HEADERS);
            fclose($handle);
        }, 'training-summary-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadOrganizationImportReport(string $report): StreamedResponse
    {
        $fileName = basename($report);
        if (! preg_match('/^organizations-import-skipped-\d{8}-\d{6}-[A-Za-z0-9]{6}\.csv$/', $fileName)) {
            abort(404);
        }

        $path = self::ORGANIZATION_IMPORT_REPORT_DIRECTORY.'/'.$fileName;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importOrganizations(Request $request): RedirectResponse
    {
        set_time_limit(0);

        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'import_mode' => ['nullable', 'in:'.self::ORGANIZATION_IMPORT_MODE_UPDATE.','.self::ORGANIZATION_IMPORT_MODE_OVERWRITE],
        ]);
        $importMode = $validated['import_mode'] ?? self::ORGANIZATION_IMPORT_MODE_UPDATE;

        $config = ResourceRegistry::get('organizations');
        $categoryOptions = $this->choiceLookup($config, 'category');
        $typeOptions = $this->choiceLookup($config, 'type');

        $path = $validated['import_file']->getRealPath();
        try {
            $result = $this->importOrganizationsFromCsv((string) $path, $categoryOptions, $typeOptions, $importMode);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $report = null;
        if (! empty($result['skipped_rows'])) {
            $report = $this->writeOrganizationSkippedRowsReport($result['skipped_rows']);
        }

        $this->audit()->logCustom('Organizations imported', 'organizations.import', [
            'auditable_type' => Organization::class,
            'metadata' => [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'error_count' => count($result['errors']),
                'import_mode' => $importMode,
                'skipped_report_file' => $report['file_name'] ?? null,
            ],
        ]);

        $successMessage = 'Organization import completed: '.$result['created'].' created, '.$result['updated'].' updated';
        if ($result['skipped'] > 0) {
            $successMessage .= ', '.$result['skipped'].' skipped.';
        } else {
            $successMessage .= '.';
        }

        $redirect = back()->with('success', $successMessage);

        if ($report !== null) {
            $redirect
                ->with('warning', $result['skipped'].' organization row(s) were skipped and not created. Download the skipped rows CSV, correct the listed reason, and import again.')
                ->with('organization_import_report', $report);
        } elseif (! empty($result['errors'])) {
            $redirect->with('warning', implode(' ', array_slice($result['errors'], 0, 8)));
        }

        return $redirect;
    }

    public function importOrganizationsFromCsv(string $path, ?array $categoryOptions = null, ?array $typeOptions = null, string $importMode = self::ORGANIZATION_IMPORT_MODE_UPDATE): array
    {
        $categoryOptions ??= $this->choiceLookup(ResourceRegistry::get('organizations'), 'category');
        $typeOptions ??= $this->choiceLookup(ResourceRegistry::get('organizations'), 'type');
        $forceOverwrite = $importMode === self::ORGANIZATION_IMPORT_MODE_OVERWRITE;

        $handle = $path !== '' ? fopen($path, 'r') : false;
        if ($handle === false) {
            throw new \RuntimeException('Unable to read import file.');
        }

        try {
            $headerRow = fgetcsv($handle);

            if (! is_array($headerRow) || empty($headerRow)) {
                throw new \RuntimeException('Invalid CSV file: missing header row.');
            }

            $headerMap = [];
            foreach ($headerRow as $index => $column) {
                $normalized = $this->normalizeCsvHeader((string) $column);
                if ($normalized !== '' && ! array_key_exists($normalized, $headerMap)) {
                    $headerMap[$normalized] = $index;
                }
            }

            $regionsById = [];
            $regionsByExternalId = [];
            $regionsByName = [];
            foreach (Region::query()->get() as $region) {
                $regionsById[(int) $region->id] = $region;
                $regionExternalId = $this->normalizeExternalId(data_get($region, 'external_id'));
                if ($regionExternalId !== '') {
                    $regionsByExternalId[$regionExternalId] = $region;
                }
                $regionsByName[mb_strtolower(trim((string) $region->name))] = $region;
            }

            $zonesById = [];
            $zonesByExternalId = [];
            $zonesByName = [];
            $zonesByScopedKey = [];
            foreach (Zone::query()->get() as $zone) {
                $zonesById[(int) $zone->id] = $zone;
                $zoneExternalId = $this->normalizeExternalId(data_get($zone, 'external_id'));
                if ($zoneExternalId !== '') {
                    $zonesByExternalId[$zoneExternalId] = $zone;
                }
                $zoneKey = mb_strtolower(trim((string) $zone->name));
                $zonesByName[$zoneKey] = $zone;
                if ($zone->region_id !== null) {
                    $zonesByScopedKey[$zoneKey.'|r:'.(int) $zone->region_id] = $zone;
                }
            }

            $woredasById = [];
            $woredasByExternalScopedKey = [];
            $woredasByScopedKey = [];
            $woredasByName = [];
            foreach (Woreda::query()->get() as $woreda) {
                $woredasById[(int) $woreda->id] = $woreda;
                $nameKey = mb_strtolower(trim((string) $woreda->name));
                $woredaExternalId = $this->normalizeExternalId(data_get($woreda, 'external_id'));
                $woredasByName[$nameKey] ??= [];
                $woredasByName[$nameKey][] = $woreda;
                if ($woredaExternalId !== '' && $woreda->zone_id !== null) {
                    $woredasByExternalScopedKey[$woredaExternalId.'|z:'.(int) $woreda->zone_id] = $woreda;
                }
                if ($woredaExternalId !== '' && $woreda->region_id !== null) {
                    $woredasByExternalScopedKey[$woredaExternalId.'|r:'.(int) $woreda->region_id] = $woreda;
                }
                if ($woreda->zone_id !== null) {
                    $woredasByScopedKey[$nameKey.'|z:'.(int) $woreda->zone_id] = $woreda;
                }
                if ($woreda->region_id !== null) {
                    $woredasByScopedKey[$nameKey.'|r:'.(int) $woreda->region_id] = $woreda;
                }
            }

            $organizationsByExternalId = [];
            $organizationsByName = [];
            foreach (Organization::query()->get() as $organization) {
                $organizationExternalId = $this->normalizeExternalId(data_get($organization, 'external_id'));
                if ($organizationExternalId !== '') {
                    $organizationsByExternalId[$organizationExternalId] = $organization;
                }
                $organizationsByName[mb_strtolower(trim((string) $organization->name))] = $organization;
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $line = 1;
            $errors = [];
            $skippedRows = [];

            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->csvRowIsBlank($row)) {
                    continue;
                }

                $organizationIdRaw = $this->csvCell($row, $headerMap, ['organization_id', 'facility_id', 'mfr_id']);
                $organizationExternalId = $this->normalizeExternalId($organizationIdRaw);
                $name = $this->csvCell($row, $headerMap, ['organization', 'facility', 'name', 'organization_name', 'facility_name']);
                $rawCategory = $this->csvCell($row, $headerMap, ['category', 'facility_organization_category']);
                $rawType = $this->csvCell($row, $headerMap, ['type', 'organization_type']);
                $zoneIdRaw = $this->csvCell($row, $headerMap, ['zone_id']);
                $zoneName = $this->csvCell($row, $headerMap, ['zone_name', 'zone']);
                $cityTown = $this->csvCell($row, $headerMap, ['city_town', 'city', 'city_town_name']);
                $phone = $this->csvCell($row, $headerMap, ['phone', 'phone_number']);
                $fax = $this->csvCell($row, $headerMap, ['fax']);
                $regionIdRaw = $this->csvCell($row, $headerMap, ['region_id']);
                $regionName = $this->csvCell($row, $headerMap, ['region_name', 'region']);
                $woredaIdRaw = $this->csvCell($row, $headerMap, ['woreda_id']);
                $woredaName = $this->csvCell($row, $headerMap, ['woreda_name', 'woreda']);
                $reportRow = [
                    'region_id' => $regionIdRaw,
                    'region_name' => $regionName,
                    'zone_id' => $zoneIdRaw,
                    'zone_name' => $zoneName,
                    'woreda_id' => $woredaIdRaw,
                    'woreda_name' => $woredaName,
                    'organization_id' => $organizationIdRaw,
                    'organization' => $name,
                    'category' => $rawCategory,
                    'type' => $rawType,
                    'city_town' => $cityTown,
                    'phone' => $phone,
                    'fax' => $fax,
                ];

                $rowErrors = [];

                if ($name === '') {
                    $rowErrors[] = 'Organization name is required.';
                }

                $organizationKey = mb_strtolower(trim($name));
                $existing = null;
                if ($organizationExternalId !== '') {
                    $existing = $organizationsByExternalId[$organizationExternalId] ?? null;
                } elseif ($name !== '') {
                    $existing = $organizationsByName[$organizationKey] ?? null;
                }

                $category = $this->normalizeOrganizationCategory($rawCategory, $categoryOptions, $forceOverwrite ? null : $existing);
                $type = $this->normalizeOrganizationType($rawType, $typeOptions, $forceOverwrite ? null : $existing);

                $regionExternalId = $this->normalizeExternalId($regionIdRaw);
                $region = $regionExternalId !== '' ? ($regionsByExternalId[$regionExternalId] ?? null) : null;
                $regionKey = mb_strtolower(trim($regionName));
                $region ??= $regionKey !== '' ? ($regionsByName[$regionKey] ?? null) : null;
                $region ??= ($regionExternalId !== '' && ctype_digit($regionExternalId)) ? ($regionsById[(int) $regionExternalId] ?? null) : null;
                if ($region === null && $regionKey !== '') {
                    $region = Region::query()->create([
                        'external_id' => $regionExternalId !== '' ? $regionExternalId : null,
                        'name' => trim($regionName),
                    ]);
                    $regionsById[(int) $region->id] = $region;
                    if ($regionExternalId !== '') {
                        $regionsByExternalId[$regionExternalId] = $region;
                    }
                    $regionsByName[$regionKey] = $region;
                }
                if ($region !== null) {
                    $regionChanged = false;
                    if ($regionExternalId !== '' && (string) $region->external_id !== $regionExternalId) {
                        $region->external_id = $regionExternalId;
                        $regionChanged = true;
                    }
                    if ($regionKey !== '' && (string) $region->name !== trim($regionName)) {
                        $region->name = trim($regionName);
                        $regionChanged = true;
                    }
                    if ($regionChanged) {
                        $region->save();
                    }
                    $regionsById[(int) $region->id] = $region;
                    if ($regionExternalId !== '') {
                        $regionsByExternalId[$regionExternalId] = $region;
                    }
                    $regionsByName[mb_strtolower(trim((string) $region->name))] = $region;
                }
                if (($regionIdRaw !== '' || $regionName !== '') && $region === null) {
                    $rowErrors[] = 'Region not found.';
                }

                $zoneExternalId = $this->normalizeExternalId($zoneIdRaw);
                $zone = $zoneExternalId !== '' ? ($zonesByExternalId[$zoneExternalId] ?? null) : null;
                $zoneKey = mb_strtolower(trim($zoneName));
                if ($zone === null && $zoneKey !== '' && $region?->id !== null) {
                    $zone = $zonesByScopedKey[$zoneKey.'|r:'.(int) $region->id] ?? null;
                }
                $zone ??= $zoneKey !== '' ? ($zonesByName[$zoneKey] ?? null) : null;
                $zone ??= ($zoneExternalId !== '' && ctype_digit($zoneExternalId)) ? ($zonesById[(int) $zoneExternalId] ?? null) : null;
                if ($zone === null && $zoneKey !== '' && $region?->id !== null) {
                    $zone = Zone::query()->create([
                        'external_id' => $zoneExternalId !== '' ? $zoneExternalId : null,
                        'name' => trim($zoneName),
                        'description' => null,
                        'region_id' => $region->id,
                    ]);
                    $zonesById[(int) $zone->id] = $zone;
                    if ($zoneExternalId !== '') {
                        $zonesByExternalId[$zoneExternalId] = $zone;
                    }
                    $zonesByName[$zoneKey] = $zone;
                    $zonesByScopedKey[$zoneKey.'|r:'.(int) $zone->region_id] = $zone;
                }
                if ($zone !== null) {
                    $zoneChanged = false;
                    if ($zoneExternalId !== '' && (string) $zone->external_id !== $zoneExternalId) {
                        $zone->external_id = $zoneExternalId;
                        $zoneChanged = true;
                    }
                    if ($zoneKey !== '' && (string) $zone->name !== trim($zoneName)) {
                        $zone->name = trim($zoneName);
                        $zoneChanged = true;
                    }
                    if ($region?->id !== null && (int) $zone->region_id !== (int) $region->id) {
                        $zone->region_id = (int) $region->id;
                        $zoneChanged = true;
                    }
                    if ($zoneChanged) {
                        $zone->save();
                    }
                    $zonesById[(int) $zone->id] = $zone;
                    if ($zoneExternalId !== '') {
                        $zonesByExternalId[$zoneExternalId] = $zone;
                    }
                    $zonesByName[mb_strtolower(trim((string) $zone->name))] = $zone;
                }
                if (($zoneIdRaw !== '' || $zoneName !== '') && $zone === null) {
                    $rowErrors[] = $region === null
                        ? 'Zone not found. Provide a valid Region so a new Zone can be created.'
                        : 'Zone not found.';
                }

                if ($region === null && $zone !== null && $zone->region_id !== null) {
                    $region = $regionsById[(int) $zone->region_id] ?? null;
                }

                if ($zone !== null && $region !== null && $zone->region_id === null) {
                    $zone->region_id = (int) $region->id;
                    $zone->save();
                }

                $woredaExternalId = $this->normalizeExternalId($woredaIdRaw);
                $woreda = null;
                $woredaKey = mb_strtolower(trim($woredaName));
                if ($woredaExternalId !== '' && $zone?->id !== null && isset($woredasByExternalScopedKey[$woredaExternalId.'|z:'.$zone->id])) {
                    $woreda = $woredasByExternalScopedKey[$woredaExternalId.'|z:'.$zone->id];
                } elseif ($woredaExternalId !== '' && $zone?->id === null && $region?->id !== null && isset($woredasByExternalScopedKey[$woredaExternalId.'|r:'.$region->id])) {
                    $woreda = $woredasByExternalScopedKey[$woredaExternalId.'|r:'.$region->id];
                }
                if ($woreda === null && $woredaKey !== '') {
                    if ($zone?->id !== null && isset($woredasByScopedKey[$woredaKey.'|z:'.$zone->id])) {
                        $woreda = $woredasByScopedKey[$woredaKey.'|z:'.$zone->id];
                    } elseif ($zone?->id === null && $region?->id !== null && isset($woredasByScopedKey[$woredaKey.'|r:'.$region->id])) {
                        $woreda = $woredasByScopedKey[$woredaKey.'|r:'.$region->id];
                    } elseif ($woredaExternalId === '' && $zone?->id === null && $region?->id === null && count($woredasByName[$woredaKey] ?? []) === 1) {
                        $woreda = $woredasByName[$woredaKey][0];
                    } elseif ($region?->id !== null || $zone?->id !== null) {
                        $woreda = Woreda::query()->create([
                            'external_id' => $woredaExternalId !== '' ? $woredaExternalId : null,
                            'name' => trim($woredaName),
                            'description' => null,
                            'region_id' => $region?->id,
                            'zone_id' => $zone?->id,
                        ]);
                        $woredasById[(int) $woreda->id] = $woreda;
                        $woredasByName[$woredaKey] ??= [];
                        $woredasByName[$woredaKey][] = $woreda;
                        if ($woredaExternalId !== '' && $woreda->zone_id !== null) {
                            $woredasByExternalScopedKey[$woredaExternalId.'|z:'.(int) $woreda->zone_id] = $woreda;
                        }
                        if ($woredaExternalId !== '' && $woreda->region_id !== null) {
                            $woredasByExternalScopedKey[$woredaExternalId.'|r:'.(int) $woreda->region_id] = $woreda;
                        }
                        if ($woreda->zone_id !== null) {
                            $woredasByScopedKey[$woredaKey.'|z:'.(int) $woreda->zone_id] = $woreda;
                        }
                        if ($woreda->region_id !== null) {
                            $woredasByScopedKey[$woredaKey.'|r:'.(int) $woreda->region_id] = $woreda;
                        }
                    }
                }
                if (($woredaIdRaw !== '' || $woredaName !== '') && $woreda === null) {
                    $rowErrors[] = ($region === null && $zone === null)
                        ? 'Woreda not found. Provide a valid Region or Zone so a new Woreda can be created.'
                        : 'Woreda not found.';
                }

                if ($region === null && $woreda !== null && $woreda->region_id !== null) {
                    $region = $regionsById[(int) $woreda->region_id] ?? null;
                }

                if ($woreda !== null) {
                    $woredaChanged = false;

                    if ($woredaExternalId !== '' && (string) $woreda->external_id !== $woredaExternalId) {
                        $woreda->external_id = $woredaExternalId;
                        $woredaChanged = true;
                    }

                    if ($woredaKey !== '' && (string) $woreda->name !== trim($woredaName)) {
                        $woreda->name = trim($woredaName);
                        $woredaChanged = true;
                    }

                    if ($woreda->zone_id === null && $zone?->id !== null) {
                        $woreda->zone_id = (int) $zone->id;
                        $woredaChanged = true;
                    }

                    if ($woreda->region_id === null && $region?->id !== null) {
                        $woreda->region_id = (int) $region->id;
                        $woredaChanged = true;
                    }

                    if ($woredaChanged) {
                        $woreda->save();
                        $woredaKey = mb_strtolower(trim((string) $woreda->name));
                        $woredaExternalId = $this->normalizeExternalId($woreda->external_id);
                        if ($woredaExternalId !== '' && $woreda->zone_id !== null) {
                            $woredasByExternalScopedKey[$woredaExternalId.'|z:'.(int) $woreda->zone_id] = $woreda;
                        }
                        if ($woredaExternalId !== '' && $woreda->region_id !== null) {
                            $woredasByExternalScopedKey[$woredaExternalId.'|r:'.(int) $woreda->region_id] = $woreda;
                        }
                        if ($woreda->zone_id !== null) {
                            $woredasByScopedKey[$woredaKey.'|z:'.(int) $woreda->zone_id] = $woreda;
                        }
                        if ($woreda->region_id !== null) {
                            $woredasByScopedKey[$woredaKey.'|r:'.(int) $woreda->region_id] = $woreda;
                        }
                    }

                    if ($woreda->zone_id !== null) {
                        if ($zone !== null && (int) $zone->id !== (int) $woreda->zone_id) {
                            if ($forceOverwrite) {
                                $woreda->zone_id = (int) $zone->id;
                                $woreda->save();
                            } else {
                                $rowErrors[] = 'Woreda does not belong to the selected Zone.';
                            }
                        }

                        $zone = $zone ?? ($zonesById[(int) $woreda->zone_id] ?? null);
                    } elseif ($zone === null && $woreda->zone_id === null) {
                        $rowErrors[] = 'Selected Woreda has no Zone assigned.';
                    } elseif ($zone === null && $woreda->zone_id !== null) {
                        $zone = $zonesById[(int) $woreda->zone_id] ?? null;
                    }
                }

                if ($zone !== null && $zone->region_id === null) {
                    if ($region?->id !== null) {
                        $zone->region_id = (int) $region->id;
                        $zone->save();
                    } else {
                        $rowErrors[] = 'Selected Zone has no Region assigned.';
                    }
                }

                if ($zone !== null && $region === null && $zone->region_id !== null) {
                    $region = $regionsById[(int) $zone->region_id] ?? null;
                }

                if ($woreda !== null && $region === null && $woreda->region_id !== null) {
                    $region = $regionsById[(int) $woreda->region_id] ?? null;
                }

                if ($woreda !== null && $region !== null && ($woreda->region_id === null || (int) $woreda->region_id !== (int) $region->id)) {
                    if ($forceOverwrite) {
                        if ($woreda->region_id !== $region->id) {
                            $woreda->region_id = (int) $region->id;
                            $woreda->save();
                        }
                    } else {
                        $rowErrors[] = 'Woreda does not belong to the selected Region.';
                    }
                }

                if ($zone !== null && $region !== null && ($zone->region_id === null || (int) $zone->region_id !== (int) $region->id)) {
                    if ($forceOverwrite) {
                        if ($zone->region_id !== $region->id) {
                            $zone->region_id = (int) $region->id;
                            $zone->save();
                        }
                    } else {
                        $rowErrors[] = 'Zone does not belong to the selected Region.';
                    }
                }

                if ($woreda === null && $zone === null) {
                    $rowErrors[] = 'Either Zone or Woreda is required.';
                }

                if (! empty($rowErrors)) {
                    $skipped++;
                    $reason = implode(' ', $rowErrors);
                    $errors[] = 'Line '.$line.': '.$reason;
                    $skippedRows[] = [
                        'line' => $line,
                        'reason' => $reason,
                        'row' => $reportRow,
                    ];
                    continue;
                }

                $payload = [
                    'external_id' => $organizationExternalId !== '' ? $organizationExternalId : null,
                    'name' => $name,
                    'category' => $category,
                    'type' => $type,
                    'region_id' => $region?->id,
                    'zone_id' => $zone?->id,
                    'zone' => $zone?->name,
                    'woreda_id' => $woreda?->id,
                    'city_town' => $cityTown !== '' ? $cityTown : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'fax' => $fax !== '' ? $fax : null,
                ];

                if ($existing) {
                    if (! $forceOverwrite) {
                        if ($organizationExternalId === '') {
                            unset($payload['external_id']);
                        }
                        if ($cityTown === '') {
                            unset($payload['city_town']);
                        }
                        if ($phone === '') {
                            unset($payload['phone']);
                        }
                        if ($fax === '') {
                            unset($payload['fax']);
                        }
                    }

                    $dirty = false;
                    foreach ($payload as $field => $value) {
                        if ($existing->{$field} !== $value) {
                            $existing->{$field} = $value;
                            $dirty = true;
                        }
                    }

                    if ($dirty) {
                        $existing->save();
                        $updated++;
                    }
                    if ($organizationExternalId !== '') {
                        $organizationsByExternalId[$organizationExternalId] = $existing;
                    }
                    $organizationsByName[mb_strtolower(trim((string) $existing->name))] = $existing;
                } else {
                    $createdOrganization = Organization::query()->create($payload);
                    if ($organizationExternalId !== '') {
                        $organizationsByExternalId[$organizationExternalId] = $createdOrganization;
                    }
                    $organizationsByName[$organizationKey] = $createdOrganization;
                    $created++;
                }
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'skipped_rows' => $skippedRows,
                'import_mode' => $importMode,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function overwriteOrganizationHierarchyFromCsv(string $path, array $headerMap): void
    {
        $handle = $path !== '' ? fopen($path, 'r') : false;
        if ($handle === false) {
            throw new \RuntimeException('Unable to read import file.');
        }

        try {
            $headerRow = fgetcsv($handle);
            if (! is_array($headerRow) || empty($headerRow)) {
                return;
            }

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->csvRowIsBlank($row)) {
                    continue;
                }

                $regionIdRaw = $this->csvCell($row, $headerMap, ['region_id']);
                $regionName = $this->csvCell($row, $headerMap, ['region_name', 'region']);
                $zoneIdRaw = $this->csvCell($row, $headerMap, ['zone_id']);
                $zoneName = $this->csvCell($row, $headerMap, ['zone_name', 'zone']);
                $woredaIdRaw = $this->csvCell($row, $headerMap, ['woreda_id']);
                $woredaName = $this->csvCell($row, $headerMap, ['woreda_name', 'woreda']);

                $region = $this->resolveOrCreateRegion($regionIdRaw, $regionName);
                if ($region === null) {
                    continue;
                }

                $zone = null;
                if ($zoneIdRaw !== '' || trim($zoneName) !== '') {
                    $zone = $this->resolveOrCreateZone($zoneIdRaw, $zoneName, (int) $region->id);
                }

                if ($woredaIdRaw !== '' || trim($woredaName) !== '') {
                    $woreda = $this->resolveOrCreateWoreda($woredaIdRaw, $woredaName, (int) $region->id, $zone?->id);
                    if ($woreda !== null) {
                        $woredaChanged = false;
                        if ($zone?->id !== null && $woreda->zone_id !== $zone->id) {
                            $woreda->zone_id = $zone->id;
                            $woredaChanged = true;
                        }
                        if ($woreda->region_id === null || (int) $woreda->region_id !== (int) $region->id) {
                            $woreda->region_id = (int) $region->id;
                            $woredaChanged = true;
                        }
                        if ($woredaChanged) {
                            $woreda->save();
                        }
                    }
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function writeOrganizationSkippedRowsReport(array $skippedRows): ?array
    {
        if (empty($skippedRows)) {
            return null;
        }

        $fileName = 'organizations-import-skipped-'.now()->format('Ymd-His').'-'.Str::random(6).'.csv';
        $path = self::ORGANIZATION_IMPORT_REPORT_DIRECTORY.'/'.$fileName;
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create organization import skipped rows report.');
        }

        try {
            fputcsv($stream, array_merge([
                'line_number',
                'status',
                'reason',
            ], self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS));

            foreach ($skippedRows as $skippedRow) {
                $row = (array) ($skippedRow['row'] ?? []);
                fputcsv($stream, array_merge([
                    (int) ($skippedRow['line'] ?? 0),
                    'skipped_not_created',
                    (string) ($skippedRow['reason'] ?? ''),
                ], array_map(
                    fn (string $header): string => (string) ($row[$header] ?? ''),
                    self::ORGANIZATION_IMPORT_TEMPLATE_HEADERS
                )));
            }

            rewind($stream);
            Storage::disk('local')->put($path, stream_get_contents($stream));
        } finally {
            fclose($stream);
        }

        return [
            'file_name' => $fileName,
            'url' => route('admin.organizations.import-report', ['report' => $fileName]),
        ];
    }

    public function exportParticipants(): StreamedResponse
    {
        $fileName = 'participants-export-'.now()->format('Ymd-His').'.csv';
        $this->audit()->logCustom('Participants exported', 'participants.export', [
            'auditable_type' => Participant::class,
            'metadata' => [
                'exported_records' => Participant::query()->count(),
                'file_name' => $fileName,
            ],
        ]);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'participant_code',
                'first_name',
                'father_name',
                'grandfather_name',
                'date_of_birth',
                'age',
                'gender',
                'home_phone',
                'mobile_phone',
                'email',
                'profession',
                'region_id',
                'region_name',
                'zone_id',
                'zone_name',
                'woreda_id',
                'woreda_name',
                'organization_id',
                'organization_name',
            ]);

            Participant::query()
                ->with(['region', 'zone', 'woreda', 'organization'])
                ->orderBy('id')
                ->chunkById(500, function ($participants) use ($handle) {
                    foreach ($participants as $participant) {
                        fputcsv($handle, [
                            (string) $participant->participant_code,
                            (string) $participant->first_name,
                            (string) $participant->father_name,
                            (string) $participant->grandfather_name,
                            $participant->date_of_birth?->toDateString() ?? '',
                            $participant->age,
                            (string) $participant->gender,
                            (string) $participant->home_phone,
                            (string) $participant->mobile_phone,
                            (string) $participant->email,
                            (string) $participant->profession,
                            $participant->region_id,
                            (string) data_get($participant, 'region.name', ''),
                            $participant->zone_id,
                            (string) data_get($participant, 'zone.name', ''),
                            $participant->woreda_id,
                            (string) data_get($participant, 'woreda.name', ''),
                            $participant->organization_id,
                            (string) data_get($participant, 'organization.name', ''),
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importParticipants(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $path = $validated['import_file']->getRealPath();
        $handle = $path ? fopen($path, 'r') : false;

        if ($handle === false) {
            return back()->with('error', 'Unable to read import file.');
        }

        $headerRow = fgetcsv($handle);

        if (! is_array($headerRow) || empty($headerRow)) {
            fclose($handle);

            return back()->with('error', 'Invalid CSV file: missing header row.');
        }

        $headerMap = [];
        foreach ($headerRow as $index => $column) {
            $normalized = $this->normalizeCsvHeader((string) $column);
            if ($normalized !== '' && ! array_key_exists($normalized, $headerMap)) {
                $headerMap[$normalized] = $index;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $line = 1;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->csvRowIsBlank($row)) {
                continue;
            }

            $participantCode = strtoupper($this->csvCell($row, $headerMap, ['participant_code', 'participant_id']));
            $firstName = $this->csvCell($row, $headerMap, ['first_name']);
            $fatherName = $this->csvCell($row, $headerMap, ['father_name', 'fathers_name']);
            $grandfatherName = $this->csvCell($row, $headerMap, ['grandfather_name', 'grandfathers_name']);
            $dateOfBirthRaw = $this->csvCell($row, $headerMap, ['date_of_birth', 'dob']);
            $ageRaw = $this->csvCell($row, $headerMap, ['age']);
            $genderRaw = $this->csvCell($row, $headerMap, ['gender', 'sex']);
            $homePhone = $this->csvCell($row, $headerMap, ['home_phone']);
            $mobilePhone = $this->csvCell($row, $headerMap, ['mobile_phone', 'mobile', 'phone']);
            $email = mb_strtolower($this->csvCell($row, $headerMap, ['email']));
            $professionRaw = $this->csvCell($row, $headerMap, ['profession', 'profession_name']);
            $regionIdRaw = $this->csvCell($row, $headerMap, ['region_id']);
            $regionName = $this->csvCell($row, $headerMap, ['region_name', 'region']);
            $zoneIdRaw = $this->csvCell($row, $headerMap, ['zone_id']);
            $zoneName = $this->csvCell($row, $headerMap, ['zone_name', 'zone']);
            $woredaIdRaw = $this->csvCell($row, $headerMap, ['woreda_id']);
            $woredaName = $this->csvCell($row, $headerMap, ['woreda_name', 'woreda']);
            $organizationIdRaw = $this->csvCell($row, $headerMap, ['organization_id']);
            $organizationName = $this->csvCell($row, $headerMap, ['organization_name', 'organization']);

            $rowErrors = [];

            if ($firstName === '') {
                $rowErrors[] = 'First name is required.';
            }

            if ($fatherName === '') {
                $rowErrors[] = 'Father\'s name is required.';
            }

            if ($grandfatherName === '') {
                $rowErrors[] = 'Grandfather\'s name is required.';
            }

            if ($mobilePhone === '') {
                $rowErrors[] = 'Mobile phone is required.';
            } elseif (! $this->validParticipantPhone($mobilePhone)) {
                $rowErrors[] = 'Mobile phone is invalid.';
            }

            if ($homePhone !== '' && ! $this->validParticipantPhone($homePhone)) {
                $rowErrors[] = 'Home phone is invalid.';
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Email is invalid.';
            }

            $gender = $this->normalizeParticipantGender($genderRaw);
            if ($genderRaw === '' || $gender === null) {
                $rowErrors[] = 'Gender is missing or invalid.';
            }

            $birthData = $this->normalizeParticipantBirthData($dateOfBirthRaw, $ageRaw);
            if (! $birthData['valid']) {
                $rowErrors[] = $birthData['message'];
            }

            $profession = $this->resolveProfession($professionRaw);
            if ($professionRaw === '' || $profession === null) {
                $rowErrors[] = 'Profession not found.';
            }

            $region = $this->resolveRegion($regionIdRaw, $regionName);
            if (($regionIdRaw !== '' || $regionName !== '') && $region === null) {
                $rowErrors[] = 'Region not found.';
            }

            $zone = $this->resolveZone($zoneIdRaw, $zoneName, $region?->id);
            if (($zoneIdRaw !== '' || $zoneName !== '') && $zone === null) {
                $rowErrors[] = 'Zone not found.';
            }

            if ($region === null && $zone !== null && $zone->region_id !== null) {
                $region = $zone->region;
            }

            $woreda = $this->resolveWoreda($woredaIdRaw, $woredaName);
            if (($woredaIdRaw !== '' || $woredaName !== '') && $woreda === null) {
                $rowErrors[] = 'Woreda not found.';
            }

            if ($zone === null && $woreda !== null && $woreda->zone_id !== null) {
                $zone = $woreda->zone;
            }

            if ($region === null && $woreda !== null && $woreda->region_id !== null) {
                $region = $woreda->region;
            }

            $organization = $this->resolveOrganization($organizationIdRaw, $organizationName);
            if (($organizationIdRaw !== '' || $organizationName !== '') && $organization === null) {
                $rowErrors[] = 'Organization not found.';
            }

            if ($zone === null && $organization !== null && $organization->zone_id !== null) {
                $zone = Zone::query()->find((int) $organization->zone_id);
            }

            if ($region === null && $organization !== null && $organization->region_id !== null) {
                $region = Region::query()->find((int) $organization->region_id);
            }

            if ($region === null) {
                $rowErrors[] = 'Region is required.';
            }

            if ($zone === null) {
                $rowErrors[] = 'Zone is required.';
            }

            if ($woreda === null) {
                $rowErrors[] = 'Woreda is required.';
            }

            if ($organization === null) {
                $rowErrors[] = 'Organization is required.';
            }

            if ($region !== null && $woreda !== null && (int) $woreda->region_id !== (int) $region->id) {
                $rowErrors[] = 'Woreda does not belong to the selected Region.';
            }

            $existingByCode = null;
            if ($participantCode !== '') {
                $existingByCode = Participant::query()
                    ->where('participant_code', $participantCode)
                    ->first();
            }

            $existingByEmail = null;
            if ($email !== '') {
                $existingByEmail = Participant::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();
            }

            if ($existingByCode && $existingByEmail && ! $existingByCode->is($existingByEmail)) {
                $rowErrors[] = 'Participant code and email match different existing records.';
            }

            if (! empty($rowErrors)) {
                $skipped++;
                $errors[] = 'Line '.$line.': '.implode(' ', $rowErrors);
                continue;
            }

            $existing = $existingByCode ?: $existingByEmail;

            $payload = [
                'first_name' => $firstName,
                'father_name' => $fatherName,
                'grandfather_name' => $grandfatherName,
                'region_id' => $region?->id,
                'zone_id' => $zone?->id,
                'woreda_id' => $woreda?->id,
                'organization_id' => $organization?->id,
                'gender' => $gender,
                'home_phone' => $homePhone !== '' ? $homePhone : null,
                'mobile_phone' => $mobilePhone,
                'email' => $email !== '' ? $email : null,
                'profession' => $profession?->name,
            ];

            if ($birthData['date_of_birth'] !== null) {
                $payload['date_of_birth'] = $birthData['date_of_birth'];
            } else {
                $payload['date_of_birth'] = null;
            }

            if ($birthData['age'] !== null) {
                $payload['age'] = $birthData['age'];
            } else {
                $payload['age'] = null;
            }

            try {
                $this->applyHierarchyConstraints('participants', $payload);
            } catch (ValidationException $exception) {
                $skipped++;
                $rowMessages = collect($exception->errors())
                    ->flatten()
                    ->filter()
                    ->implode(' ');
                $errors[] = 'Line '.$line.': '.($rowMessages !== '' ? $rowMessages : 'Participant row is invalid.');
                continue;
            }

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Participant::query()->create($payload);
                $created++;
            }
        }

        fclose($handle);

        $this->audit()->logCustom('Participants imported', 'participants.import', [
            'auditable_type' => Participant::class,
            'metadata' => [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ]);

        $successMessage = 'Participant import completed: '.$created.' created, '.$updated.' updated';
        if ($skipped > 0) {
            $successMessage .= ', '.$skipped.' skipped.';
        } else {
            $successMessage .= '.';
        }

        $redirect = back()->with('success', $successMessage);

        if (! empty($errors)) {
            $previewErrors = array_slice($errors, 0, 8);
            $moreCount = max(0, count($errors) - count($previewErrors));
            $message = implode(' ', $previewErrors);
            if ($moreCount > 0) {
                $message .= ' ... and '.$moreCount.' more issue(s).';
            }
            $redirect->with('error', $message);
        }

        return $redirect;
    }

    private function findRecord(array $config, string $record): Model
    {
        $modelClass = $config['model'];

        return $modelClass::query()->with($config['eager'] ?? [])->findOrFail($record);
    }

    private function rules(array $config, ?Model $record = null): array
    {
        $id = $record?->getKey() ?? 'NULL';

        return collect($config['rules'])->map(fn (string $rule) => str_replace('{{id}}', (string) $id, $rule))->all();
    }

    private function fieldOptions(array $config, ?Model $record = null): array
    {
        return collect($config['fields'])
            ->filter(fn (array $field) => in_array(($field['type'] ?? null), ['select', 'multiselect'], true))
            ->mapWithKeys(function (array $field) use ($config, $record) {
                if (isset($field['choices'])) {
                    return [$field['name'] => collect($field['choices'])->map(function ($choice) {
                        return is_array($choice)
                            ? ['value' => $choice['value'], 'label' => $choice['label']]
                            : ['value' => $choice, 'label' => $choice];
                    })->all()];
                }

                $optionConfig = $field['options'];
                $modelClass = $optionConfig['model'];
                $label = $optionConfig['label'];
                $value = $optionConfig['value'];
                $table = $modelClass::make()->getTable();
                $query = $modelClass::query();

                if (! empty($optionConfig['distinct'])) {
                    $existingPrograms = $query->select($value)->distinct()->whereNotNull($value)->where($value, '!=', '')->pluck($value)->toArray();
                    $predefinedPrograms = $optionConfig['predefined'] ?? [];
                    $allPrograms = array_unique(array_merge($existingPrograms, $predefinedPrograms));
                    sort($allPrograms);
                    return [$field['name'] => collect($allPrograms)->map(fn ($program) => ['value' => $program, 'label' => $program])->all()];
                }

                if (! empty($optionConfig['with']) && is_array($optionConfig['with'])) {
                    $query->with($optionConfig['with']);
                }

                $fieldName = $field['name'] ?? null;

                if ($fieldName === 'organization_id') {
                    $selectedValue = old($field['name']);
                    if ($selectedValue === null && $record) {
                        $selectedValue = data_get($record, $field['name']);
                    }

                    if ($selectedValue === null || $selectedValue === '') {
                        return [$field['name'] => []];
                    }

                    $selected = $query->whereKey($selectedValue)->first();

                    return [$field['name'] => $selected ? [$this->formatSelectOption($selected, $label, $value, $optionConfig)] : []];
                }

                if (($config['path'] ?? null) === 'participants' && in_array($fieldName, ['zone_id', 'woreda_id'], true)) {
                    $selectedValue = old($field['name']);
                    if ($selectedValue === null && $record) {
                        $selectedValue = data_get($record, $field['name']);
                    }

                    if ($selectedValue === null || $selectedValue === '') {
                        return [$field['name'] => []];
                    }

                    $selected = $query->whereKey($selectedValue)->first();

                    return [$field['name'] => $selected ? [$this->formatSelectOption($selected, $label, $value, $optionConfig)] : []];
                }

                if (! empty($optionConfig['with']) && is_array($optionConfig['with'])) {
                    $query->with($optionConfig['with']);
                }

                if (str_contains($label, '.')) {
                    $query->with(Str::before($label, '.'));
                }

                if (Schema::hasColumn($table, $label)) {
                    $query->orderBy($label);
                } else {
                    $query->orderBy($value);
                }

                return [$field['name'] => $query->get()->map(fn ($item) => $this->formatSelectOption($item, $label, $value, $optionConfig))->all()];
            })
            ->all();
    }

    private function formatSelectOption(Model $item, string $label, string $value, array $optionConfig = []): array
    {
        $resolvedLabel = data_get($item, $label);
        $displayLabel = $resolvedLabel !== null && $resolvedLabel !== ''
            ? (string) $resolvedLabel
            : (string) $item->{$value};

        // Special formatting for organization_id: concatenate name with region
        if (!empty($optionConfig['format_label']) && $optionConfig['format_label'] === 'organization_with_region') {
            $orgName = $item->name ?? '';
            $regionName = $item->region?->name ?? '';
            if ($orgName && $regionName) {
                $displayLabel = $orgName.' - '.$regionName.' region';
            } elseif ($orgName) {
                $displayLabel = $orgName;
            }
        }

        return [
            'value' => $item->{$value},
            'label' => $displayLabel,
            'region_id' => data_get($item, 'region_id'),
            'zone_id' => data_get($item, 'zone_id'),
            'woreda_id' => data_get($item, 'woreda_id'),
            'project_id' => data_get($item, 'project_id'),
        ];
    }

    private function applySearch(Builder $query, array $config, string $term): void
    {
        if ($term === '' || empty($config['search'])) {
            return;
        }

        $query->where(function (Builder $builder) use ($config, $term) {
            foreach ($config['search'] as $column) {
                $builder->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    private function applyGroupedTrainingEventSearch(\Illuminate\Database\Query\Builder $query, array $config, string $term): void
    {
        if ($term === '') {
            return;
        }

        $query->where(function ($builder) use ($config, $term) {
            foreach ($config['search'] ?? [] as $column) {
                if (str_contains((string) $column, '.')) {
                    continue;
                }

                $builder->orWhere('te.'.$column, 'like', '%'.$term.'%');
            }

            $builder->orWhere('t.title', 'like', '%'.$term.'%');
            $builder->orWhere('o.title', 'like', '%'.$term.'%');
        });
    }

    private function applyHierarchyConstraints(string $resource, array &$data): void
    {
        $messages = [];

        if ($resource === 'zones') {
            $regionId = $this->nullableInt($data['region_id'] ?? null);
            if ($regionId === null || ! Region::query()->whereKey($regionId)->exists()) {
                $messages['region_id'] = 'Zone must belong to a valid Region.';
            }
        }

        if ($resource === 'woredas') {
            $zoneId = $this->nullableInt($data['zone_id'] ?? null);
            $regionId = $this->nullableInt($data['region_id'] ?? null);

            $zone = $zoneId ? Zone::query()->find($zoneId) : null;
            if (! $zone) {
                $messages['zone_id'] = 'Woreda must belong to a valid Zone.';
            } elseif ($zone->region_id === null) {
                $messages['zone_id'] = 'Selected Zone is not linked to a Region.';
            } else {
                if ($regionId !== null && $regionId !== (int) $zone->region_id) {
                    $messages['region_id'] = 'Selected Region does not match the Zone\'s Region.';
                }

                $data['region_id'] = (int) $zone->region_id;
            }
        }

        if ($resource === 'participants') {
            $regionId = $this->nullableInt($data['region_id'] ?? null);
            $zoneId = $this->nullableInt($data['zone_id'] ?? null);
            $woredaId = $this->nullableInt($data['woreda_id'] ?? null);
            $organizationId = $this->nullableInt($data['organization_id'] ?? null);
            $zone = $zoneId ? Zone::query()->find($zoneId) : null;
            $woreda = $woredaId ? Woreda::query()->find($woredaId) : null;
            $organization = $organizationId ? Organization::query()->find($organizationId) : null;

            if ($woreda && $woreda->zone_id === null) {
                $messages['woreda_id'] = 'Selected Woreda is not linked to a Zone.';
            } elseif (! $zone && $woreda && $woreda->zone_id !== null) {
                $zone = Zone::query()->find((int) $woreda->zone_id);
            }

            if (! $zone && $organization && $organization->zone_id !== null) {
                $zone = Zone::query()->find((int) $organization->zone_id);
            }

            if ($zone && $zone->region_id === null) {
                $messages['zone_id'] = 'Selected Zone is not linked to a Region.';
            } elseif ($zone && $zone->region_id !== null) {
                if ($regionId !== null && (int) $zone->region_id !== $regionId) {
                    $messages['region_id'] = 'Selected Region does not match the Zone\'s Region.';
                }

                $regionId = (int) $zone->region_id;
            }

            if ($woreda && $zone && (int) $woreda->zone_id !== (int) $zone->id) {
                $messages['woreda_id'] = 'Selected Woreda does not belong to the selected Zone.';
            }

            if ($woreda && $regionId !== null && (int) $woreda->region_id !== $regionId) {
                $messages['woreda_id'] = 'Selected Woreda does not belong to the selected Region.';
            }

            if ($organization && $organization->region_id !== null && $regionId !== null && (int) $organization->region_id !== $regionId) {
                $messages['organization_id'] = 'Selected Organization does not belong to the selected Region.';
            }

            if ($organization && $organization->zone_id !== null && $zone && (int) $organization->zone_id !== (int) $zone->id) {
                $messages['organization_id'] = 'Selected Organization does not belong to the selected Zone.';
            }

            if ($organization && $organization->woreda_id !== null && $woreda && (int) $organization->woreda_id !== (int) $woreda->id) {
                $messages['organization_id'] = 'Selected Organization does not belong to the selected Woreda.';
            }

            $data['region_id'] = $regionId;
            $data['zone_id'] = $zone?->id;
        }

        if ($resource === 'organizations') {
            $regionId = $this->nullableInt($data['region_id'] ?? null);
            $zoneId = $this->nullableInt($data['zone_id'] ?? null);
            $woredaId = $this->nullableInt($data['woreda_id'] ?? null);

            $zone = $zoneId ? Zone::query()->find($zoneId) : null;
            $woreda = $woredaId ? Woreda::query()->find($woredaId) : null;

            if ($woreda && $woreda->zone_id === null) {
                $messages['woreda_id'] = 'Selected Woreda is not linked to a Zone.';
            }

            if (! $woreda && ! $zone) {
                $messages['zone_id'] = 'Health Facility must belong to either a Zone or a Woreda.';
            }

            if ($woreda && $zone && (int) $woreda->zone_id !== (int) $zone->id) {
                $messages['woreda_id'] = 'Selected Woreda does not belong to the selected Zone.';
            }

            if ($woreda && ! $zone && $woreda->zone_id !== null) {
                $zone = Zone::query()->find((int) $woreda->zone_id);
            }

            if ($zone && $zone->region_id === null) {
                $messages['zone_id'] = 'Selected Zone is not linked to a Region.';
            }

            if ($zone) {
                if ($regionId !== null && $zone->region_id !== null && $regionId !== (int) $zone->region_id) {
                    $messages['region_id'] = 'Selected Region does not match the Zone\'s Region.';
                }
                if ($zone->region_id !== null) {
                    $regionId = (int) $zone->region_id;
                }
            }

            if ($woreda && $regionId !== null && (int) $woreda->region_id !== $regionId) {
                $messages['woreda_id'] = 'Selected Woreda does not belong to the resolved Region.';
            }

            $data['region_id'] = $regionId;
            $data['zone_id'] = $zone?->id;
            $data['zone'] = $zone?->name;
        }

        if ($resource === 'training_events') {
            $organizerId = $this->nullableInt($data['training_organizer_id'] ?? null);
            $organizerType = trim((string) ($data['organizer_type'] ?? ''));
            $subawardeeId = $this->nullableInt($data['project_subawardee_id'] ?? null);

            $organizer = $organizerId ? \App\Models\TrainingOrganizer::query()->find($organizerId) : null;
            $subawardee = $subawardeeId ? \App\Models\ProjectSubawardee::query()->find($subawardeeId) : null;

            if (! $organizer) {
                $messages['training_organizer_id'] = 'Training Event must belong to a valid Organizer.';
            }

            if ($organizerType === 'Subawardee') {
                if (! $subawardee) {
                    $messages['project_subawardee_id'] = 'Subawardee Name is required when Type of Organizer is Subawardee.';
                } elseif ($organizer && (int) $subawardee->project_id !== (int) $organizer->id) {
                    $messages['project_subawardee_id'] = 'Selected Subawardee does not belong to the selected Organizer.';
                }
            } else {
                $data['project_subawardee_id'] = null;
            }
        }

        if (! empty($messages)) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function preparePayload(Request $request, array $config, ?Model $record = null): array
    {
        $data = $request->validate($this->rules($config, $record));

        foreach ($this->checkboxFields($config) as $field) {
            $name = $field['name'];

            if ($request->has($name)) {
                $data[$name] = $request->boolean($name);
                continue;
            }

            if ($record === null && array_key_exists('default', $field)) {
                $data[$name] = (bool) $field['default'];
            }
        }

        foreach ($this->fileFields($config) as $field) {
            $name = $field['name'];

            if (! $request->hasFile($name)) {
                unset($data[$name]);
                continue;
            }

            $disk = $field['disk'] ?? 'public';
            $directory = $field['directory'] ?? ('uploads/'.$config['path']);

            if ($record !== null) {
                $existingPath = (string) data_get($record, $name, '');
                if ($existingPath !== '' && Storage::disk($disk)->exists($existingPath)) {
                    Storage::disk($disk)->delete($existingPath);
                }
            }

            $data[$name] = $request->file($name)->store($directory, $disk);
        }

        return $data;
    }

    private function extractRelationPayload(array &$data, array $config): array
    {
        $payload = [];

        foreach ($config['fields'] ?? [] as $field) {
            $name = $field['name'] ?? null;
            $relation = $field['relation'] ?? null;
            if (! $name || ! $relation || ! array_key_exists($name, $data)) {
                continue;
            }

            if (($field['type'] ?? null) === 'multiselect') {
                $ids = collect($data[$name] ?? [])
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->values()
                    ->all();

                $payload[$relation] = [
                    'type' => 'multiselect',
                    'values' => $ids,
                ];
                unset($data[$name]);

                $primaryColumn = $field['primary_column'] ?? null;
                if ($primaryColumn && ! empty($ids)) {
                    $data[$primaryColumn] = $ids[0];
                }

                continue;
            }

            if (($field['type'] ?? null) === 'repeater') {
                $values = collect($data[$name] ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $payload[$relation] = [
                    'type' => 'repeater',
                    'values' => $values,
                    'column' => (string) ($field['column'] ?? 'name'),
                ];
                unset($data[$name]);
            }
        }

        return $payload;
    }

    private function syncRelations(Model $model, array $relationPayload): void
    {
        foreach ($relationPayload as $relation => $relationConfig) {
            if (! method_exists($model, $relation)) {
                continue;
            }

            $type = $relationConfig['type'] ?? 'multiselect';
            $values = $relationConfig['values'] ?? [];

            if ($type === 'multiselect') {
                $model->{$relation}()->sync($values);
                continue;
            }

            if ($type === 'repeater') {
                $column = (string) ($relationConfig['column'] ?? 'name');
                $model->{$relation}()->delete();

                if (! empty($values)) {
                    $model->{$relation}()->createMany(
                        collect($values)->map(fn (string $value) => [$column => $value])->all()
                    );
                }
            }
        }
    }

    private function deleteAttachedFiles(array $config, Model $record): void
    {
        foreach ($this->fileFields($config) as $field) {
            $name = $field['name'];
            $disk = $field['disk'] ?? 'public';
            $path = (string) data_get($record, $name, '');

            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    private function fileFieldConfig(array $config, string $name): ?array
    {
        return collect($config['fields'])
            ->first(fn (array $field) => ($field['type'] ?? null) === 'file' && ($field['name'] ?? null) === $name);
    }

    private function fileFields(array $config): array
    {
        return collect($config['fields'])
            ->filter(fn (array $field) => ($field['type'] ?? null) === 'file')
            ->values()
            ->all();
    }

    private function checkboxFields(array $config): array
    {
        return collect($config['fields'])
            ->filter(fn (array $field) => ($field['type'] ?? null) === 'checkbox')
            ->values()
            ->all();
    }

    private function csvTrainingOrganizerSubawardees(array $row, array $headerMap): array
    {
        $values = $this->splitCsvList($this->csvCell($row, $headerMap, [
            'subawardees',
            'subawardee_names',
            'subawardees_list',
        ]));

        foreach ($headerMap as $header => $index) {
            if (! preg_match('/^subawardee(_name)?(_\d+)?$/', (string) $header)) {
                continue;
            }

            $cell = trim((string) ($row[(int) $index] ?? ''));
            if ($cell !== '') {
                $values = array_merge($values, $this->splitCsvList($cell));
            }
        }

        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function splitCsvList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return preg_split('/\s*(?:;|\||\r\n|\r|\n)\s*/', $value) ?: [];
    }

    private function normalizeCsvBoolean(string $value): ?bool
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'active', 'enabled' => true,
            '0', 'false', 'no', 'n', 'inactive', 'disabled' => false,
            default => null,
        };
    }

    private function syncTrainingOrganizerSubawardees(TrainingOrganizer $organizer, array $names): void
    {
        $organizer->subawardees()->delete();

        if ($names === []) {
            return;
        }

        $organizer->subawardees()->createMany(
            collect($names)->map(fn (string $name) => ['subawardee_name' => $name])->all()
        );
    }

    private function normalizeCsvHeader(string $header): string
    {
        $cleanHeader = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return trim((string) Str::of($cleanHeader)->lower()->replace(['-', ' '], '_')->replace('__', '_'));
    }

    private function csvCell(array $row, array $headerMap, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeCsvHeader((string) $key);
            if (! array_key_exists($normalized, $headerMap)) {
                continue;
            }

            $index = (int) $headerMap[$normalized];
            $value = $row[$index] ?? null;

            if ($value === null) {
                continue;
            }

            return trim((string) $value);
        }

        return '';
    }

    private function csvRowIsBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function choiceLookup(array $config, string $fieldName): array
    {
        $field = collect($config['fields'] ?? [])->first(fn (array $item) => ($item['name'] ?? null) === $fieldName);
        $choices = $field['choices'] ?? [];
        $lookup = [];

        foreach ($choices as $choice) {
            $value = is_array($choice) ? ($choice['value'] ?? '') : (string) $choice;
            $normalized = mb_strtolower(trim((string) $value));

            if ($normalized !== '') {
                $lookup[$normalized] = (string) $value;
            }
        }

        return $lookup;
    }

    private function matchChoiceValue(string $value, array $lookup): ?string
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        return $lookup[$normalized] ?? null;
    }

    private function normalizeOrganizationCategory(string $value, array $lookup, ?Organization $existing = null): string
    {
        $matched = $this->matchChoiceValue($value, $lookup);
        if ($matched !== null) {
            return $matched;
        }

        $normalized = $this->normalizeImportToken($value);

        if ($normalized !== '') {
            if (str_contains($normalized, 'government') || str_contains($normalized, 'public') || str_contains($normalized, 'moh') || str_contains($normalized, 'health bureau') || str_contains($normalized, 'health office')) {
                return 'Government/Public';
            }

            if (str_contains($normalized, 'military') || str_contains($normalized, 'police') || str_contains($normalized, 'prison') || str_contains($normalized, 'defense')) {
                return 'Military/Police/Prison';
            }

            if (str_contains($normalized, 'ngo') || str_contains($normalized, 'cso') || str_contains($normalized, 'non governmental')) {
                return 'NGO/CSO';
            }

            if (str_contains($normalized, 'faith') || str_contains($normalized, 'religious') || str_contains($normalized, 'mission')) {
                return 'Faith Based Org.';
            }

            if (str_contains($normalized, 'un agency') || str_contains($normalized, 'united nations')) {
                return 'UN Agency';
            }

            if (str_contains($normalized, 'community')) {
                return 'Community Org.';
            }

            if (str_contains($normalized, 'private') || str_contains($normalized, 'for profit')) {
                return 'Private';
            }
        }

        return $existing?->category ?: self::CSV_ORGANIZATION_DEFAULT_CATEGORY;
    }

    private function normalizeOrganizationType(string $value, array $lookup, ?Organization $existing = null): string
    {
        $matched = $this->matchChoiceValue($value, $lookup);
        if ($matched !== null) {
            return $matched;
        }

        $normalized = $this->normalizeImportToken($value);

        if ($normalized !== '') {
            if (str_contains($normalized, 'hospital')) {
                return 'Hospital';
            }

            if (str_contains($normalized, 'health post')) {
                return 'Health Post';
            }

            if (str_contains($normalized, 'health center') || str_contains($normalized, 'clinic') || str_contains($normalized, 'medical center') || str_contains($normalized, 'division')) {
                return 'Health Center/Clinic/Division';
            }

            if (str_contains($normalized, 'laborator')) {
                return 'Laboratory';
            }

            if (str_contains($normalized, 'pharmacy') || str_contains($normalized, 'drug shop') || str_contains($normalized, 'drug store') || str_contains($normalized, 'drug vendor')) {
                return 'Pharmacy';
            }

            if (str_contains($normalized, 'school') || str_contains($normalized, 'university') || str_contains($normalized, 'college')) {
                return 'School/University';
            }

            if (str_contains($normalized, 'research')) {
                return 'Research Institute';
            }

            if (str_contains($normalized, 'international') && (str_contains($normalized, 'ngo') || str_contains($normalized, 'cso'))) {
                return 'International NGO/CSO';
            }

            if (str_contains($normalized, 'ngo') || str_contains($normalized, 'cso')) {
                return 'Local NGO/CSO';
            }

            if (str_contains($normalized, 'faith') || str_contains($normalized, 'religious') || str_contains($normalized, 'mission')) {
                return 'Faith-based org.';
            }

            if (str_contains($normalized, 'community')) {
                return 'Community based org.';
            }

            if (str_contains($normalized, 'media')) {
                return 'Media Related';
            }

            if (str_contains($normalized, 'military') || str_contains($normalized, 'police') || str_contains($normalized, 'prison') || str_contains($normalized, 'defense')) {
                return 'Defense/Police force/Prison';
            }

            if (str_contains($normalized, 'moh') || str_contains($normalized, 'rhb') || str_contains($normalized, 'zhd') || str_contains($normalized, 'woreda health office') || str_contains($normalized, 'health bureau')) {
                return 'MOH/RHB/ZHD/Wor. HO';
            }

            if (str_contains($normalized, 'government') || str_contains($normalized, 'public') || str_contains($normalized, 'office')) {
                return 'Other Government org.';
            }

            if (str_contains($normalized, 'business') || str_contains($normalized, 'commercial')) {
                return 'Business/Commercial entity';
            }

            if (str_contains($normalized, 'club') || str_contains($normalized, 'association')) {
                return 'Club/Association';
            }

            if (str_contains($normalized, 'un agency') || str_contains($normalized, 'united nations')) {
                return 'UN agency';
            }

            if (str_contains($normalized, 'usg') || str_contains($normalized, 'usaid') || str_contains($normalized, 'cdc')) {
                return 'USG agency';
            }
        }

        return $existing?->type ?: self::CSV_ORGANIZATION_DEFAULT_TYPE;
    }

    private function normalizeImportToken(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '' || ! ctype_digit($stringValue)) {
            return null;
        }

        return (int) $stringValue;
    }

    private function normalizeExternalId(mixed $value): string
    {
        return trim((string) $value);
    }

    private function resolveRegion(string $regionIdRaw, string $regionName): ?Region
    {
        $regionExternalId = $this->normalizeExternalId($regionIdRaw);
        if ($regionExternalId !== '' && Schema::hasColumn('regions', 'external_id')) {
            $region = Region::query()->where('external_id', $regionExternalId)->first();
            if ($region) {
                return $region;
            }
        }

        if ($regionExternalId !== '' && ctype_digit($regionExternalId)) {
            $region = Region::query()->find((int) $regionIdRaw);
            if ($region) {
                return $region;
            }
        }

        if ($regionName !== '') {
            return Region::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($regionName)])
                ->first();
        }

        return null;
    }

    private function resolveOrCreateRegion(string $regionIdRaw, string $regionName): ?Region
    {
        $region = $this->resolveRegion($regionIdRaw, $regionName);
        if ($region) {
            return $region;
        }

        $normalizedRegionName = trim($regionName);
        if ($normalizedRegionName === '' || $regionIdRaw !== '') {
            return null;
        }

        return Region::query()->create([
            'name' => $normalizedRegionName,
        ]);
    }

    private function resolveOrCreateZone(string $zoneIdRaw, string $zoneName, int $regionId): ?Zone
    {
        $zoneExternalId = $this->normalizeExternalId($zoneIdRaw);
        if ($zoneExternalId !== '' && Schema::hasColumn('zones', 'external_id')) {
            $zone = Zone::query()->with('region')->where('external_id', $zoneExternalId)->first();
            if ($zone) {
                if ($zone->region_id === null || (int) $zone->region_id !== $regionId) {
                    $zone->region_id = $regionId;
                    $zone->save();
                }

                return $zone;
            }
        }

        if ($zoneExternalId !== '' && ctype_digit($zoneExternalId)) {
            $zone = Zone::query()->with('region')->find((int) $zoneExternalId);
            if ($zone) {
                if ($zone->region_id === null || (int) $zone->region_id !== $regionId) {
                    $zone->region_id = $regionId;
                    $zone->save();
                }

                return $zone;
            }
        }

        $normalizedZoneName = trim($zoneName);
        if ($normalizedZoneName === '') {
            return null;
        }

        $zone = Zone::query()->with('region')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedZoneName)])
            ->where('region_id', $regionId)
            ->first();
        if ($zone) {
            return $zone;
        }

        return Zone::query()->create([
            'external_id' => $zoneExternalId !== '' ? $zoneExternalId : null,
            'name' => $normalizedZoneName,
            'description' => null,
            'region_id' => $regionId,
        ]);
    }

    private function resolveZone(string $zoneIdRaw, string $zoneName, ?int $regionId = null): ?Zone
    {
        $zone = null;

        $zoneExternalId = $this->normalizeExternalId($zoneIdRaw);
        if ($zoneExternalId !== '' && Schema::hasColumn('zones', 'external_id')) {
            $zone = Zone::query()->with('region')->where('external_id', $zoneExternalId)->first();
            if ($zone) {
                return $zone;
            }
        }

        if ($zoneExternalId !== '' && ctype_digit($zoneExternalId)) {
            $zone = Zone::query()->with('region')->find((int) $zoneExternalId);
            if ($zone) {
                return $zone;
            }
        }

        $normalizedZoneName = trim($zoneName);
        if ($normalizedZoneName === '') {
            return null;
        }

        $query = Zone::query()->with('region')->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedZoneName)]);
        if ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        $zone = $query->first();
        if ($zone) {
            return $zone;
        }

        if ($regionId !== null) {
            $existingByName = Zone::query()
                ->with('region')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedZoneName)])
                ->first();

            if ($existingByName) {
                return $existingByName;
            }

            return Zone::query()->create([
                'region_id' => $regionId,
                'name' => $normalizedZoneName,
                'description' => null,
            ]);
        }

        return null;
    }

    private function resolveOrCreateWoreda(string $woredaIdRaw, string $woredaName, ?int $regionId = null, ?int $zoneId = null): ?Woreda
    {
        $woredaExternalId = $this->normalizeExternalId($woredaIdRaw);
        if ($woredaExternalId !== '' && Schema::hasColumn('woredas', 'external_id')) {
            $query = Woreda::query()->with(['region', 'zone'])->where('external_id', $woredaExternalId);
            if ($zoneId !== null) {
                $query->where('zone_id', $zoneId);
            } elseif ($regionId !== null) {
                $query->where('region_id', $regionId);
            }
            $woreda = $query->first();
            if ($woreda) {
                return $woreda;
            }
        }

        if ($woredaExternalId !== '' && ctype_digit($woredaExternalId)) {
            $woreda = Woreda::query()->with(['region', 'zone'])->find((int) $woredaExternalId);
            if ($woreda) {
                return $woreda;
            }
        }

        $normalizedWoredaName = trim($woredaName);
        if ($normalizedWoredaName === '') {
            return null;
        }

        $query = Woreda::query()->with(['region', 'zone'])->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedWoredaName)]);
        if ($zoneId !== null) {
            $query->where('zone_id', $zoneId);
        } elseif ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        $woreda = $query->first();
        if ($woreda) {
            return $woreda;
        }

        if ($regionId === null && $zoneId === null) {
            return null;
        }

        return Woreda::query()->create([
            'external_id' => $woredaExternalId !== '' ? $woredaExternalId : null,
            'region_id' => $regionId,
            'zone_id' => $zoneId,
            'name' => $normalizedWoredaName,
            'description' => null,
        ]);
    }

    private function resolveWoreda(string $woredaIdRaw, string $woredaName): ?Woreda
    {
        $woredaExternalId = $this->normalizeExternalId($woredaIdRaw);
        if ($woredaExternalId !== '' && Schema::hasColumn('woredas', 'external_id')) {
            $matches = Woreda::query()->with(['region', 'zone'])->where('external_id', $woredaExternalId)->limit(2)->get();
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        if ($woredaExternalId !== '' && ctype_digit($woredaExternalId)) {
            $woreda = Woreda::query()->with(['region', 'zone'])->find((int) $woredaExternalId);
            if ($woreda) {
                return $woreda;
            }
        }

        if ($woredaName !== '') {
            return Woreda::query()
                ->with(['region', 'zone'])
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($woredaName)])
                ->first();
        }

        return null;
    }

    private function resolveOrganization(string $organizationIdRaw, string $organizationName): ?Organization
    {
        $organizationExternalId = $this->normalizeExternalId($organizationIdRaw);
        if ($organizationExternalId !== '' && Schema::hasColumn('organizations', 'external_id')) {
            $organization = Organization::query()->where('external_id', $organizationExternalId)->first();
            if ($organization) {
                return $organization;
            }
        }

        if ($organizationExternalId !== '' && ctype_digit($organizationExternalId)) {
            $organization = Organization::query()->find((int) $organizationExternalId);
            if ($organization) {
                return $organization;
            }
        }

        if ($organizationName !== '') {
            return Organization::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($organizationName)])
                ->first();
        }

        return null;
    }

    private function resolveProfession(string $professionName): ?Profession
    {
        if ($professionName === '') {
            return null;
        }

        return Profession::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($professionName)])
            ->first();
    }

    private function normalizeParticipantGender(string $value): ?string
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            default => null,
        };
    }

    private function validParticipantPhone(string $value): bool
    {
        return preg_match('/^(?=(?:\D*\d){7,15}\D*$)\+?\d[\d\s().-]*\d$/', trim($value)) === 1;
    }

    private function normalizeParticipantBirthData(string $dateOfBirthRaw, string $ageRaw): array
    {
        $normalizedDob = null;
        $normalizedAge = null;
        $dobProvided = $dateOfBirthRaw !== '';
        $ageProvided = $ageRaw !== '';

        if ($dobProvided) {
            try {
                $normalizedDob = Carbon::parse($dateOfBirthRaw)->toDateString();

                if (Carbon::parse($normalizedDob)->isFuture()) {
                    return [
                        'valid' => false,
                        'message' => 'Date of birth cannot be in the future.',
                        'date_of_birth' => null,
                        'age' => null,
                    ];
                }
            } catch (\Throwable) {
                if (! $ageProvided) {
                    return [
                        'valid' => false,
                        'message' => 'Date of birth is invalid.',
                        'date_of_birth' => null,
                        'age' => null,
                    ];
                }
            }
        }

        if ($ageProvided) {
            if (! ctype_digit($ageRaw)) {
                if ($normalizedDob === null) {
                    return [
                        'valid' => false,
                        'message' => 'Age is invalid.',
                        'date_of_birth' => null,
                        'age' => null,
                    ];
                }
            } else {
                $age = (int) $ageRaw;
                if ($age < 0 || $age > 120) {
                    if ($normalizedDob === null) {
                        return [
                            'valid' => false,
                            'message' => 'Age must be between 0 and 120.',
                            'date_of_birth' => null,
                            'age' => null,
                        ];
                    }
                } else {
                    $normalizedAge = $age;
                }
            }
        }

        if ($normalizedDob === null && $normalizedAge === null) {
            return [
                'valid' => false,
                'message' => 'Either date of birth or age is required.',
                'date_of_birth' => null,
                'age' => null,
            ];
        }

        return [
            'valid' => true,
            'message' => null,
            'date_of_birth' => $normalizedDob,
            'age' => $normalizedAge,
        ];
    }
}
