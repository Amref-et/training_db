<?php

namespace App\Services;

use App\Models\ApiIntegration;
use App\Models\ApiSyncLog;
use App\Models\TrainingEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Dhis2IntegrationService
{
    public function integration(): ApiIntegration
    {
        return ApiIntegration::dhis2();
    }

    public function testConnection(?ApiIntegration $integration = null): array
    {
        $integration ??= $this->integration();
        $endpoint = $this->absoluteUrl($integration, '/api/system/info');
        $log = $this->startLog($integration, 'system.info', null, $endpoint, [
            'provider' => $integration->provider,
        ]);

        try {
            $response = $this->http($integration)->get($endpoint);
            $payload = $response->json();

            $integration->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => $response->successful() ? 'success' : 'failed',
                'last_error' => $response->successful() ? null : Str::limit((string) $response->body(), 1000),
            ])->save();

            $this->finishLog($log, $response->successful() ? 'success' : 'failed', $payload, $integration->last_error);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'DHIS2 connection succeeded.' : 'DHIS2 connection failed.',
                'data' => is_array($payload) ? $payload : ['body' => Str::limit((string) $response->body(), 2000)],
            ];
        } catch (\Throwable $exception) {
            $integration->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_error' => Str::limit($exception->getMessage(), 1000),
            ])->save();

            $this->finishLog($log, 'failed', null, $exception->getMessage());

            return [
                'success' => false,
                'status' => null,
                'message' => 'DHIS2 connection failed.',
                'data' => ['error' => $exception->getMessage()],
            ];
        }
    }

    public function buildTrainingEventPayload(TrainingEvent $trainingEvent, ?ApiIntegration $integration = null): array
    {
        $integration ??= $this->integration();
        $trainingEvent->loadMissing([
            'training',
            'trainingOrganizer',
            'projectSubawardee',
            'trainingRegion',
            'enrollments.participant.organization',
            'enrollments.workshopScores',
        ]);

        $participantCount = $trainingEvent->enrollments->count();
        $avgFinalScore = $trainingEvent->enrollments->whereNotNull('final_score')->avg('final_score');
        $organizedBy = $trainingEvent->organizer_type === 'Subawardee'
            ? ($trainingEvent->projectSubawardee?->subawardee_name ?: $trainingEvent->trainingOrganizer?->project_name)
            : ($trainingEvent->trainingOrganizer?->project_name ?: $trainingEvent->trainingOrganizer?->title);

        $eventPayload = [
            'program' => $integration->program_id ?: null,
            'orgUnit' => $this->resolveOrgUnit($integration, $trainingEvent),
            'status' => $this->dhis2Status((string) $trainingEvent->status),
            'eventDate' => $trainingEvent->start_date ? Carbon::parse((string) $trainingEvent->start_date)->toDateString() : null,
            'completedDate' => $trainingEvent->end_date ? Carbon::parse((string) $trainingEvent->end_date)->toDateString() : null,
            'storedBy' => 'Amref Training Database',
            'notes' => [],
            'dataValues' => $this->mappedDataValues($integration, [
                'event_name' => $trainingEvent->event_name,
                'training_title' => $trainingEvent->training?->title,
                'project_name' => $trainingEvent->trainingOrganizer?->project_name ?: $trainingEvent->trainingOrganizer?->title,
                'organized_by' => $organizedBy,
                'participant_count' => $participantCount,
                'avg_final_score' => $avgFinalScore !== null ? round((float) $avgFinalScore, 2) : null,
                'status' => $trainingEvent->status,
                'venue' => $trainingEvent->course_venue,
                'city' => $trainingEvent->training_city,
                'workshop_count' => $trainingEvent->workshop_count,
            ]),
        ];

        return [
            'source' => 'amref-training-database',
            'provider' => 'dhis2',
            'generated_at' => now()->toIso8601String(),
            'integration' => [
                'code' => $integration->code,
                'name' => $integration->name,
                'base_url' => $integration->base_url,
                'program_id' => $integration->program_id,
                'event_endpoint' => $integration->event_endpoint,
            ],
            'training_event' => [
                'id' => $trainingEvent->id,
                'event_name' => $trainingEvent->event_name,
                'training' => $trainingEvent->training?->title,
                'project_name' => $trainingEvent->trainingOrganizer?->project_name ?: $trainingEvent->trainingOrganizer?->title,
                'organizer_type' => $trainingEvent->organizer_type,
                'organized_by' => $organizedBy,
                'region' => $trainingEvent->trainingRegion?->name,
                'city' => $trainingEvent->training_city,
                'venue' => $trainingEvent->course_venue,
                'workshop_count' => $trainingEvent->workshop_count,
                'start_date' => $trainingEvent->start_date,
                'end_date' => $trainingEvent->end_date,
                'status' => $trainingEvent->status,
                'participants_count' => $participantCount,
                'avg_final_score' => $avgFinalScore !== null ? round((float) $avgFinalScore, 2) : null,
            ],
            'participants' => $trainingEvent->enrollments
                ->map(function ($enrollment) {
                    $participant = $enrollment->participant;

                    return [
                        'participant_id' => $participant?->id,
                        'participant_code' => $participant?->participant_code,
                        'name' => $participant?->name,
                        'gender' => $participant?->gender,
                        'email' => $participant?->email,
                        'mobile_phone' => $participant?->mobile_phone,
                        'profession' => $participant?->profession,
                        'organization' => $participant?->organization?->name,
                        'final_score' => $enrollment->final_score !== null ? round((float) $enrollment->final_score, 2) : null,
                        'completion_status' => $enrollment->activity_completion_status,
                        'workshop_scores' => $enrollment->workshopScores->map(fn ($score) => [
                            'workshop_number' => $score->workshop_number,
                            'pre_test_score' => $score->pre_test_score,
                            'mid_test_score' => $score->mid_test_score,
                            'post_test_score' => $score->post_test_score,
                        ])->values()->all(),
                    ];
                })
                ->values()
                ->all(),
            'dhis2_event_payload' => $eventPayload,
        ];
    }

    public function syncTrainingEvent(TrainingEvent $trainingEvent, ?ApiIntegration $integration = null): array
    {
        $integration ??= $this->integration();
        $payload = $this->buildTrainingEventPayload($trainingEvent, $integration);
        $endpoint = $this->absoluteUrl($integration, $integration->event_endpoint ?: '/api/events');
        $log = $this->startLog($integration, TrainingEvent::class, $trainingEvent->id, $endpoint, $payload['dhis2_event_payload']);

        try {
            $response = $this->http($integration)->post($endpoint, $payload['dhis2_event_payload']);

            $this->finishLog(
                $log,
                $response->successful() ? 'success' : 'failed',
                $response->json() ?? ['body' => Str::limit((string) $response->body(), 3000)],
                $response->successful() ? null : Str::limit((string) $response->body(), 1000)
            );

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Training event synced to DHIS2.' : 'DHIS2 sync failed.',
                'data' => $response->json() ?? ['body' => Str::limit((string) $response->body(), 3000)],
            ];
        } catch (\Throwable $exception) {
            $this->finishLog($log, 'failed', null, $exception->getMessage());

            return [
                'success' => false,
                'status' => null,
                'message' => 'DHIS2 sync failed.',
                'data' => ['error' => $exception->getMessage()],
            ];
        }
    }

    private function http(ApiIntegration $integration): PendingRequest
    {
        $request = Http::acceptJson()->timeout(30);

        if ($integration->auth_type === 'bearer' && filled($integration->bearer_token)) {
            $request = $request->withToken((string) $integration->bearer_token);
        } elseif ($integration->auth_type === 'basic' && filled($integration->username)) {
            $request = $request->withBasicAuth((string) $integration->username, (string) ($integration->password ?? ''));
        }

        foreach ((array) $integration->setting('default_headers', []) as $header => $value) {
            if (trim((string) $header) === '') {
                continue;
            }

            $request = $request->withHeader((string) $header, (string) $value);
        }

        return $request;
    }

    private function resolveOrgUnit(ApiIntegration $integration, TrainingEvent $trainingEvent): ?string
    {
        $strategy = (string) $integration->setting('org_unit_strategy', 'default');
        $orgUnitMap = (array) $integration->setting('org_unit_map', []);
        $region = $trainingEvent->trainingRegion?->name;
        $regionId = $trainingEvent->training_region_id;

        if ($strategy === 'region_map') {
            if ($regionId !== null && filled($orgUnitMap[(string) $regionId] ?? null)) {
                return (string) $orgUnitMap[(string) $regionId];
            }

            if ($region !== null && filled($orgUnitMap[$region] ?? null)) {
                return (string) $orgUnitMap[$region];
            }
        }

        return $integration->setting('default_org_unit');
    }

    private function mappedDataValues(ApiIntegration $integration, array $values): array
    {
        $mappings = (array) ($integration->mappings ?? []);
        $dataValues = [];

        foreach ($values as $key => $value) {
            $dataElement = trim((string) ($mappings[$key] ?? ''));
            if ($dataElement === '' || $value === null || $value === '') {
                continue;
            }

            $dataValues[] = [
                'dataElement' => $dataElement,
                'value' => is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES),
            ];
        }

        return $dataValues;
    }

    private function dhis2Status(string $status): string
    {
        return match (mb_strtolower(trim($status))) {
            'completed' => 'COMPLETED',
            'ongoing' => 'ACTIVE',
            'cancelled' => 'CANCELLED',
            default => 'SCHEDULE',
        };
    }

    private function absoluteUrl(ApiIntegration $integration, string $path): string
    {
        return rtrim((string) $integration->base_url, '/').'/'.ltrim($path, '/');
    }

    private function startLog(ApiIntegration $integration, ?string $entityType, ?int $entityId, string $endpoint, mixed $requestPayload): ApiSyncLog
    {
        return ApiSyncLog::query()->create([
            'api_integration_id' => $integration->id,
            'direction' => 'outbound',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => 'pending',
            'endpoint' => $endpoint,
            'request_payload' => is_array($requestPayload) ? $requestPayload : ['payload' => $requestPayload],
            'synced_at' => now(),
        ]);
    }

    private function finishLog(ApiSyncLog $log, string $status, mixed $responsePayload, ?string $error): void
    {
        $log->update([
            'status' => $status,
            'response_payload' => is_array($responsePayload) ? $responsePayload : ($responsePayload !== null ? ['payload' => $responsePayload] : null),
            'error_message' => $error !== null ? Str::limit($error, 5000) : null,
            'synced_at' => now(),
        ]);
    }
}
