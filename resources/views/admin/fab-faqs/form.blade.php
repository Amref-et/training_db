@extends('layouts.admin')

@section('eyebrow', 'Appearance')
@section('title', $item->exists ? 'Edit FAQ Item' : 'Create FAQ Item')
@section('subtitle', 'Build category, subcategory, question, and answer paths for the FAB chatbot.')

@section('actions')
    <a href="{{ route('admin.fab-faqs.index') }}" class="btn btn-outline-secondary">Back to FAQ Items</a>
@endsection

@section('content')
<div class="panel p-4">
    <form method="POST" action="{{ $item->exists ? route('admin.fab-faqs.update', $item) : route('admin.fab-faqs.store') }}" class="row g-3">
        @csrf
        @if($item->exists)
            @method('PUT')
        @endif

        <div class="col-md-6">
            <label class="form-label">Parent</label>
            <select name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                <option value="">Top level</option>
                @foreach($parentOptions as $option)
                    <option value="{{ $option['id'] }}" @selected((string) old('parent_id', $item->parent_id) === (string) $option['id'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
            <div class="form-text">Only categories can contain child items.</div>
            @error('parent_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select @error('type') is-invalid @enderror">
                @foreach(\App\Models\FabFaqItem::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', $item->type) === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
            @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Visibility</label>
            <select name="visibility" class="form-select @error('visibility') is-invalid @enderror">
                @foreach(\App\Models\FabFaqItem::VISIBILITIES as $visibility)
                    <option value="{{ $visibility }}" @selected(old('visibility', $item->visibility ?: \App\Models\FabFaqItem::VISIBILITY_BOTH) === $visibility)>{{ ucfirst($visibility) }}</option>
                @endforeach
            </select>
            <div class="form-text">Public shows on website pages. Admin shows in the admin console.</div>
            @error('visibility')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Sort Order</label>
            <input type="number" min="0" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
            @error('sort_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-8">
            <label class="form-label">Menu Label / Question</label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $item->title) }}" required>
            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $item->is_active ?? true))>
                <label class="form-check-label" for="is_active">Visible in chatbot</label>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">Answer</label>
            <textarea name="answer" rows="7" class="form-control @error('answer') is-invalid @enderror" placeholder="Required when Type is Question.">{{ old('answer', $item->answer) }}</textarea>
            <div class="form-text">Category answers are ignored. Question answers are shown when a user reaches the final item.</div>
            @error('answer')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-5">
            <label class="form-label">Navigation Link Label</label>
            <input type="text" name="link_label" class="form-control @error('link_label') is-invalid @enderror" value="{{ old('link_label', $item->link_label) }}" placeholder="Open page">
            @error('link_label')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-7">
            <label class="form-label">Navigation Link URL</label>
            <input type="text" name="link_url" class="form-control @error('link_url') is-invalid @enderror" value="{{ old('link_url', $item->link_url) }}" placeholder="/admin/training-workflow?step=5">
            <div class="form-text">Use a relative path like /participant-registration or a full https:// URL.</div>
            @error('link_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-dark" type="submit">Save FAQ Item</button>
            <a href="{{ route('admin.fab-faqs.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
