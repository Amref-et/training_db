@extends('layouts.admin')

@section('eyebrow', 'Admin UI')
@section('title', $menu->exists ? 'Edit Sidebar Menu Item' : 'Create Sidebar Menu Item')
@section('subtitle', 'Configure sidebar grouping, submenu, route and permission visibility.')

@section('content')
<div class="panel p-4">
    <form method="POST" action="{{ $menu->exists ? route('admin.sidebar-menus.update', $menu) : route('admin.sidebar-menus.store') }}" class="row g-3">
        @csrf
        @if($menu->exists) @method('PUT') @endif

        <div class="col-md-6">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $menu->title) }}" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Parent Group (optional)</label>
            <select name="parent_id" class="form-select" id="parent_id">
                <option value="">None (Top-level group/item)</option>
                @foreach($parentItems as $parent)
                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $menu->parent_id) === (string) $parent->id)>{{ $parent->title }}</option>
                @endforeach
            </select>
            <div class="form-text">Select a parent to make this a submenu item.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label">Assign Section</label>
            <select name="section_id" id="section_id" class="form-select">
                <option value="">Auto (General)</option>
                @foreach($sections as $section)
                    <option value="{{ $section->id }}" @selected((string) old('section_id', $menu->section_id) === (string) $section->id)>{{ $section->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Used for top-level menu grouping in sidebar.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label d-block">Create Section</label>
            <a class="btn btn-outline-secondary w-100" href="{{ route('admin.sidebar-menus.index') }}#section-create-form">Open Section Create Form</a>
            <div class="form-text">Create a new section first, then assign it here.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label">Icon/Text Prefix (optional)</label>
            <input type="text" name="icon" class="form-control" value="{{ old('icon', $menu->icon) }}" placeholder="chart-line or bi bi-bar-chart-line">
            <div class="form-text">Examples: `chart-line`, `bi-bar-chart-line`, `bi bi-people`, `bi:calendar-event`.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label">Route Name (optional)</label>
            <input type="text" name="route_name" class="form-control" value="{{ old('route_name', $menu->route_name) }}" placeholder="admin.dashboard">
        </div>

        <div class="col-md-4">
            <label class="form-label">Custom URL (optional)</label>
            <input type="text" name="url" class="form-control" value="{{ old('url', $menu->url) }}" placeholder="/admin/training-workflow?step=3">
        </div>

        <div class="col-md-4">
            <label class="form-label">Target</label>
            <select name="target" class="form-select">
                <option value="_self" @selected(old('target', $menu->target ?: '_self') === '_self')>Same Tab</option>
                <option value="_blank" @selected(old('target', $menu->target) === '_blank')>New Tab</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Required Permission (optional)</label>
            <input type="text" name="required_permission" class="form-control" value="{{ old('required_permission', $menu->required_permission) }}" placeholder="training_events.view">
        </div>

        <div class="col-md-4">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', $menu->sort_order ?? 0) }}">
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $menu->exists ? $menu->is_active : true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-dark" type="submit">Save Sidebar Item</button>
            <a href="{{ route('admin.sidebar-menus.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    (() => {
        const parentField = document.getElementById('parent_id');
        const sectionSelectField = document.getElementById('section_id');

        if (!parentField || !sectionSelectField) {
            return;
        }

        const toggleSectionFields = () => {
            const hasParent = parentField.value !== '';
            sectionSelectField.disabled = hasParent;
            if (hasParent) {
                sectionSelectField.dataset.previousValue = sectionSelectField.value;
            } else if (sectionSelectField.dataset.previousValue !== undefined) {
                sectionSelectField.value = sectionSelectField.dataset.previousValue;
            }
        };

        toggleSectionFields();
        parentField.addEventListener('change', toggleSectionFields);
    })();
</script>
@endsection
