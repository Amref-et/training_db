@extends('layouts.admin')

@section('eyebrow', 'Access Control')
@section('title', 'User Activity Log')
@section('subtitle', 'Track admin user activities and actions.')

@section('actions')
<div class="d-flex gap-2">
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Users</a>
</div>
@endsection

@section('content')
<div class="panel p-4">
    <form method="GET" class="row g-3 mb-4">
        <div class="col-lg-4">
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" value="{{ $queryText }}" placeholder="Action, path, route, IP, user...">
        </div>
        <div class="col-lg-3">
            <label class="form-label">User</label>
            <select name="user_id" class="form-select">
                <option value="">All users</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected($selectedUserId === (int) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <label class="form-label">Method</label>
            <select name="method" class="form-select">
                <option value="">All</option>
                @foreach($methods as $method)
                    <option value="{{ $method }}" @selected($selectedMethod === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-1">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="{{ $from }}">
        </div>
        <div class="col-lg-1">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-lg-1 d-grid">
            <button class="btn btn-dark mt-4" type="submit">Filter</button>
        </div>
    </form>

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('admin.user-activity-logs.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Route</th>
                    <th>Path</th>
                    <th>IP</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ optional($log->occurred_at)->format('Y-m-d H:i:s') ?: '-' }}</td>
                        <td>{{ $log->user?->name ?? 'Unknown' }}</td>
                        <td>{{ $log->action ?: '-' }}</td>
                        <td><span class="badge text-bg-light border">{{ $log->method ?: '-' }}</span></td>
                        <td>{{ $log->status_code ?: '-' }}</td>
                        <td><code>{{ $log->route_name ?: '-' }}</code></td>
                        <td><small>{{ $log->path ?: '-' }}</small></td>
                        <td>{{ $log->ip_address ?: '-' }}</td>
                        <td>
                            @if(!empty($log->metadata))
                                <details>
                                    <summary class="small">View</summary>
                                    <pre class="small mb-0">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">No activity logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
