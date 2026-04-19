@extends('layouts.admin')

@section('eyebrow', 'Access Control')
@section('title', 'Users')
@section('subtitle', 'Manage accounts and assign roles.')

@section('actions')
<div class="d-flex gap-2">
    <a href="{{ route('admin.user-activity-logs.index') }}" class="btn btn-outline-secondary">User Activity Log</a>
    <a href="{{ route('admin.users.create') }}" class="btn btn-dark">Add User</a>
</div>
@endsection

@section('content')
<div class="panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roleNames()->join(', ') ?: '—' }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                            @if(auth()->id() !== $user->id)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')">Delete</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-4">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
@endsection
