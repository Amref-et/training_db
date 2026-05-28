@extends('layouts.admin')

@section('eyebrow', 'Appearance')
@section('title', $item->exists ? 'Edit FAQ Item' : 'Create FAQ Item')
@section('subtitle', 'Build category, subcategory, question, and answer paths for the public FAB chatbot.')

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

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-dark" type="submit">Save FAQ Item</button>
            <a href="{{ route('admin.fab-faqs.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
