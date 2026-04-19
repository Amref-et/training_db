@extends('layouts.admin')

@section('eyebrow', 'Records')
@section('title', $config['label'])
@section('subtitle', 'Manage '.$config['label'].' records.')

@section('actions')
<div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
    @if($resource === 'organizations' && auth()->user()->hasPermission('organizations.view'))
        <a href="{{ route('admin.organizations.export') }}" class="btn btn-outline-secondary">Export CSV</a>
    @endif
    @if($resource === 'participants' && auth()->user()->hasPermission('participants.view'))
        <a href="{{ route('admin.participants.export') }}" class="btn btn-outline-secondary">Export CSV</a>
    @endif
    @if($resource === 'organizations' && auth()->user()->hasPermission('zones.view'))
        <a href="{{ route('admin.zones.index') }}" class="btn btn-outline-secondary">Zone List</a>
    @endif
    @if($resource === 'organizations' && auth()->user()->hasPermission('organizations.create'))
        <form method="POST" action="{{ route('admin.organizations.import') }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
            @csrf
            <input type="file" name="import_file" class="form-control form-control-sm" accept=".csv,.txt" required>
            <button class="btn btn-outline-dark btn-sm" type="submit">Import CSV</button>
        </form>
    @endif
    @if($resource === 'participants' && auth()->user()->hasPermission('participants.create'))
        <form method="POST" action="{{ route('admin.participants.import') }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
            @csrf
            <input type="file" name="import_file" class="form-control form-control-sm" accept=".csv,.txt" required>
            <button class="btn btn-outline-dark btn-sm" type="submit">Import CSV</button>
        </form>
    @endif
    @if(auth()->user()->hasPermission($config['permission'].'.create'))
        <a href="{{ route('admin.'.$config['path'].'.create') }}" class="btn btn-dark">Add {{ $config['singular'] }}</a>
    @endif
</div>
@endsection

@section('content')
<div class="panel p-4">
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-10"><input type="text" class="form-control" name="q" value="{{ $query }}" placeholder="Search {{ strtolower($config['label']) }}"></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" type="submit">Search</button></div>
    </form>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr>@foreach($config['columns'] as $column)<th>{{ $column['label'] }}</th>@endforeach<th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($records as $record)
                    <tr>
                        @foreach($config['columns'] as $column)
                            @php($value = data_get($record, $column['value']))
                            <td>
                                @if(($column['type'] ?? null) === 'file')
                                    @if($value)
                                        <a href="{{ route('admin.'.$config['path'].'.file', ['record' => $record->getKey(), 'field' => $column['value']]) }}">{{ basename((string) $value) }}</a>
                                    @else
                                        -
                                    @endif
                                @elseif($value instanceof \Illuminate\Support\Carbon)
                                    {{ $value->format('Y-m-d') }}
                                @else
                                    {{ $value ?: '-' }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end">
                            @if(auth()->user()->hasPermission($config['permission'].'.update'))<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.'.$config['path'].'.edit', $record->getKey()) }}">Edit</a>@endif
                            @if(auth()->user()->hasPermission($config['permission'].'.delete'))<form method="POST" action="{{ route('admin.'.$config['path'].'.destroy', $record->getKey()) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this record?')">Delete</button></form>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($config['columns']) + 1 }}" class="text-center text-secondary py-4">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $records->links() }}
</div>
@endsection
