@extends('layouts.admin')

@section('eyebrow', 'Admin UI')
@section('title', 'Sidebar Menu Management')
@section('subtitle', 'Manage side navigation groups and submenu links for the admin panel.')

@section('actions')
<div class="d-flex gap-2 flex-wrap">
    <a href="{{ route('admin.sidebar-menus.create') }}" class="btn btn-dark">Add Sidebar Item</a>
    <form method="POST" action="{{ route('admin.sidebar-menus.seed') }}" class="d-inline">@csrf<input type="hidden" name="replace_existing" value="0"><button class="btn btn-outline-secondary" type="submit">Load Suggested Structure</button></form>
    <form method="POST" action="{{ route('admin.sidebar-menus.seed') }}" class="d-inline">@csrf<input type="hidden" name="replace_existing" value="1"><button class="btn btn-outline-danger" type="submit" onclick="return confirm('Replace existing sidebar menu with suggested structure?')">Replace With Suggested</button></form>
</div>
@endsection

@section('content')
@php($isEditingSection = isset($editingSection) && $editingSection)
<div class="panel p-4 mb-4" id="section-create-form">
    <h2 class="h5 mb-3">{{ $isEditingSection ? 'Edit Sidebar Section' : 'Create Sidebar Section' }}</h2>
    <form method="POST" action="{{ $isEditingSection ? route('admin.sidebar-menus.sections.update', $editingSection) : route('admin.sidebar-menus.sections.store') }}" class="row g-3 align-items-end">
        @csrf
        @if($isEditingSection) @method('PUT') @endif
        <div class="col-md-6">
            <label class="form-label">Section Name</label>
            <input type="text" name="name" class="form-control" maxlength="100" placeholder="Training Operations" value="{{ old('name', $isEditingSection ? $editingSection->name : '') }}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Section Order</label>
            <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $isEditingSection ? $editingSection->sort_order : 0) }}">
        </div>
        <div class="col-md-3">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="section_is_active" @checked(old('is_active', $isEditingSection ? $editingSection->is_active : true))>
                <label class="form-check-label" for="section_is_active">Active</label>
            </div>
            <button class="btn btn-dark w-100" type="submit">{{ $isEditingSection ? 'Update Section' : 'Create Section' }}</button>
        </div>
    </form>
    @if($isEditingSection)
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.sidebar-menus.index') }}#section-create-form">Cancel Edit</a>
            <form method="POST" action="{{ route('admin.sidebar-menus.sections.destroy', $editingSection) }}" onsubmit="return confirm('Delete this sidebar section? Items in this section will be reassigned to the default section.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete Section</button>
            </form>
        </div>
    @endif
    @if(($sections ?? collect())->isNotEmpty())
        <div class="mt-3 d-grid gap-2">
            @foreach($sections as $section)
                <div class="d-flex align-items-center justify-content-between gap-2 border rounded px-2 py-1">
                    <span class="small">{{ $section->name }} ({{ $section->sort_order }})</span>
                    <a href="{{ route('admin.sidebar-menus.index', ['edit_section' => $section->id]) }}#section-create-form" class="btn btn-sm btn-outline-primary">Edit</a>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Title</th>
                    <th>Route/URL</th>
                    <th>Permission</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($menuRows as $row)
                    @if(($row['type'] ?? 'item') === 'section')
                        <tr class="table-light">
                            <td colspan="7" class="fw-semibold text-uppercase small">{{ $row['section_title'] }}</td>
                        </tr>
                    @else
                        @php($item = $row['item'])
                        @php($depth = (int) $row['depth'])
                        <tr>
                            <td>
                                @if($depth === 0)
                                    <span class="badge rounded-pill text-bg-light border">{{ $item->section_title ?: 'General' }}</span>
                                @else
                                    <span class="text-secondary">Inherited</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2" style="margin-left: {{ $depth * 1.25 }}rem;">
                                    @if($depth > 0)<span class="text-secondary">-></span>@endif
                                    <span class="{{ $depth === 0 ? 'fw-semibold' : '' }}">{{ $item->title }}</span>
                                </div>
                            </td>
                            <td class="text-break">
                                @if($item->route_name)
                                    <code>{{ $item->route_name }}</code>
                                @elseif($item->url)
                                    {{ $item->url }}
                                @else
                                    <span class="text-secondary">Group only</span>
                                @endif
                            </td>
                            <td>{{ $item->required_permission ?: 'None' }}</td>
                            <td>{{ $item->sort_order }}</td>
                            <td><span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Active' : 'Hidden' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.sidebar-menus.edit', $item) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.sidebar-menus.destroy', $item) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this sidebar menu item?')">Delete</button></form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            No sidebar menu items yet. Use "Load Suggested Structure" to start quickly.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
