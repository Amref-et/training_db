@extends('layouts.admin')

@section('eyebrow', 'CMS')
@section('title', 'Content Pages')
@section('subtitle', 'Manage website pages and homepage content.')

@section('actions')
<a href="{{ route('admin.pages.create') }}" class="btn btn-dark">Add Page</a>
@endsection

@section('content')
<div class="panel p-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Sections</th><th>Blocks</th><th>Homepage</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($pages as $page)
                    @php($sectionCount = count($page->sections ?? []))
                    @php($blockCount = count($page->blocks ?? []))
                    <tr>
                        <td>{{ $page->title }}</td>
                        <td>/pages/{{ $page->slug }}</td>
                        <td><span class="badge text-bg-{{ $page->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($page->status) }}</span></td>
                        <td>{{ $sectionCount }}</td>
                        <td>{{ $blockCount }}</td>
                        <td>{{ $page->is_homepage ? 'Yes' : 'No' }}</td>
                        <td class="text-end">
                            @if($page->status === 'published')<a class="btn btn-sm btn-outline-secondary" href="{{ route('pages.show', $page->slug) }}" target="_blank">View</a>@endif
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.pages.edit', $page) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this page?')">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No pages available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $pages->links() }}
</div>
@endsection