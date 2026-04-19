@extends('layouts.admin')

@section('eyebrow', 'Website')
@section('title', $menu->exists ? 'Edit Menu Item' : 'Create Menu Item')
@section('subtitle', 'Configure menu links and optional submenu parent.')

@section('content')
<div class="panel p-4">
    <form method="POST" action="{{ $menu->exists ? route('admin.menus.update', $menu) : route('admin.menus.store') }}" class="row g-3">
        @csrf
        @if($menu->exists) @method('PUT') @endif

        <div class="col-md-6">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $menu->title) }}" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Parent Menu (for Submenu)</label>
            <select name="parent_id" class="form-select">
                <option value="">None (Top-level menu)</option>
                @foreach($parentItems as $parent)
                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $menu->parent_id) === (string) $parent->id)>{{ $parent->title }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Linked Page (optional)</label>
            <select name="page_id" class="form-select">
                <option value="">None</option>
                @foreach($pages as $page)
                    <option value="{{ $page->id }}" @selected((string) old('page_id', $menu->page_id) === (string) $page->id)>{{ $page->title }}</option>
                @endforeach
            </select>
            <div class="form-text">If selected, this menu item can use the page URL automatically.</div>
        </div>

        <div class="col-md-6">
            <label class="form-label">Custom URL (optional)</label>
            <input type="text" name="url" class="form-control" value="{{ old('url', $menu->url) }}" placeholder="/pages/about or https://example.com">
            <div class="form-text">Leave empty to use the linked page URL. If both are empty, this menu item becomes `#`.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label">Open Target</label>
            <select name="target" class="form-select">
                <option value="_self" @selected(old('target', $menu->target ?: '_self') === '_self')>Same Tab</option>
                <option value="_blank" @selected(old('target', $menu->target) === '_blank')>New Tab</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', $menu->sort_order ?? 0) }}">
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $menu->exists ? $menu->is_active : true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-dark" type="submit">Save Menu Item</button>
            <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
