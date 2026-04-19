<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Region;
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

        $query = Organization::query()->select(['id', 'name', 'region_id', 'zone_id', 'woreda_id']);

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
                ->find($selectedId);

            if ($selected) {
                $options->prepend($selected);
            }
        }

        return response()->json([
            'options' => $options
                ->unique('id')
                ->values()
                ->map(fn (Organization $organization) => $this->formatSelectOption($organization, 'name', 'id'))
                ->all(),
        ]);
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

            fputcsv($handle, [
                'region',
                'zone',
                'woreda',
                'organization',
                'facility',
                'name',
                'category',
                'type',
                'region_id',
                'region_name',
                'zone_id',
                'zone_name',
                'zone',
                'woreda_id',
                'woreda_name',
                'city_town',
                'phone',
                'fax',
            ]);

            Organization::query()
                ->with(['region', 'zoneDefinition', 'woreda'])
                ->orderBy('id')
                ->chunkById(500, function ($organizations) use ($handle) {
                    foreach ($organizations as $organization) {
                        $zoneName = (string) data_get($organization, 'zoneDefinition.name', (string) $organization->zone);
                        fputcsv($handle, [
                            (string) data_get($organization, 'region.name', ''),
                            $zoneName,
                            (string) data_get($organization, 'woreda.name', ''),
                            (string) $organization->name,
                            (string) $organization->name,
                            (string) $organization->name,
                            (string) $organization->category,
                            (string) $organization->type,
                            $organization->region_id,
                            (string) data_get($organization, 'region.name', ''),
                            $organization->zone_id,
                            $zoneName,
                            $zoneName,
                            $organization->woreda_id,
                            (string) data_get($organization, 'woreda.name', ''),
                            (string) $organization->city_town,
                            (string) $organization->phone,
                            (string) $organization->fax,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, $headers);
    }

    public function importOrganizations(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $config = ResourceRegistry::get('organizations');
        $categoryOptions = $this->choiceLookup($config, 'category');
        $typeOptions = $this->choiceLookup($config, 'type');

        $path = $validated['import_file']->getRealPath();
        try {
            $result = $this->importOrganizationsFromCsv((string) $path, $categoryOptions, $typeOptions);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->audit()->logCustom('Organizations imported', 'organizations.import', [
            'auditable_type' => Organization::class,
            'metadata' => $result,
        ]);

        $successMessage = 'Organization import completed: '.$result['created'].' created, '.$result['updated'].' updated';
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

    public function importOrganizationsFromCsv(string $path, ?array $categoryOptions = null, ?array $typeOptions = null): array
    {
        $categoryOptions ??= $this->choiceLookup(ResourceRegistry::get('organizations'), 'category');
        $typeOptions ??= $this->choiceLookup(ResourceRegistry::get('organizations'), 'type');

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
            $regionsByName = [];
            foreach (Region::query()->get() as $region) {
                $regionsById[(int) $region->id] = $region;
                $regionsByName[mb_strtolower(trim((string) $region->name))] = $region;
            }

            $zonesById = [];
            $zonesByName = [];
            foreach (Zone::query()->get() as $zone) {
                $zonesById[(int) $zone->id] = $zone;
                $zonesByName[mb_strtolower(trim((string) $zone->name))] = $zone;
            }

            $woredasById = [];
            $woredasByScopedKey = [];
            $woredasByName = [];
            foreach (Woreda::query()->get() as $woreda) {
                $woredasById[(int) $woreda->id] = $woreda;
                $nameKey = mb_strtolower(trim((string) $woreda->name));
                $woredasByName[$nameKey] ??= [];
                $woredasByName[$nameKey][] = $woreda;
                if ($woreda->zone_id !== null) {
                    $woredasByScopedKey[$nameKey.'|z:'.(int) $woreda->zone_id] = $woreda;
                }
                if ($woreda->region_id !== null) {
                    $woredasByScopedKey[$nameKey.'|r:'.(int) $woreda->region_id] = $woreda;
                }
            }

            $organizationsByName = [];
            foreach (Organization::query()->get() as $organization) {
                $organizationsByName[mb_strtolower(trim((string) $organization->name))] = $organization;
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

                $rowErrors = [];

                if ($name === '') {
                    $rowErrors[] = 'Organization name is required.';
                }

                $organizationKey = mb_strtolower(trim($name));
                $existing = $name !== '' ? ($organizationsByName[$organizationKey] ?? null) : null;

                $category = $this->matchChoiceValue($rawCategory, $categoryOptions);
                if ($rawCategory !== '' && $category === null) {
                    $rowErrors[] = 'Category is missing or invalid.';
                }
                $category = $category ?? ($existing?->category ?: self::CSV_ORGANIZATION_DEFAULT_CATEGORY);

                $type = $this->matchChoiceValue($rawType, $typeOptions);
                if ($rawType !== '' && $type === null) {
                    $rowErrors[] = 'Type is missing or invalid.';
                }
                $type = $type ?? ($existing?->type ?: self::CSV_ORGANIZATION_DEFAULT_TYPE);

                $region = null;
                if ($regionIdRaw !== '' && ctype_digit($regionIdRaw)) {
                    $region = $regionsById[(int) $regionIdRaw] ?? null;
                }
                if ($region === null && $regionName !== '') {
                    $regionKey = mb_strtolower(trim($regionName));
                    $region = $regionsByName[$regionKey] ?? null;
                    if ($region === null && $regionIdRaw === '') {
                        $region = Region::query()->create(['name' => trim($regionName)]);
                        $regionsById[(int) $region->id] = $region;
                        $regionsByName[$regionKey] = $region;
                    }
                }
                if (($regionIdRaw !== '' || $regionName !== '') && $region === null) {
                    $rowErrors[] = 'Region not found.';
                }

                $zone = null;
                if ($zoneIdRaw !== '' && ctype_digit($zoneIdRaw)) {
                    $zone = $zonesById[(int) $zoneIdRaw] ?? null;
                }
                if ($zone === null && $zoneName !== '') {
                    $zoneKey = mb_strtolower(trim($zoneName));
                    $zone = $zonesByName[$zoneKey] ?? null;
                    if ($zone === null && $region?->id !== null) {
                        $zone = Zone::query()->create([
                            'name' => trim($zoneName),
                            'description' => null,
                            'region_id' => $region->id,
                        ]);
                        $zonesById[(int) $zone->id] = $zone;
                        $zonesByName[$zoneKey] = $zone;
                    }
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

                $woreda = null;
                if ($woredaIdRaw !== '' && ctype_digit($woredaIdRaw)) {
                    $woreda = $woredasById[(int) $woredaIdRaw] ?? null;
                }
                if ($woreda === null && $woredaName !== '') {
                    $woredaKey = mb_strtolower(trim($woredaName));
                    if ($zone?->id !== null && isset($woredasByScopedKey[$woredaKey.'|z:'.$zone->id])) {
                        $woreda = $woredasByScopedKey[$woredaKey.'|z:'.$zone->id];
                    } elseif ($region?->id !== null && isset($woredasByScopedKey[$woredaKey.'|r:'.$region->id])) {
                        $woreda = $woredasByScopedKey[$woredaKey.'|r:'.$region->id];
                    } elseif (count($woredasByName[$woredaKey] ?? []) === 1) {
                        $woreda = $woredasByName[$woredaKey][0];
                    } elseif ($region?->id !== null || $zone?->id !== null) {
                        $woreda = Woreda::query()->create([
                            'name' => trim($woredaName),
                            'description' => null,
                            'region_id' => $region?->id,
                            'zone_id' => $zone?->id,
                        ]);
                        $woredasById[(int) $woreda->id] = $woreda;
                        $woredasByName[$woredaKey] ??= [];
                        $woredasByName[$woredaKey][] = $woreda;
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
                        if ($woreda->zone_id !== null) {
                            $woredasByScopedKey[$woredaKey.'|z:'.(int) $woreda->zone_id] = $woreda;
                        }
                        if ($woreda->region_id !== null) {
                            $woredasByScopedKey[$woredaKey.'|r:'.(int) $woreda->region_id] = $woreda;
                        }
                    }

                    if ($woreda->zone_id !== null) {
                        if ($zone !== null && (int) $zone->id !== (int) $woreda->zone_id) {
                            $rowErrors[] = 'Woreda does not belong to the selected Zone.';
                        }

                        $zone = $zone ?? ($zonesById[(int) $woreda->zone_id] ?? null);
                    } elseif ($zone === null) {
                        $rowErrors[] = 'Selected Woreda has no Zone assigned.';
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

                if ($woreda !== null && $region !== null && (int) $woreda->region_id !== (int) $region->id) {
                    $rowErrors[] = 'Woreda does not belong to the selected Region.';
                }

                if ($zone !== null && $region !== null && (int) $zone->region_id !== (int) $region->id) {
                    $rowErrors[] = 'Zone does not belong to the selected Region.';
                }

                if ($woreda === null && $zone === null) {
                    $rowErrors[] = 'Either Zone or Woreda is required.';
                }

                if (! empty($rowErrors)) {
                    $skipped++;
                    $errors[] = 'Line '.$line.': '.implode(' ', $rowErrors);
                    continue;
                }

                $payload = [
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
                } else {
                    $createdOrganization = Organization::query()->create($payload);
                    $organizationsByName[$organizationKey] = $createdOrganization;
                    $created++;
                }
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
                            (string) $participant->date_of_birth,
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
            }

            if ($email === '') {
                $rowErrors[] = 'Email is required.';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

            $existingByEmail = Participant::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

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
                'email' => $email,
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
            ->mapWithKeys(function (array $field) use ($record) {
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

                if (($field['name'] ?? null) === 'organization_id') {
                    $selectedValue = old($field['name']);
                    if ($selectedValue === null && $record) {
                        $selectedValue = data_get($record, $field['name']);
                    }

                    if ($selectedValue === null || $selectedValue === '') {
                        return [$field['name'] => []];
                    }

                    $selected = $query->whereKey($selectedValue)->first();

                    return [$field['name'] => $selected ? [$this->formatSelectOption($selected, $label, $value)] : []];
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

                return [$field['name'] => $query->get()->map(fn ($item) => $this->formatSelectOption($item, $label, $value))->all()];
            })
            ->all();
    }

    private function formatSelectOption(Model $item, string $label, string $value): array
    {
        $resolvedLabel = data_get($item, $label);

        return [
            'value' => $item->{$value},
            'label' => $resolvedLabel !== null && $resolvedLabel !== ''
                ? (string) $resolvedLabel
                : (string) $item->{$value},
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

    private function resolveRegion(string $regionIdRaw, string $regionName): ?Region
    {
        if ($regionIdRaw !== '' && ctype_digit($regionIdRaw)) {
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

    private function resolveZone(string $zoneIdRaw, string $zoneName, ?int $regionId = null): ?Zone
    {
        $zone = null;

        if ($zoneIdRaw !== '' && ctype_digit($zoneIdRaw)) {
            $zone = Zone::query()->with('region')->find((int) $zoneIdRaw);
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
        if ($woredaIdRaw !== '' && ctype_digit($woredaIdRaw)) {
            $woreda = Woreda::query()->with(['region', 'zone'])->find((int) $woredaIdRaw);
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
            'region_id' => $regionId,
            'zone_id' => $zoneId,
            'name' => $normalizedWoredaName,
            'description' => null,
        ]);
    }

    private function resolveWoreda(string $woredaIdRaw, string $woredaName): ?Woreda
    {
        if ($woredaIdRaw !== '' && ctype_digit($woredaIdRaw)) {
            $woreda = Woreda::query()->with(['region', 'zone'])->find((int) $woredaIdRaw);
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
        if ($organizationIdRaw !== '' && ctype_digit($organizationIdRaw)) {
            $organization = Organization::query()->find((int) $organizationIdRaw);
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

    private function normalizeParticipantBirthData(string $dateOfBirthRaw, string $ageRaw): array
    {
        $normalizedDob = null;
        $normalizedAge = null;
        $dobProvided = $dateOfBirthRaw !== '';
        $ageProvided = $ageRaw !== '';

        if ($dobProvided) {
            try {
                $normalizedDob = Carbon::parse($dateOfBirthRaw)->toDateString();
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
