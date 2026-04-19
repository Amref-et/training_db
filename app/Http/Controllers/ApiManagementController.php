<?php

namespace App\Http\Controllers;

use App\Models\ApiIntegration;
use App\Models\ApiSyncLog;
use App\Models\TrainingEvent;
use App\Models\User;
use App\Services\Dhis2IntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\View\View;

class ApiManagementController extends Controller
{
    public function __construct(private Dhis2IntegrationService $dhis2)
    {
    }

    public function index(): View
    {
        $integration = ApiIntegration::dhis2();

        return view('admin.api-management.index', [
            'integration' => $integration,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'tokens' => PersonalAccessToken::query()->with('tokenable')->latest()->get(),
            'logs' => ApiSyncLog::query()->with('integration')->latest('synced_at')->latest('id')->limit(25)->get(),
            'trainingEvents' => TrainingEvent::query()->with(['training', 'trainingOrganizer'])->orderByDesc('start_date')->limit(100)->get(),
            'abilityOptions' => $this->abilityOptions(),
        ]);
    }

    public function updateDhis2(Request $request): RedirectResponse
    {
        $integration = ApiIntegration::dhis2();
        $beforeState = $this->audit()->snapshotModel($integration);

        $data = $request->validate([
            'base_url' => 'nullable|url|max:255',
            'api_version' => 'nullable|string|max:20',
            'auth_type' => 'required|in:basic,bearer',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'bearer_token' => 'nullable|string',
            'token_url' => 'nullable|url|max:255',
            'event_endpoint' => 'nullable|string|max:255',
            'program_id' => 'nullable|string|max:255',
            'default_org_unit' => 'nullable|string|max:255',
            'org_unit_strategy' => 'required|in:default,region_map',
            'org_unit_map' => 'nullable|string',
            'default_headers' => 'nullable|string',
            'mappings' => 'nullable|string',
        ]);

        $integration->fill([
            'base_url' => $data['base_url'] ?? null,
            'api_version' => $data['api_version'] ?? null,
            'auth_type' => $data['auth_type'],
            'username' => $data['username'] ?? null,
            'token_url' => $data['token_url'] ?? null,
            'event_endpoint' => $data['event_endpoint'] ?? '/api/events',
            'program_id' => $data['program_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->filled('password')) {
            $integration->password = $data['password'];
        }

        if ($request->filled('bearer_token')) {
            $integration->bearer_token = $data['bearer_token'];
        }

        $integration->settings = [
            'default_org_unit' => $data['default_org_unit'] ?? null,
            'org_unit_strategy' => $data['org_unit_strategy'],
            'org_unit_map' => $this->decodeJsonField($data['org_unit_map'] ?? '', 'org_unit_map', 'Org unit map'),
            'default_headers' => $this->decodeJsonField($data['default_headers'] ?? '', 'default_headers', 'Default headers'),
        ];
        $integration->mappings = $this->decodeJsonField($data['mappings'] ?? '', 'mappings', 'Data element mappings');
        $integration->save();

        $integration->refresh();
        $this->audit()->logModelUpdated($integration, $beforeState, 'DHIS2 integration settings updated');

        return redirect()->route('admin.api-management.index')->with('success', 'DHIS2 integration settings updated.');
    }

    public function createToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'required|string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $user = User::query()->findOrFail((int) $data['user_id']);
        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse((string) $data['expires_at']) : null;
        $token = $user->createToken((string) $data['name'], $data['abilities'], $expiresAt);

        $this->audit()->logCustom('API token created', 'api.token.created', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'auditable_label' => $user->email,
            'metadata' => [
                'token_name' => $data['name'],
                'abilities' => $data['abilities'],
                'expires_at' => $expiresAt?->toIso8601String(),
            ],
        ]);

        return redirect()
            ->route('admin.api-management.index')
            ->with('success', 'API token created. Copy it now; it will not be shown again.')
            ->with('plain_text_token', $token->plainTextToken);
    }

    public function destroyToken(PersonalAccessToken $token): RedirectResponse
    {
        $label = $token->name;
        $tokenId = $token->id;
        $userLabel = $token->tokenable instanceof User ? $token->tokenable->email : null;
        $abilities = $token->abilities;
        $token->delete();

        $this->audit()->logCustom('API token revoked', 'api.token.revoked', [
            'auditable_type' => PersonalAccessToken::class,
            'auditable_id' => $tokenId,
            'auditable_label' => $label,
            'old_values' => [
                'token_name' => $label,
                'user' => $userLabel,
                'abilities' => $abilities,
            ],
        ]);

        return redirect()->route('admin.api-management.index')->with('success', 'API token revoked.');
    }

    public function testDhis2(): RedirectResponse
    {
        $result = $this->dhis2->testConnection();

        $this->audit()->logCustom('DHIS2 connection test executed', 'integration.dhis2.tested', [
            'auditable_type' => ApiIntegration::class,
            'auditable_id' => ApiIntegration::dhis2()->id,
            'auditable_label' => 'DHIS2',
            'metadata' => $result,
        ]);

        return redirect()->route('admin.api-management.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function previewTrainingEventPayload(TrainingEvent $trainingEvent)
    {
        $payload = $this->dhis2->buildTrainingEventPayload($trainingEvent);

        $this->audit()->logCustom('DHIS2 payload preview generated', 'integration.dhis2.payload_previewed', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
        ]);

        return response()->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function syncTrainingEvent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'training_event_id' => 'required|exists:training_events,id',
        ]);

        $trainingEvent = TrainingEvent::query()->findOrFail((int) $data['training_event_id']);
        $result = $this->dhis2->syncTrainingEvent($trainingEvent);

        $this->audit()->logCustom('DHIS2 training event sync executed', 'integration.dhis2.event_synced', [
            'auditable_type' => TrainingEvent::class,
            'auditable_id' => $trainingEvent->id,
            'auditable_label' => $trainingEvent->event_name,
            'metadata' => $result,
        ]);

        return redirect()->route('admin.api-management.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    private function abilityOptions(): array
    {
        return [
            'reference-data:read' => 'Reference data read',
            'reference-data:write' => 'Reference data write',
            'participants:read' => 'Participants read',
            'participants:write' => 'Participants write',
            'training-events:read' => 'Training events read',
            'training-events:write' => 'Training events write',
            'dhis2:read' => 'DHIS2 export read',
            'dhis2:write' => 'DHIS2 sync write',
            'sync:execute' => 'Sync execution',
        ];
    }

    private function decodeJsonField(string $value, string $fieldKey, string $fieldLabel): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $fieldKey => $fieldLabel.' must be valid JSON.',
            ]);
        }

        return $decoded;
    }
}
