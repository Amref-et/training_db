@extends('layouts.admin')

@section('eyebrow', 'Website')
@section('title', 'Menu Management')
@section('subtitle', 'Manage header menus and submenu items.')

@section('actions')
<div class="d-flex gap-2 flex-wrap">
    <a href="{{ route('admin.menus.create') }}" class="btn btn-dark">Add Menu Item</a>
    <a href="{{ route('admin.sidebar-menus.index') }}" class="btn btn-outline-secondary">Manage Sidebar Menus</a>
</div>
@endsection

@section('content')
<div class="panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Link</th>
                    <th>Target</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($menuItems as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->title }}</td>
                        <td class="text-break">{{ $item->resolvedUrl() }}</td>
                        <td>{{ $item->target }}</td>
                        <td>{{ $item->sort_order }}</td>
                        <td><span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Active' : 'Hidden' }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.menus.edit', $item) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('admin.menus.destroy', $item) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this menu item and its submenu items?')">Delete</button></form>
                        </td>
                    </tr>
                    @foreach($item->children as $child)
                        <tr>
                            <td class="ps-4">-> {{ $child->title }}</td>
                            <td class="text-break">{{ $child->resolvedUrl() }}</td>
                            <td>{{ $child->target }}</td>
                            <td>{{ $child->sort_order }}</td>
                            <td><span class="badge text-bg-{{ $child->is_active ? 'success' : 'secondary' }}">{{ $child->is_active ? 'Active' : 'Hidden' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.menus.edit', $child) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.menus.destroy', $child) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this submenu item?')">Delete</button></form>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No menu items created yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
