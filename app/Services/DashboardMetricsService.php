<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Project;
use App\Models\Region;
use App\Models\Training;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingEventWorkshopScore;
use App\Models\TrainingOrganizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DashboardMetricsService
{
    private const FILTER_DEFINITIONS_CACHE_KEY = 'dashboard_metrics.filter_definitions.v2';

    private const FILTER_DEFINITIONS_CACHE_TTL_SECONDS = 600;

    public function summary(array $filters = []): array
    {
        $filterDefinitions = $this->filterDefinitions();
        $filters = $this->resolveFilters($filters, $filterDefinitions);

        $participantsQuery = $this->participantsQuery($filters);
        $enrollmentsQuery = $this->trainingEventEnrollmentsQuery($filters);
        $workshopScoresQuery = $this->workshopScoresQuery($filters);

        $totalParticipants = (clone $participantsQuery)->count();
        $genderTotals = (clone $participantsQuery)
            ->selectRaw("
                SUM(CASE WHEN LOWER(TRIM(gender)) IN ('male', 'm') THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN LOWER(TRIM(gender)) IN ('female', 'f') THEN 1 ELSE 0 END) as female_count
            ")
            ->first();
        $maleParticipants = (int) ($genderTotals->male_count ?? 0);
        $femaleParticipants = (int) ($genderTotals->female_count ?? 0);
        $totalProjects = Project::query()
            ->whereHas('participant', function (Builder $query) use ($filters) {
                $this->applyParticipantFilters($query, $filters);

                if ($this->hasTrainingEventFilters($filters)) {
                    $query->whereHas('trainingEventEnrollments.trainingEvent', function (Builder $eventQuery) use ($filters) {
                        $this->applyTrainingEventFilters($eventQuery, $filters);
                    });
                }
            })
            ->count();

        $avgPreScore = (clone $workshopScoresQuery)->avg('pre_test_score');
        $avgPostScore = (clone $enrollmentsQuery)->avg('final_score');

        $organizerPre = (clone $workshopScoresQuery)
            ->join('training_event_participants', 'training_event_workshop_scores.training_event_participant_id', '=', 'training_event_participants.id')
            ->join('training_events', 'training_event_participants.training_event_id', '=', 'training_events.id')
            ->join('training_organizers', 'training_events.training_organizer_id', '=', 'training_organizers.id')
            ->selectRaw("training_organizers.id as id, COALESCE(NULLIF(training_organizers.project_name, ''), NULLIF(training_organizers.title, ''), CONCAT('Project #', training_organizers.id)) as label, AVG(training_event_workshop_scores.pre_test_score) as avg_pre")
            ->groupByRaw("training_organizers.id, COALESCE(NULLIF(training_organizers.project_name, ''), NULLIF(training_organizers.title, ''), CONCAT('Project #', training_organizers.id))")
            ->get()
            ->keyBy('id');

        $organizerPost = (clone $enrollmentsQuery)
            ->join('training_events', 'training_event_participants.training_event_id', '=', 'training_events.id')
            ->join('training_organizers', 'training_events.training_organizer_id', '=', 'training_organizers.id')
            ->selectRaw("training_organizers.id as id, COALESCE(NULLIF(training_organizers.project_name, ''), NULLIF(training_organizers.title, ''), CONCAT('Project #', training_organizers.id)) as label, AVG(training_event_participants.final_score) as avg_post")
            ->groupByRaw("training_organizers.id, COALESCE(NULLIF(training_organizers.project_name, ''), NULLIF(training_organizers.title, ''), CONCAT('Project #', training_organizers.id))")
            ->get()
            ->keyBy('id');

        $resultsByOrganizer = collect($organizerPre->keys())
            ->merge($organizerPost->keys())
            ->unique()
            ->map(function ($id) use ($organizerPre, $organizerPost) {
                $preRow = $organizerPre->get($id);
                $postRow = $organizerPost->get($id);

                return [
                    'label' => $preRow->label ?? $postRow->label ?? '—',
                    'avg_pre' => round((float) ($preRow->avg_pre ?? 0), 1),
                    'avg_post' => round((float) ($postRow->avg_post ?? 0), 1),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        $regionPre = (clone $workshopScoresQuery)
            ->join('training_event_participants', 'training_event_workshop_scores.training_event_participant_id', '=', 'training_event_participants.id')
            ->join('participants', 'training_event_participants.participant_id', '=', 'participants.id')
            ->join('regions', 'participants.region_id', '=', 'regions.id')
            ->selectRaw('regions.id as id, regions.name as label, AVG(training_event_workshop_scores.pre_test_score) as avg_pre')
            ->groupBy('regions.id', 'regions.name')
            ->get()
            ->keyBy('id');

        $regionPost = (clone $enrollmentsQuery)
            ->join('participants', 'training_event_participants.participant_id', '=', 'participants.id')
            ->join('regions', 'participants.region_id', '=', 'regions.id')
            ->selectRaw('regions.id as id, regions.name as label, AVG(training_event_participants.final_score) as avg_post')
            ->groupBy('regions.id', 'regions.name')
            ->get()
            ->keyBy('id');

        $resultsByRegion = collect($regionPre->keys())
            ->merge($regionPost->keys())
            ->unique()
            ->map(function ($id) use ($regionPre, $regionPost) {
                $preRow = $regionPre->get($id);
                $postRow = $regionPost->get($id);

                return [
                    'label' => $preRow->label ?? $postRow->label ?? '—',
                    'avg_pre' => round((float) ($preRow->avg_pre ?? 0), 1),
                    'avg_post' => round((float) ($postRow->avg_post ?? 0), 1),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        return [
            'filters' => $filters,
            'filterDefinitions' => $filterDefinitions,
            'regions' => Region::query()->orderBy('name')->get(),
            'projects' => TrainingOrganizer::query()->orderBy('project_name')->orderBy('title')->get(),
            'totalParticipants' => $totalParticipants,
            'maleParticipants' => $maleParticipants,
            'femaleParticipants' => $femaleParticipants,
            'totalProjects' => $totalProjects,
            'avgPreScore' => round((float) ($avgPreScore ?? 0), 1),
            'avgPostScore' => round((float) ($avgPostScore ?? 0), 1),
            'resultsByOrganizer' => $resultsByOrganizer,
            'resultsByRegion' => $resultsByRegion,
        ];
    }

    public function filterDefinitions(): array
    {
        return Cache::remember(
            self::FILTER_DEFINITIONS_CACHE_KEY,
            self::FILTER_DEFINITIONS_CACHE_TTL_SECONDS,
            fn () => $this->buildFilterDefinitions()
        );
    }

    public function resolveFilters(array $filters, ?array $definitions = null): array
    {
        $definitions ??= $this->filterDefinitions();

        return collect($definitions)
            ->mapWithKeys(fn (array $definition) => [
                $definition['key'] => trim((string) Arr::get($filters, $definition['key'], '')),
            ])
            ->all();
    }

    public function organizationFilterOptions(string $query = '', mixed $selectedId = null, mixed $regionId = null): array
    {
        $query = trim($query);
        $selectedId = $this->nullableInt($selectedId);
        $regionId = $this->nullableInt($regionId);

        $options = $this->organizationFilterQuery($query, $regionId)
            ->limit($query !== '' || $regionId !== null ? 50 : 20)
            ->get();

        if ($selectedId !== null && ! $options->contains('id', $selectedId)) {
            $selected = Organization::query()
                ->select(['id', 'name', 'region_id'])
                ->find($selectedId);

            if ($selected) {
                $options->prepend($selected);
            }
        }

        return $options
            ->unique('id')
            ->values()
            ->map(fn (Organization $organization) => $this->formatOrganizationOption($organization))
            ->all();
    }

    public function selectedOrganizationFilterOption(mixed $selectedId): ?array
    {
        $selectedId = $this->nullableInt($selectedId);
        if ($selectedId === null) {
            return null;
        }

        $organization = Organization::query()
            ->select(['id', 'name', 'region_id'])
            ->find($selectedId);

        return $organization ? $this->formatOrganizationOption($organization) : null;
    }

    private function participantsQuery(array $filters): Builder
    {
        $query = Participant::query()->with(['region', 'organization']);
        $this->applyParticipantFilters($query, $filters);

        if ($this->hasTrainingEventFilters($filters)) {
            $query->whereHas('trainingEventEnrollments.trainingEvent', function (Builder $eventQuery) use ($filters) {
                $this->applyTrainingEventFilters($eventQuery, $filters);
            });
        }

        return $query;
    }

    private function trainingEventEnrollmentsQuery(array $filters): Builder
    {
        $query = TrainingEventParticipant::query();

        if ($this->hasTrainingEventFilters($filters)) {
            $query->whereHas('trainingEvent', function (Builder $eventQuery) use ($filters) {
                $this->applyTrainingEventFilters($eventQuery, $filters);
            });
        }

        if ($this->hasParticipantFilters($filters)) {
            $query->whereHas('participant', fn (Builder $participantQuery) => $this->applyParticipantFilters($participantQuery, $filters));
        }

        return $query;
    }

    private function workshopScoresQuery(array $filters): Builder
    {
        $query = TrainingEventWorkshopScore::query();

        if ($this->hasTrainingEventFilters($filters)) {
            $query->whereHas('trainingEventParticipant.trainingEvent', function (Builder $eventQuery) use ($filters) {
                $this->applyTrainingEventFilters($eventQuery, $filters);
            });
        }

        if ($this->hasParticipantFilters($filters)) {
            $query->whereHas('trainingEventParticipant.participant', fn (Builder $participantQuery) => $this->applyParticipantFilters($participantQuery, $filters));
        }

        return $query;
    }

    private function applyParticipantFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['gender'])) {
            $query->where('gender', (string) $filters['gender']);
        }

        if (! empty($filters['region_id'])) {
            $query->where('region_id', (int) $filters['region_id']);
        }

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        if (! empty($filters['profession'])) {
            $query->where('profession', (string) $filters['profession']);
        }
    }

    private function applyTrainingEventFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['training_organizer_id'])) {
            $query->where('training_organizer_id', (int) $filters['training_organizer_id']);
        }

        if (! empty($filters['organized_by'])) {
            $this->applyOrganizedByFilter($query, (string) $filters['organized_by']);
        }

        if (! empty($filters['training_id'])) {
            $query->where('training_id', (int) $filters['training_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
    }

    private function hasParticipantFilters(array $filters): bool
    {
        return ! empty($filters['gender'])
            || ! empty($filters['region_id'])
            || ! empty($filters['organization_id'])
            || ! empty($filters['profession']);
    }

    private function hasTrainingEventFilters(array $filters): bool
    {
        return ! empty($filters['training_organizer_id'])
            || ! empty($filters['organized_by'])
            || ! empty($filters['training_id'])
            || ! empty($filters['status']);
    }

    private function organizedByOptions(): array
    {
        return TrainingEvent::query()
            ->with([
                'trainingOrganizer:id,title,project_name',
                'projectSubawardee:id,subawardee_name',
            ])
            ->get()
            ->map(function (TrainingEvent $event) {
                if ($event->organizer_type === 'Subawardee' && $event->projectSubawardee?->subawardee_name) {
                    return [
                        'value' => 'subawardee:'.$event->project_subawardee_id,
                        'label' => $event->projectSubawardee->subawardee_name,
                    ];
                }

                $projectName = $event->trainingOrganizer?->project_name ?: $event->trainingOrganizer?->title;

                if ($projectName && $event->training_organizer_id) {
                    return [
                        'value' => 'project:'.$event->training_organizer_id,
                        'label' => $projectName,
                    ];
                }

                return null;
            })
            ->filter(fn ($option) => is_array($option) && ($option['value'] ?? '') !== '' && ($option['label'] ?? '') !== '')
            ->unique('value')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function buildFilterDefinitions(): array
    {
        return array_values(array_filter([
            $this->selectDefinition(
                'training_organizer_id',
                'Project',
                'All projects',
                TrainingOrganizer::query()->orderBy('project_name')->orderBy('title')->get()
                    ->map(fn (TrainingOrganizer $organizer) => [
                        'value' => (string) $organizer->id,
                        'label' => $organizer->project_name ?: $organizer->title,
                    ])
                    ->all()
            ),
            $this->selectDefinition(
                'organized_by',
                'Organized By',
                'All organizers',
                $this->organizedByOptions()
            ),
            $this->selectDefinition(
                'gender',
                'Gender',
                'All genders',
                Participant::query()->distinct()->orderBy('gender')->pluck('gender')
                    ->filter()
                    ->map(fn ($gender) => [
                        'value' => (string) $gender,
                        'label' => Str::headline((string) $gender),
                    ])
                    ->values()
                    ->all()
            ),
            $this->selectDefinition(
                'region_id',
                'Region',
                'All regions',
                Region::query()->orderBy('name')->get()
                    ->map(fn (Region $region) => [
                        'value' => (string) $region->id,
                        'label' => $region->name,
                    ])
                    ->all()
            ),
            $this->asyncSelectDefinition(
                'organization_id',
                'Organization',
                'All organizations'
            ),
            $this->selectDefinition(
                'profession',
                'Profession',
                'All professions',
                Profession::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->pluck('name')
                    ->map(fn ($professionName) => [
                        'value' => (string) $professionName,
                        'label' => (string) $professionName,
                    ])
                    ->values()
                    ->all()
            ),
            $this->selectDefinition(
                'training_id',
                'Training',
                'All trainings',
                Training::query()->orderBy('title')->get()
                    ->map(fn (Training $training) => [
                        'value' => (string) $training->id,
                        'label' => $training->title,
                    ])
                    ->all()
            ),
            $this->selectDefinition(
                'status',
                'Training Status',
                'All statuses',
                TrainingEvent::query()->distinct()->orderBy('status')->pluck('status')
                    ->filter()
                    ->map(fn ($status) => [
                        'value' => (string) $status,
                        'label' => Str::headline((string) $status),
                    ])
                    ->values()
                    ->all()
            ),
        ]));
    }

    private function applyOrganizedByFilter(Builder $query, string $value): void
    {
        $value = trim($value);

        if (Str::startsWith($value, 'subawardee:')) {
            $subawardeeId = (int) Str::after($value, 'subawardee:');

            if ($subawardeeId > 0) {
                $query->where('organizer_type', 'Subawardee')
                    ->where('project_subawardee_id', $subawardeeId);
            }

            return;
        }

        if (Str::startsWith($value, 'project:')) {
            $projectId = (int) Str::after($value, 'project:');

            if ($projectId > 0) {
                $query->where('training_organizer_id', $projectId)
                    ->where(function (Builder $projectQuery) {
                        $projectQuery->where('organizer_type', 'The project')
                            ->orWhereNull('organizer_type');
                    });
            }
        }
    }

    private function selectDefinition(string $key, string $label, string $allLabel, array $options): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'all_label' => $allLabel,
            'options' => $options,
        ];
    }

    private function asyncSelectDefinition(string $key, string $label, string $allLabel): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'select',
            'all_label' => $allLabel,
            'options' => [],
            'async' => true,
        ];
    }

    private function organizationFilterQuery(string $query, ?int $regionId = null): Builder
    {
        return Organization::query()
            ->select(['id', 'name', 'region_id'])
            ->when($regionId !== null, fn (Builder $builder) => $builder->where('region_id', $regionId))
            ->when($query !== '', fn (Builder $builder) => $builder->where('name', 'like', '%'.$query.'%'))
            ->orderBy('name');
    }

    private function formatOrganizationOption(Organization $organization): array
    {
        return [
            'value' => (string) $organization->id,
            'label' => (string) $organization->name,
            'region_id' => $organization->region_id !== null ? (string) $organization->region_id : '',
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
