@extends('layouts.admin')

@section('eyebrow', 'Access Control')
@section('title', 'Roles')
@section('subtitle', 'Control which CRUD operations each role can perform.')

@section('actions')
<a href="{{ route('admin.roles.create') }}" class="btn btn-dark">Add Role</a>
@endsection

@section('content')
<div class="panel p-4">
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Role</th><th>Users</th><th>Permissions</th><th class="text-end">Actions</th></tr></thead><tbody>
        @forelse($roles as $role)
            <tr><td>{{ $role->name }}</td><td>{{ $role->users->count() }}</td><td>{{ $role->permissions->count() }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.roles.edit', $role) }}">Edit</a><form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this role?')">Delete</button></form></td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-secondary py-4">No roles found.</td></tr>
        @endforelse
    </tbody></table></div>
    {{ $roles->links() }}
</div>
@endsection
