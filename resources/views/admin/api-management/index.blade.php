@extends('layouts.admin')

@section('eyebrow', 'Integrations')
@section('title', 'API Management')
@section('subtitle', 'Manage API access tokens, DHIS2 integration settings, and outbound sync activity.')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="panel p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h2 class="h5 mb-1">OpenAPI / Swagger</h2>
                    <div class="text-secondary">Browse the live versioned API contract, inspect payloads, and test endpoints with bearer tokens.</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.api-management.docs') }}" class="btn btn-dark">Open Swagger UI</a>
                    <a href="{{ route('api.openapi') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">View JSON Spec</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="panel p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">API Access Tokens</h2>
                    <div class="text-secondary">Issue and revoke Sanctum tokens for external systems and service accounts.</div>
                </div>
            </div>

            @if(session('plain_text_token'))
                <div class="alert alert-warning">
                    <div class="fw-semibold mb-2">Copy this token now</div>
                    <textarea class="form-control" rows="3" readonly>{{ session('plain_text_token') }}</textarea>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.api-management.tokens.store') }}" class="row g-3 mb-4">
                @csrf
                <div class="col-lg-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Select user</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Token name</label>
                    <input type="text" name="name" class="form-control" placeholder="DHIS2 Integration Token" required>
                </div>
                <div class="col-lg-2">
                    <label class="form-label">Expires at</label>
                    <input type="datetime-local" name="expires_at" class="form-control">
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Abilities</label>
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="row g-2">
                            @foreach($abilityOptions as $ability => $label)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="abilities[]" value="{{ $ability }}" id="ability_{{ md5($ability) }}">
                                        <label class="form-check-label" for="ability_{{ md5($ability) }}">{{ $label }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-dark">Create Token</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>User</th>
                            <th>Abilities</th>
                            <th>Last Used</th>
                            <th>Expires</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tokens as $token)
                            <tr>
                                <td>{{ $token->name }}</td>
                                <td>{{ $token->tokenable?->email ?? 'Unknown' }}</td>
                                <td><small>{{ implode(', ', $token->abilities ?? []) ?: '*' }}</small></td>
                                <td>{{ optional($token->last_used_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                <td>{{ optional($token->expires_at)->format('Y-m-d H:i') ?: 'Never' }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.api-management.tokens.destroy', $token) }}" onsubmit="return confirm('Revoke this API token?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No API tokens created yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="panel p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">DHIS2 Integration</h2>
                    <div class="text-secondary">Store connection details, map outgoing fields to DHIS2 data elements, and define org unit rules.</div>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('admin.api-management.dhis2.test') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Test Connection</button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.api-management.dhis2.update') }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-3">
                    <label class="form-label">Enabled</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $integration->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Base URL</label>
                    <input type="url" name="base_url" class="form-control" value="{{ old('base_url', $integration->base_url) }}" placeholder="https://dhis2.example.org">
                </div>
                <div class="col-md-2">
                    <label class="form-label">API Version</label>
                    <input type="text" name="api_version" class="form-control" value="{{ old('api_version', $integration->api_version) }}" placeholder="40">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Auth Type</label>
                    <select name="auth_type" class="form-select">
                        <option value="basic" @selected(old('auth_type', $integration->auth_type) === 'basic')>Basic Auth</option>
                        <option value="bearer" @selected(old('auth_type', $integration->auth_type) === 'bearer')>Bearer Token</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $integration->username) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current secret">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bearer Token</label>
                    <input type="password" name="bearer_token" class="form-control" placeholder="Leave blank to keep current token">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Token URL</label>
                    <input type="url" name="token_url" class="form-control" value="{{ old('token_url', $integration->token_url) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Event Endpoint</label>
                    <input type="text" name="event_endpoint" class="form-control" value="{{ old('event_endpoint', $integration->event_endpoint) }}" placeholder="/api/events">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Program ID</label>
                    <input type="text" name="program_id" class="form-control" value="{{ old('program_id', $integration->program_id) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Org Unit Strategy</label>
                    <select name="org_unit_strategy" class="form-select">
                        <option value="default" @selected(old('org_unit_strategy', $integration->setting('org_unit_strategy', 'default')) === 'default')>Default org unit</option>
                        <option value="region_map" @selected(old('org_unit_strategy', $integration->setting('org_unit_strategy')) === 'region_map')>Map by training region</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Default Org Unit</label>
                    <input type="text" name="default_org_unit" class="form-control" value="{{ old('default_org_unit', $integration->setting('default_org_unit')) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Org Unit Map JSON</label>
                    <textarea name="org_unit_map" class="form-control font-monospace" rows="4" placeholder='{"1":"DHIS2_ORG_UNIT_ID","Amhara":"DHIS2_ORG_UNIT_ID"}'>{{ old('org_unit_map', json_encode($integration->setting('org_unit_map', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Default Headers JSON</label>
                    <textarea name="default_headers" class="form-control font-monospace" rows="3" placeholder='{"X-Requested-With":"AmrefTrainingDatabase"}'>{{ old('default_headers', json_encode($integration->setting('default_headers', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Data Element Mapping JSON</label>
                    <textarea name="mappings" class="form-control font-monospace" rows="8" placeholder='{"event_name":"de123","participant_count":"de456"}'>{{ old('mappings', json_encode($integration->mappings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="small text-secondary">
                        Last tested:
                        {{ optional($integration->last_tested_at)->format('Y-m-d H:i:s') ?: 'Never' }}
                        @if($integration->last_test_status)
                            | Status: <span class="{{ $integration->last_test_status === 'success' ? 'text-success' : 'text-danger' }}">{{ ucfirst($integration->last_test_status) }}</span>
                        @endif
                        @if($integration->last_error)
                            | Error: {{ $integration->last_error }}
                        @endif
                    </div>
                    <button type="submit" class="btn btn-dark">Save DHIS2 Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="panel p-4 mb-4">
            <h2 class="h5 mb-3">Manual DHIS2 Sync</h2>
            <form method="GET" action="{{ route('admin.api-management.dhis2.preview', ['trainingEvent' => 0]) }}" class="row g-3 mb-3" id="payload-preview-form">
                <div class="col-12">
                    <label class="form-label">Training Event</label>
                    <select class="form-select" id="payload-preview-event">
                        <option value="">Select training event</option>
                        @foreach($trainingEvents as $event)
                            <option value="{{ $event->id }}">{{ $event->event_name ?: ($event->training?->title ?? 'Event #'.$event->id) }} | {{ $event->trainingOrganizer?->project_name ?: $event->trainingOrganizer?->title ?: 'No project' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="preview-payload-button">Preview Payload</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.api-management.dhis2.sync') }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <label class="form-label">Training Event</label>
                    <select name="training_event_id" class="form-select" required>
                        <option value="">Select training event</option>
                        @foreach($trainingEvents as $event)
                            <option value="{{ $event->id }}">{{ $event->event_name ?: ($event->training?->title ?? 'Event #'.$event->id) }} | {{ $event->trainingOrganizer?->project_name ?: $event->trainingOrganizer?->title ?: 'No project' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Sync Event to DHIS2</button>
                </div>
            </form>
        </div>

        <div class="panel p-4">
            <h2 class="h5 mb-3">Recent API Sync Logs</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Status</th>
                            <th>Entity</th>
                            <th>Endpoint</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ optional($log->synced_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $log->status === 'success' ? 'text-bg-success' : ($log->status === 'failed' ? 'text-bg-danger' : 'text-bg-secondary') }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td>{{ class_basename((string) $log->entity_type) }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                                <td>
                                    <div class="small">{{ $log->endpoint ?: '-' }}</div>
                                    @if($log->error_message)
                                        <div class="text-danger small">{{ $log->error_message }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">No API sync logs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const previewButton = document.getElementById('preview-payload-button');
    const previewSelect = document.getElementById('payload-preview-event');
    if (!previewButton || !previewSelect) {
        return;
    }

    const template = @json(route('admin.api-management.dhis2.preview', ['trainingEvent' => '__EVENT__']));
    previewButton.addEventListener('click', function () {
        const eventId = previewSelect.value;
        if (!eventId) {
            alert('Select a training event first.');
            return;
        }

        window.open(template.replace('__EVENT__', eventId), '_blank', 'noopener');
    });
});
</script>
@endsection
