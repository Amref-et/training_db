<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpenApiController extends Controller
{
    public function json(Request $request): JsonResponse
    {
        return response()->json(
            $this->spec($request),
            200,
            [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    public function docs(): View
    {
        return view('admin.api-management.docs', [
            'openApiUrl' => route('api.openapi'),
        ]);
    }

    private function spec(Request $request): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name', 'Amref Training Database').' API',
                'version' => 'v1',
                'description' => implode("\n\n", [
                    'OpenAPI documentation for the versioned Amref Training Database API.',
                    'Authenticate with a Sanctum personal access token using the `Authorization: Bearer <token>` header.',
                    'The documented surface targets `/api/v1`. Core resource aliases also exist under `/api`, but DHIS2 export endpoints are versioned only.',
                    'Successful access requires both a valid token ability and the matching in-app permission for the signed-in user.',
                ]),
            ],
            'servers' => [
                [
                    'url' => url('/api/v1'),
                    'description' => 'Primary versioned API server',
                ],
            ],
            'tags' => [
                ['name' => 'Meta', 'description' => 'API discovery and capability metadata.'],
                ['name' => 'Regions', 'description' => 'Region master data. Requires `reference-data:*` token abilities and `regions.*` permissions.'],
                ['name' => 'Zones', 'description' => 'Zone master data. Requires `reference-data:*` token abilities and `zones.*` permissions.'],
                ['name' => 'Woredas', 'description' => 'Woreda master data. Requires `reference-data:*` token abilities and `woredas.*` permissions.'],
                ['name' => 'Organizations', 'description' => 'Organization master data. Requires `reference-data:*` token abilities and `organizations.*` permissions.'],
                ['name' => 'Participants', 'description' => 'Participant records. Requires `participants:*` token abilities and `participants.*` permissions.'],
                ['name' => 'Projects', 'description' => 'Training project master data from `training_organizers`. Requires `reference-data:*` token abilities and `training_organizers.*` permissions.'],
                ['name' => 'Trainings', 'description' => 'Training catalogue records. Requires `reference-data:*` token abilities and `trainings.*` permissions.'],
                ['name' => 'Project Records', 'description' => 'Project tracking records. Requires `reference-data:*` token abilities and `projects.*` permissions.'],
                ['name' => 'Training Events', 'description' => 'Scheduled training events. Requires `training-events:*` token abilities and `training_events.*` permissions.'],
                ['name' => 'Training Rounds', 'description' => 'Workshop rounds linked to training events. Requires `reference-data:*` token abilities and `training_rounds.*` permissions.'],
                ['name' => 'Dashboard', 'description' => 'Dashboard summary metrics. Requires `reference-data:read` token ability and `dashboard.view`.'],
                ['name' => 'DHIS2', 'description' => 'DHIS2-ready export payloads for training events. Requires `dhis2:read` token ability and `training_events.view`.'],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'paths' => $this->paths(),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum token',
                        'description' => 'Use a personal access token created from Admin > API Management.',
                    ],
                ],
                'parameters' => [
                    'PerPage' => $this->queryParameter('per_page', 'integer', 'Page size. Maximum 100.', false, ['default' => 25, 'minimum' => 1, 'maximum' => 100]),
                    'Search' => $this->queryParameter('q', 'string', 'Free-text search query.', false),
                    'RegionIdFilter' => $this->queryParameter('region_id', 'integer', 'Filter by region ID.', false),
                    'ZoneIdFilter' => $this->queryParameter('zone_id', 'integer', 'Filter by zone ID.', false),
                    'WoredaIdFilter' => $this->queryParameter('woreda_id', 'integer', 'Filter by woreda ID.', false),
                    'OrganizationIdFilter' => $this->queryParameter('organization_id', 'integer', 'Filter by organization ID.', false),
                    'TrainingIdFilter' => $this->queryParameter('training_id', 'integer', 'Filter by training ID.', false),
                    'TrainingOrganizerIdFilter' => $this->queryParameter('training_organizer_id', 'integer', 'Filter by project/training organizer ID.', false),
                    'TrainingRegionIdFilter' => $this->queryParameter('training_region_id', 'integer', 'Filter by training region ID.', false),
                    'TrainingEventIdFilter' => $this->queryParameter('training_event_id', 'integer', 'Filter by training event ID.', false),
                    'ParticipantIdFilter' => $this->queryParameter('participant_id', 'integer', 'Filter by participant ID.', false),
                    'ProjectCategoryIdFilter' => $this->queryParameter('project_category_id', 'integer', 'Filter by project category ID.', false),
                    'TrainingCategoryIdFilter' => $this->queryParameter('training_category_id', 'integer', 'Filter by training category ID.', false),
                    'GenderFilter' => $this->queryParameter('gender', 'string', 'Filter by gender.', false, ['enum' => ['male', 'female']]),
                    'OrganizerTypeFilter' => $this->queryParameter('organizer_type', 'string', 'Filter by organizer type.', false, ['enum' => ['The project', 'Subawardee']]),
                    'StatusFilter' => $this->queryParameter('status', 'string', 'Filter by training event status.', false, ['enum' => ['Pending', 'Ongoing', 'Completed', 'Cancelled']]),
                    'StartDateFromFilter' => $this->queryParameter('start_date_from', 'string', 'Filter training events starting on or after this date.', false, ['format' => 'date']),
                    'StartDateToFilter' => $this->queryParameter('start_date_to', 'string', 'Filter training events starting on or before this date.', false, ['format' => 'date']),
                    'RoundNumberFilter' => $this->queryParameter('round_number', 'integer', 'Filter by round number.', false),
                    'Limit' => $this->queryParameter('limit', 'integer', 'Limit DHIS2 export payloads. Maximum 200.', false, ['default' => 50, 'minimum' => 1, 'maximum' => 200]),
                    'RegionIdPath' => $this->pathParameter('regionId', 'integer', 'Region ID.'),
                    'RegionPath' => $this->pathParameter('region', 'integer', 'Region ID.'),
                    'ZonePath' => $this->pathParameter('zone', 'integer', 'Zone ID.'),
                    'WoredaPath' => $this->pathParameter('woreda', 'integer', 'Woreda ID.'),
                    'OrganizationPath' => $this->pathParameter('organization', 'integer', 'Organization ID.'),
                    'ParticipantPath' => $this->pathParameter('participant', 'integer', 'Participant ID.'),
                    'TrainingOrganizerPath' => $this->pathParameter('trainingOrganizer', 'integer', 'Project ID.'),
                    'TrainingPath' => $this->pathParameter('training', 'integer', 'Training ID.'),
                    'ProjectPath' => $this->pathParameter('project', 'integer', 'Project record ID.'),
                    'TrainingEventPath' => $this->pathParameter('trainingEvent', 'integer', 'Training event ID.'),
                    'TrainingRoundPath' => $this->pathParameter('trainingRound', 'integer', 'Training round ID.'),
                ],
                'schemas' => $this->schemas(),
            ],
        ];
    }

    private function paths(): array
    {
        return array_merge(
            [
                '/meta' => [
                    'get' => [
                        'tags' => ['Meta'],
                        'summary' => 'API capability metadata',
                        'description' => 'Returns the current API version, enabled endpoint groups, current authenticated user summary, and feature flags.',
                        'operationId' => 'getMeta',
                        'responses' => [
                            '200' => $this->jsonResponse('API metadata response.', '#/components/schemas/MetaEnvelope'),
                            '401' => $this->errorResponse('Unauthorized'),
                        ],
                    ],
                ],
                '/woredas/region/{regionId}' => [
                    'get' => [
                        'tags' => ['Woredas'],
                        'summary' => 'List woredas by region',
                        'description' => 'Shortcut endpoint for listing woredas within a specific region.',
                        'operationId' => 'listWoredasByRegion',
                        'parameters' => [
                            ['$ref' => '#/components/parameters/RegionIdPath'],
                            ['$ref' => '#/components/parameters/PerPage'],
                            ['$ref' => '#/components/parameters/Search'],
                            ['$ref' => '#/components/parameters/ZoneIdFilter'],
                        ],
                        'responses' => [
                            '200' => $this->jsonResponse('Paginated woreda list.', '#/components/schemas/WoredaCollectionEnvelope'),
                            '401' => $this->errorResponse('Unauthorized'),
                            '403' => $this->errorResponse('Forbidden'),
                        ],
                    ],
                ],
                '/dashboard' => [
                    'get' => [
                        'tags' => ['Dashboard'],
                        'summary' => 'Dashboard summary metrics',
                        'description' => 'Returns dashboard summary metrics using the same filtering rules as the admin dashboard.',
                        'operationId' => 'getDashboardSummary',
                        'responses' => [
                            '200' => $this->jsonResponse('Dashboard summary response.', '#/components/schemas/DashboardEnvelope'),
                            '401' => $this->errorResponse('Unauthorized'),
                            '403' => $this->errorResponse('Forbidden'),
                        ],
                    ],
                ],
                '/integrations/dhis2/training-events' => [
                    'get' => [
                        'tags' => ['DHIS2'],
                        'summary' => 'Export DHIS2 payloads for training events',
                        'description' => 'Returns outbound DHIS2-ready payloads for recent training events.',
                        'operationId' => 'exportDhis2TrainingEvents',
                        'parameters' => [
                            ['$ref' => '#/components/parameters/Limit'],
                        ],
                        'responses' => [
                            '200' => $this->jsonResponse('DHIS2 training event export.', '#/components/schemas/Dhis2TrainingEventExportEnvelope'),
                            '401' => $this->errorResponse('Unauthorized'),
                            '403' => $this->errorResponse('Forbidden'),
                        ],
                    ],
                ],
                '/integrations/dhis2/training-events/{trainingEvent}' => [
                    'get' => [
                        'tags' => ['DHIS2'],
                        'summary' => 'Export a single DHIS2 training event payload',
                        'description' => 'Returns a single training event payload shaped for DHIS2 event ingestion.',
                        'operationId' => 'exportSingleDhis2TrainingEvent',
                        'parameters' => [
                            ['$ref' => '#/components/parameters/TrainingEventPath'],
                        ],
                        'responses' => [
                            '200' => $this->jsonResponse('DHIS2 training event payload.', '#/components/schemas/Dhis2TrainingEventPayload'),
                            '401' => $this->errorResponse('Unauthorized'),
                            '403' => $this->errorResponse('Forbidden'),
                            '404' => $this->errorResponse('Not found'),
                        ],
                    ],
                ],
            ],
            $this->resourcePathSet(
                'Regions',
                'regions',
                'region',
                '#/components/schemas/Region',
                '#/components/schemas/RegionWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                ]
            ),
            $this->resourcePathSet(
                'Zones',
                'zones',
                'zone',
                '#/components/schemas/Zone',
                '#/components/schemas/ZoneWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                    ['$ref' => '#/components/parameters/RegionIdFilter'],
                ]
            ),
            $this->resourcePathSet(
                'Woredas',
                'woredas',
                'woreda',
                '#/components/schemas/Woreda',
                '#/components/schemas/WoredaWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                    ['$ref' => '#/components/parameters/RegionIdFilter'],
                    ['$ref' => '#/components/parameters/ZoneIdFilter'],
                ]
            ),
            $this->resourcePathSet(
                'Organizations',
                'organizations',
                'organization',
                '#/components/schemas/Organization',
                '#/components/schemas/OrganizationWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                    ['$ref' => '#/components/parameters/RegionIdFilter'],
                    ['$ref' => '#/components/parameters/ZoneIdFilter'],
                    ['$ref' => '#/components/parameters/WoredaIdFilter'],
                ]
            ),
            $this->resourcePathSet(
                'Participants',
                'participants',
                'participant',
                '#/components/schemas/Participant',
                '#/components/schemas/ParticipantWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                    ['$ref' => '#/components/parameters/RegionIdFilter'],
                    ['$ref' => '#/components/parameters/ZoneIdFilter'],
                    ['$ref' => '#/components/parameters/WoredaIdFilter'],
                    ['$ref' => '#/components/parameters/OrganizationIdFilter'],
                    ['$ref' => '#/components/parameters/GenderFilter'],
                ]
            ),
            $this->resourcePathSet(
                'Projects',
                'training-organizers',
                'trainingOrganizer',
                '#/components/schemas/TrainingOrganizer',
                '#/components/schemas/TrainingOrganizerWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                ],
                'Project'
            ),
            $this->resourcePathSet(
                'Trainings',
                'trainings',
                'training',
                '#/components/schemas/Training',
                '#/components/schemas/TrainingWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                    ['$ref' => '#/components/parameters/TrainingCategoryIdFilter'],
                ],
                'Training'
            ),
            $this->resourcePathSet(
                'Project Records',
                'projects',
                'project',
                '#/components/schemas/Project',
                '#/components/schemas/ProjectWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                    ['$ref' => '#/components/parameters/ProjectCategoryIdFilter'],
                    ['$ref' => '#/components/parameters/ParticipantIdFilter'],
                ],
                'Project record',
                'multipart/form-data'
            ),
            $this->resourcePathSet(
                'Training Events',
                'training-events',
                'trainingEvent',
                '#/components/schemas/TrainingEvent',
                '#/components/schemas/TrainingEventWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/Search'],
                    ['$ref' => '#/components/parameters/TrainingIdFilter'],
                    ['$ref' => '#/components/parameters/TrainingOrganizerIdFilter'],
                    ['$ref' => '#/components/parameters/TrainingRegionIdFilter'],
                    ['$ref' => '#/components/parameters/OrganizerTypeFilter'],
                    ['$ref' => '#/components/parameters/StatusFilter'],
                    ['$ref' => '#/components/parameters/StartDateFromFilter'],
                    ['$ref' => '#/components/parameters/StartDateToFilter'],
                ],
                'Training event'
            ),
            $this->resourcePathSet(
                'Training Rounds',
                'training-rounds',
                'trainingRound',
                '#/components/schemas/TrainingRound',
                '#/components/schemas/TrainingRoundWrite',
                [
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/TrainingEventIdFilter'],
                    ['$ref' => '#/components/parameters/RoundNumberFilter'],
                ],
                'Training round'
            )
        );
    }

    private function resourcePathSet(
        string $tag,
        string $resourcePath,
        string $parameterKey,
        string $schemaRef,
        string $writeSchemaRef,
        array $listParameters,
        ?string $entityLabel = null,
        string $contentType = 'application/json'
    ): array {
        $entityLabel ??= rtrim($tag, 's');
        $collectionSchemaRef = $this->collectionSchemaReference($schemaRef);

        return [
            '/'.$resourcePath => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'List '.$entityLabel.'s',
                    'operationId' => 'list'.str_replace(' ', '', $tag),
                    'parameters' => $listParameters,
                    'responses' => [
                        '200' => $this->jsonResponse('Paginated '.$entityLabel.' list.', $collectionSchemaRef),
                        '401' => $this->errorResponse('Unauthorized'),
                        '403' => $this->errorResponse('Forbidden'),
                    ],
                ],
                'post' => [
                    'tags' => [$tag],
                    'summary' => 'Create '.$entityLabel,
                    'operationId' => 'create'.str_replace(' ', '', $entityLabel),
                    'requestBody' => $this->requestBody($writeSchemaRef, $contentType),
                    'responses' => [
                        '201' => $this->jsonResponse($entityLabel.' created.', $this->itemSchemaReference($schemaRef)),
                        '401' => $this->errorResponse('Unauthorized'),
                        '403' => $this->errorResponse('Forbidden'),
                        '422' => $this->validationErrorResponse(),
                    ],
                ],
            ],
            '/'.$resourcePath.'/{'.$parameterKey.'}' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'Get '.$entityLabel,
                    'operationId' => 'get'.str_replace(' ', '', $entityLabel),
                    'parameters' => [
                        ['$ref' => '#/components/parameters/'.ucfirst($parameterKey).'Path'],
                    ],
                    'responses' => [
                        '200' => $this->jsonResponse($entityLabel.' response.', $this->itemSchemaReference($schemaRef)),
                        '401' => $this->errorResponse('Unauthorized'),
                        '403' => $this->errorResponse('Forbidden'),
                        '404' => $this->errorResponse('Not found'),
                    ],
                ],
                'put' => [
                    'tags' => [$tag],
                    'summary' => 'Update '.$entityLabel,
                    'operationId' => 'update'.str_replace(' ', '', $entityLabel),
                    'parameters' => [
                        ['$ref' => '#/components/parameters/'.ucfirst($parameterKey).'Path'],
                    ],
                    'requestBody' => $this->requestBody($writeSchemaRef, $contentType),
                    'responses' => [
                        '200' => $this->jsonResponse($entityLabel.' updated.', $this->itemSchemaReference($schemaRef)),
                        '401' => $this->errorResponse('Unauthorized'),
                        '403' => $this->errorResponse('Forbidden'),
                        '404' => $this->errorResponse('Not found'),
                        '422' => $this->validationErrorResponse(),
                    ],
                ],
                'delete' => [
                    'tags' => [$tag],
                    'summary' => 'Delete '.$entityLabel,
                    'operationId' => 'delete'.str_replace(' ', '', $entityLabel),
                    'parameters' => [
                        ['$ref' => '#/components/parameters/'.ucfirst($parameterKey).'Path'],
                    ],
                    'responses' => [
                        '200' => $this->jsonResponse($entityLabel.' deleted.', '#/components/schemas/MessageResponse'),
                        '401' => $this->errorResponse('Unauthorized'),
                        '403' => $this->errorResponse('Forbidden'),
                        '404' => $this->errorResponse('Not found'),
                    ],
                ],
            ],
        ];
    }

    private function requestBody(string $schemaRef, string $contentType = 'application/json'): array
    {
        return [
            'required' => true,
            'content' => [
                $contentType => [
                    'schema' => [
                        '$ref' => $schemaRef,
                    ],
                ],
            ],
        ];
    }

    private function jsonResponse(string $description, string $schemaRef): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => $schemaRef,
                    ],
                ],
            ],
        ];
    }

    private function errorResponse(string $description): array
    {
        return $this->jsonResponse($description, '#/components/schemas/ErrorResponse');
    }

    private function validationErrorResponse(): array
    {
        return $this->jsonResponse('Validation error.', '#/components/schemas/ValidationErrorResponse');
    }

    private function collectionSchemaReference(string $schemaRef): string
    {
        return match ($schemaRef) {
            '#/components/schemas/Region' => '#/components/schemas/RegionCollectionEnvelope',
            '#/components/schemas/Zone' => '#/components/schemas/ZoneCollectionEnvelope',
            '#/components/schemas/Woreda' => '#/components/schemas/WoredaCollectionEnvelope',
            '#/components/schemas/Organization' => '#/components/schemas/OrganizationCollectionEnvelope',
            '#/components/schemas/Participant' => '#/components/schemas/ParticipantCollectionEnvelope',
            '#/components/schemas/TrainingOrganizer' => '#/components/schemas/TrainingOrganizerCollectionEnvelope',
            '#/components/schemas/Training' => '#/components/schemas/TrainingCollectionEnvelope',
            '#/components/schemas/Project' => '#/components/schemas/ProjectCollectionEnvelope',
            '#/components/schemas/TrainingEvent' => '#/components/schemas/TrainingEventCollectionEnvelope',
            '#/components/schemas/TrainingRound' => '#/components/schemas/TrainingRoundCollectionEnvelope',
            default => '#/components/schemas/MessageResponse',
        };
    }

    private function itemSchemaReference(string $schemaRef): string
    {
        return match ($schemaRef) {
            '#/components/schemas/Region' => '#/components/schemas/RegionEnvelope',
            '#/components/schemas/Zone' => '#/components/schemas/ZoneEnvelope',
            '#/components/schemas/Woreda' => '#/components/schemas/WoredaEnvelope',
            '#/components/schemas/Organization' => '#/components/schemas/OrganizationEnvelope',
            '#/components/schemas/Participant' => '#/components/schemas/ParticipantEnvelope',
            '#/components/schemas/TrainingOrganizer' => '#/components/schemas/TrainingOrganizerEnvelope',
            '#/components/schemas/Training' => '#/components/schemas/TrainingEnvelope',
            '#/components/schemas/Project' => '#/components/schemas/ProjectEnvelope',
            '#/components/schemas/TrainingEvent' => '#/components/schemas/TrainingEventEnvelope',
            '#/components/schemas/TrainingRound' => '#/components/schemas/TrainingRoundEnvelope',
            default => '#/components/schemas/MessageResponse',
        };
    }

    private function queryParameter(string $name, string $type, string $description, bool $required = false, array $extraSchema = []): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'required' => $required,
            'description' => $description,
            'schema' => array_merge(['type' => $type], $extraSchema),
        ];
    }

    private function pathParameter(string $name, string $type, string $description): array
    {
        return [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'description' => $description,
            'schema' => [
                'type' => $type,
            ],
        ];
    }

    private function schemas(): array
    {
        return [
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer', 'example' => 1],
                    'per_page' => ['type' => 'integer', 'example' => 25],
                    'total' => ['type' => 'integer', 'example' => 250],
                    'last_page' => ['type' => 'integer', 'example' => 10],
                ],
            ],
            'MessageResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Record deleted.'],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Forbidden'],
                ],
            ],
            'ValidationErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'Region' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string', 'example' => 'Amhara'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'RegionWrite' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                ],
            ],
            'Zone' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'region' => ['$ref' => '#/components/schemas/Region'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'ZoneWrite' => [
                'type' => 'object',
                'required' => ['region_id', 'name'],
                'properties' => [
                    'region_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'description' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'Woreda' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'region' => ['$ref' => '#/components/schemas/Region'],
                    'zone' => ['$ref' => '#/components/schemas/Zone'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'WoredaWrite' => [
                'type' => 'object',
                'required' => ['zone_id', 'name'],
                'properties' => [
                    'region_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'Optional; API derives it from the zone when omitted.'],
                    'zone_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'description' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'Organization' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'category' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'city_town' => ['type' => 'string', 'nullable' => true],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'fax' => ['type' => 'string', 'nullable' => true],
                    'region' => ['$ref' => '#/components/schemas/Region'],
                    'zone' => ['$ref' => '#/components/schemas/Zone'],
                    'woreda' => ['$ref' => '#/components/schemas/Woreda'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'OrganizationWrite' => [
                'type' => 'object',
                'required' => ['name', 'category', 'type'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'category' => ['type' => 'string', 'maxLength' => 255],
                    'type' => ['type' => 'string', 'maxLength' => 255],
                    'region_id' => ['type' => 'integer', 'nullable' => true],
                    'zone_id' => ['type' => 'integer', 'nullable' => true],
                    'woreda_id' => ['type' => 'integer', 'nullable' => true],
                    'city_town' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'phone' => ['type' => 'string', 'nullable' => true, 'maxLength' => 30],
                    'fax' => ['type' => 'string', 'nullable' => true, 'maxLength' => 30],
                ],
            ],
            'Participant' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'participant_code' => ['type' => 'string', 'example' => 'PT-000123'],
                    'name' => ['type' => 'string'],
                    'first_name' => ['type' => 'string'],
                    'father_name' => ['type' => 'string'],
                    'grandfather_name' => ['type' => 'string'],
                    'date_of_birth' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'age' => ['type' => 'integer', 'nullable' => true],
                    'gender' => ['type' => 'string', 'enum' => ['male', 'female']],
                    'home_phone' => ['type' => 'string', 'nullable' => true],
                    'mobile_phone' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'profession' => ['type' => 'string'],
                    'region' => ['$ref' => '#/components/schemas/Region'],
                    'zone' => ['$ref' => '#/components/schemas/Zone'],
                    'woreda' => ['$ref' => '#/components/schemas/Woreda'],
                    'organization' => ['$ref' => '#/components/schemas/Organization'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'ParticipantWrite' => [
                'type' => 'object',
                'required' => [
                    'first_name',
                    'father_name',
                    'grandfather_name',
                    'region_id',
                    'zone_id',
                    'woreda_id',
                    'organization_id',
                    'gender',
                    'mobile_phone',
                    'email',
                    'profession',
                ],
                'properties' => [
                    'first_name' => ['type' => 'string', 'maxLength' => 255],
                    'father_name' => ['type' => 'string', 'maxLength' => 255],
                    'grandfather_name' => ['type' => 'string', 'maxLength' => 255],
                    'date_of_birth' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'age' => ['type' => 'integer', 'nullable' => true, 'minimum' => 0, 'maximum' => 120],
                    'region_id' => ['type' => 'integer'],
                    'zone_id' => ['type' => 'integer'],
                    'woreda_id' => ['type' => 'integer'],
                    'organization_id' => ['type' => 'integer'],
                    'gender' => ['type' => 'string', 'enum' => ['male', 'female']],
                    'home_phone' => ['type' => 'string', 'nullable' => true, 'maxLength' => 30],
                    'mobile_phone' => ['type' => 'string', 'maxLength' => 30],
                    'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 255],
                    'profession' => ['type' => 'string', 'description' => 'Must match an existing profession name.'],
                ],
                'description' => 'Provide either `date_of_birth` or `age`. The application keeps DOB and age in sync with the same logic used in the admin UI.',
            ],
            'TrainingOrganizer' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'project_code' => ['type' => 'string'],
                    'project_name' => ['type' => 'string'],
                    'subawardees' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TrainingOrganizerWrite' => [
                'type' => 'object',
                'required' => ['project_code', 'project_name'],
                'properties' => [
                    'project_code' => ['type' => 'string', 'maxLength' => 255],
                    'project_name' => ['type' => 'string', 'maxLength' => 255],
                    'subawardees' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'maxLength' => 255],
                    ],
                ],
            ],
            'Training' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'modality' => ['type' => 'string', 'enum' => ['Face 2 face', 'Online', 'Blended']],
                    'type' => ['type' => 'string', 'enum' => ['Basic', 'Refresher', 'ToT']],
                    'training_category' => [
                        'type' => 'object',
                        'nullable' => true,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TrainingWrite' => [
                'type' => 'object',
                'required' => ['training_category_id', 'title', 'modality', 'type'],
                'properties' => [
                    'training_category_id' => ['type' => 'integer'],
                    'title' => ['type' => 'string', 'maxLength' => 255],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'modality' => ['type' => 'string', 'enum' => ['Face 2 face', 'Online', 'Blended']],
                    'type' => ['type' => 'string', 'enum' => ['Basic', 'Refresher', 'ToT']],
                ],
            ],
            'Project' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'project_category' => [
                        'type' => 'object',
                        'nullable' => true,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                    'participant_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                    'participants' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                                'participant_code' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'coaching_visit_1' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'coaching_visit_1_notes' => ['type' => 'string', 'nullable' => true],
                    'coaching_visit_2' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'coaching_visit_2_notes' => ['type' => 'string', 'nullable' => true],
                    'coaching_visit_3' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'coaching_visit_3_notes' => ['type' => 'string', 'nullable' => true],
                    'project_file' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'ProjectWrite' => [
                'type' => 'object',
                'required' => ['project_category_id', 'participant_ids', 'title'],
                'properties' => [
                    'project_category_id' => ['type' => 'integer'],
                    'participant_ids' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => ['type' => 'integer'],
                    ],
                    'title' => ['type' => 'string', 'maxLength' => 255],
                    'coaching_visit_1' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'coaching_visit_1_notes' => ['type' => 'string', 'nullable' => true],
                    'coaching_visit_2' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'coaching_visit_2_notes' => ['type' => 'string', 'nullable' => true],
                    'coaching_visit_3' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'coaching_visit_3_notes' => ['type' => 'string', 'nullable' => true],
                    'project_file' => ['type' => 'string', 'format' => 'binary', 'nullable' => true],
                ],
            ],
            'TrainingEvent' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'event_name' => ['type' => 'string'],
                    'organizer_type' => ['type' => 'string', 'enum' => ['The project', 'Subawardee']],
                    'training_city' => ['type' => 'string', 'nullable' => true],
                    'course_venue' => ['type' => 'string', 'nullable' => true],
                    'workshop_count' => ['type' => 'integer', 'nullable' => true],
                    'start_date' => ['type' => 'string', 'format' => 'date'],
                    'end_date' => ['type' => 'string', 'format' => 'date'],
                    'status' => ['type' => 'string', 'enum' => ['Pending', 'Ongoing', 'Completed', 'Cancelled']],
                    'training' => [
                        'type' => 'object',
                        'nullable' => true,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'title' => ['type' => 'string'],
                        ],
                    ],
                    'project' => [
                        'type' => 'object',
                        'nullable' => true,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'project_code' => ['type' => 'string'],
                            'project_name' => ['type' => 'string'],
                        ],
                    ],
                    'subawardee' => [
                        'type' => 'object',
                        'nullable' => true,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                    'training_region' => ['$ref' => '#/components/schemas/Region'],
                    'participants_count' => ['type' => 'integer', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TrainingEventWrite' => [
                'type' => 'object',
                'required' => ['event_name', 'training_id', 'training_organizer_id', 'organizer_type', 'start_date', 'end_date', 'status'],
                'properties' => [
                    'event_name' => ['type' => 'string', 'maxLength' => 255],
                    'training_id' => ['type' => 'integer'],
                    'training_organizer_id' => ['type' => 'integer'],
                    'organizer_type' => ['type' => 'string', 'enum' => ['The project', 'Subawardee']],
                    'project_subawardee_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'Required when `organizer_type` is `Subawardee`.'],
                    'training_region_id' => ['type' => 'integer', 'nullable' => true],
                    'training_city' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'course_venue' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'workshop_count' => ['type' => 'integer', 'nullable' => true, 'minimum' => 1, 'maximum' => 20],
                    'start_date' => ['type' => 'string', 'format' => 'date'],
                    'end_date' => ['type' => 'string', 'format' => 'date'],
                    'status' => ['type' => 'string', 'enum' => ['Pending', 'Ongoing', 'Completed', 'Cancelled']],
                ],
            ],
            'TrainingRound' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'training_event_id' => ['type' => 'integer'],
                    'round_number' => ['type' => 'integer'],
                    'workshop_title' => ['type' => 'string', 'nullable' => true],
                    'round_start_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'round_end_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'training_event' => [
                        'type' => 'object',
                        'nullable' => true,
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'event_name' => ['type' => 'string'],
                        ],
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TrainingRoundWrite' => [
                'type' => 'object',
                'required' => ['training_event_id', 'round_number'],
                'properties' => [
                    'training_event_id' => ['type' => 'integer'],
                    'round_number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    'workshop_title' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'round_start_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'round_end_date' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                ],
            ],
            'MetaEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'version' => ['type' => 'string', 'example' => 'v1'],
                            'auth' => [
                                'type' => 'object',
                                'properties' => [
                                    'guard' => ['type' => 'string', 'example' => 'sanctum'],
                                    'user' => [
                                        'type' => 'object',
                                        'nullable' => true,
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'name' => ['type' => 'string'],
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'roles' => ['type' => 'array', 'items' => ['type' => 'string']],
                                            'permissions' => ['type' => 'array', 'items' => ['type' => 'string']],
                                            'token_abilities' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        ],
                                    ],
                                ],
                            ],
                            'endpoints' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'features' => ['type' => 'object', 'additionalProperties' => ['type' => 'boolean']],
                        ],
                    ],
                ],
            ],
            'DashboardEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                    'summary' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'Dhis2IntegrationSummary' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'nullable' => true],
                    'name' => ['type' => 'string', 'nullable' => true],
                    'provider' => ['type' => 'string', 'nullable' => true],
                    'base_url' => ['type' => 'string', 'nullable' => true],
                    'program_id' => ['type' => 'string', 'nullable' => true],
                    'event_endpoint' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'Dhis2TrainingEventPayload' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Payload generated from the DHIS2 integration mapping and the selected training event.',
            ],
            'Dhis2TrainingEventExportEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'integration' => ['$ref' => '#/components/schemas/Dhis2IntegrationSummary'],
                    'count' => ['type' => 'integer'],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Dhis2TrainingEventPayload'],
                    ],
                ],
            ],
            'RegionEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/Region'),
            'ZoneEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/Zone'),
            'WoredaEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/Woreda'),
            'OrganizationEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/Organization'),
            'ParticipantEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/Participant'),
            'TrainingOrganizerEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/TrainingOrganizer'),
            'TrainingEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/Training'),
            'ProjectEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/Project'),
            'TrainingEventEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/TrainingEvent'),
            'TrainingRoundEnvelope' => $this->itemEnvelopeSchema('#/components/schemas/TrainingRound'),
            'RegionCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/Region'),
            'ZoneCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/Zone'),
            'WoredaCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/Woreda'),
            'OrganizationCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/Organization'),
            'ParticipantCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/Participant'),
            'TrainingOrganizerCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/TrainingOrganizer'),
            'TrainingCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/Training'),
            'ProjectCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/Project'),
            'TrainingEventCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/TrainingEvent'),
            'TrainingRoundCollectionEnvelope' => $this->collectionEnvelopeSchema('#/components/schemas/TrainingRound'),
        ];
    }

    private function itemEnvelopeSchema(string $schemaRef): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    '$ref' => $schemaRef,
                ],
            ],
        ];
    }

    private function collectionEnvelopeSchema(string $schemaRef): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => [
                        '$ref' => $schemaRef,
                    ],
                ],
                'meta' => [
                    '$ref' => '#/components/schemas/PaginationMeta',
                ],
            ],
        ];
    }
}
