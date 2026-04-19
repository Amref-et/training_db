@extends('layouts.admin')

@section('eyebrow', 'Automation')
@section('title', 'CRUD Builder')
@section('subtitle', 'Create a table and register its admin CRUD without writing code.')

@section('actions')
<a href="{{ route('admin.crud-builders.create') }}" class="btn btn-dark">New Builder</a>
@endsection

@section('content')
<div class="panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Table</th>
                    <th>Path</th>
                    <th>Model</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cruds as $crud)
                    <tr>
                        <td>{{ $crud->plural_label }}</td>
                        <td>{{ $crud->table_name }}</td>
                        <td>/admin/{{ $crud->slug }}</td>
                        <td><code>{{ class_basename($crud->model_class) }}</code></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a class="btn btn-sm btn-outline-primary" href="{{ url('admin/'.$crud->slug) }}">Open CRUD</a>
                                @php($canDeleteCrud = auth()->user()?->hasPermission('crud_builder.delete') || auth()->user()?->hasPermission('crud_builder.create'))
                                @if($canDeleteCrud)
                                    <form method="POST" action="{{ route('admin.crud-builders.destroy', $crud) }}" class="d-inline" onsubmit="return confirm('Delete this CRUD definition and its generated table? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No generated CRUDs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $cruds->links() }}
</div>
@endsection
