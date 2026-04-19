@extends('layouts.admin')

@section('eyebrow', 'Integrations')
@section('title', 'API Docs')
@section('subtitle', 'OpenAPI/Swagger reference for the versioned API, including token auth, filters, payloads, and DHIS2 export routes.')

@section('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
<style>
    .docs-shell {
        background: linear-gradient(180deg, #f8fbfc 0%, #ffffff 100%);
        border-radius: var(--radius-lg);
        border: 1px solid rgba(15, 23, 42, .08);
        overflow: hidden;
    }
    .docs-toolbar {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(15, 23, 42, .08);
        background: linear-gradient(135deg, #0f3d52 0%, #1c6674 100%);
        color: #fff;
    }
    .docs-toolbar code {
        color: #d4f1f9;
    }
    .docs-toolbar .btn {
        border-radius: var(--radius-pill);
    }
    #swagger-ui {
        padding: 1rem;
    }
    #swagger-ui .topbar {
        display: none;
    }
</style>
@endsection

@section('actions')
<div class="d-flex gap-2">
    <a href="{{ route('api.openapi') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">Open JSON Spec</a>
    <a href="{{ route('admin.api-management.index') }}" class="btn btn-dark">Back to API Management</a>
</div>
@endsection

@section('content')
<div class="docs-shell">
    <div class="docs-toolbar d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="fw-semibold mb-1">Swagger UI</div>
            <div class="small text-white-50">
                Authorize with a bearer token created in API Management.
                Server base: <code>{{ url('/api/v1') }}</code>
            </div>
        </div>
        <div class="small text-white-50">
            Use token abilities such as <code>reference-data:read</code>, <code>participants:write</code>, and <code>dhis2:read</code>.
        </div>
    </div>
    <div id="swagger-ui"></div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.SwaggerUIBundle({
        url: @json($openApiUrl),
        dom_id: '#swagger-ui',
        deepLinking: true,
        displayRequestDuration: true,
        docExpansion: 'list',
        filter: true,
        persistAuthorization: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset,
        ],
        layout: 'BaseLayout',
    });
});
</script>
@endsection
