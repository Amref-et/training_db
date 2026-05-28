@extends('layouts.admin')

@section('eyebrow', 'Access Control')
@section('title', 'Users')
@section('subtitle', 'Manage accounts and assign roles.')

@section('actions')
<div class="d-flex gap-2">
    <a href="{{ route('admin.user-activity-logs.index') }}" class="btn btn-outline-secondary">User Activity Log</a>
    @if(auth()->user()->hasPermission('users.create'))
        <a href="{{ route('admin.users.import-template') }}" class="btn btn-outline-secondary">Import Template</a>
        <a href="{{ route('admin.users.create') }}" class="btn btn-dark">Add User</a>
    @endif
</div>
@endsection

@section('content')
@if(session('user_import_report'))
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>Download the user import result CSV now. It contains generated temporary passwords for the newly created users.</div>
        <a href="{{ session('user_import_report.url') }}" class="btn btn-sm btn-outline-dark">Download Import Result</a>
    </div>
@endif

@if(auth()->user()->hasPermission('users.create'))
    <div class="panel p-4 mb-4">
        <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-8">
                <label class="form-label">Import Users CSV</label>
                <input type="file" name="import_file" class="form-control @error('import_file') is-invalid @enderror" accept=".csv,text/csv,text/plain">
                <div class="form-text">Use columns: name, email, role. The role can be a role name or role ID. Temporary passwords are generated for new users.</div>
                @error('import_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 d-grid">
                <button class="btn btn-outline-secondary" type="submit">Import Users</button>
            </div>
        </form>
    </div>
@endif

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
