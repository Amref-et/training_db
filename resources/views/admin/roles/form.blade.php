@extends('layouts.admin')

@section('eyebrow', 'Access Control')
@section('title', $role->exists ? 'Edit Role' : 'Create Role')
@section('subtitle', 'Choose the permissions this role should carry.')

@section('content')
<div class="panel p-4">
    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="row g-4">
        @csrf
        @if($role->exists) @method('PUT') @endif
        <div class="col-12"><label class="form-label">Role Name</label><input class="form-control" type="text" name="name" value="{{ old('name', $role->name) }}"></div>
        @foreach($permissions as $group => $permissionItems)
            <div class="col-md-6"><div class="border rounded-4 p-3 h-100"><div class="fw-semibold mb-3 text-capitalize">{{ str_replace('_', ' ', $group) }}</div>@foreach($permissionItems as $permission)<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" id="permission_{{ $permission->id }}" @checked(in_array((string) $permission->id, collect(old('permission_ids', $selectedPermissions))->map(fn ($value) => (string) $value)->all(), true))><label class="form-check-label" for="permission_{{ $permission->id }}">{{ $permission->name }}</label></div>@endforeach</div></div>
        @endforeach
        <div class="col-12 d-flex gap-2"><button class="btn btn-dark" type="submit">Save Role</button><a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div>
@endsection
