@extends('layouts.admin')

@section('eyebrow', 'Appearance')
@section('title', 'FAB Chatbot FAQs')
@section('subtitle', 'Manage the hierarchical FAQ menus shown inside the floating chatbot.')

@section('actions')
<div class="d-flex gap-2">
    <a href="{{ route('admin.appearance.edit') }}#advanced-system" class="btn btn-outline-secondary">Appearance Settings</a>
    <a href="{{ route('admin.fab-faqs.create') }}" class="btn btn-dark">Add FAQ Item</a>
</div>
@endsection

@section('content')
<div class="panel p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="fw-semibold">FAQ hierarchy</div>
            <div class="text-secondary small">Use category items for menus and question items for final answers. Move buttons reorder items within the same parent.</div>
        </div>
        <a href="{{ route('home') }}" target="_blank" class="btn btn-sm btn-outline-secondary">Preview Website</a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Visibility</th>
                    <th>Status</th>
                    <th>Sort</th>
                    <th>Link</th>
                    <th>Answer Preview</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            <span class="text-secondary">{{ str_repeat('-- ', (int) $item->getAttribute('depth')) }}</span>
                            <span class="fw-semibold">{{ $item->title }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $item->type === \App\Models\FabFaqItem::TYPE_QUESTION ? 'text-bg-info' : 'text-bg-secondary' }}">
                                {{ ucfirst($item->type) }}
                            </span>
                        </td>
                        <td>{{ ucfirst($item->visibility ?: \App\Models\FabFaqItem::VISIBILITY_BOTH) }}</td>
                        <td>{{ $item->is_active ? 'Active' : 'Hidden' }}</td>
                        <td>{{ $item->sort_order }}</td>
                        <td class="small">
                            @if($item->link_url)
                                <a href="{{ $item->link_url }}" target="_blank" rel="noopener">{{ $item->link_label ?: 'Open link' }}</a>
                            @else
                                <span class="text-secondary">-</span>
                            @endif
                        </td>
                        <td class="text-secondary small" style="max-width: 28rem;">
                            {{ $item->answer ? \Illuminate\Support\Str::limit($item->answer, 120) : '-' }}
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                <form method="POST" action="{{ route('admin.fab-faqs.move', $item) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Up</button>
                                </form>
                                <form method="POST" action="{{ route('admin.fab-faqs.move', $item) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Down</button>
                                </form>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.fab-faqs.edit', $item) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.fab-faqs.destroy', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete this FAQ item and all child items?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">No FAQ items found. Add a category to start the chatbot menu.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
