@extends('layouts.admin')

@section('eyebrow', 'Admin Settings')
@section('title', '.env Settings')
@section('subtitle', 'Manage selected application environment values from the admin panel.')

@section('actions')
<a href="{{ route('admin.appearance.edit') }}" class="btn btn-outline-secondary">Back to Appearance</a>
@endsection

@section('content')
<div class="alert alert-warning">
    Update values carefully. Invalid configuration can make the application unavailable.
</div>

<div class="panel p-4">
    <form method="POST" action="{{ route('admin.settings.env.update') }}" class="row g-4">
        @csrf
        @method('PUT')

        @foreach($groups as $groupLabel => $fields)
            <div class="col-12">
                <h2 class="h5 mb-3">{{ $groupLabel }}</h2>
                <div class="row g-3">
                    @foreach($fields as $field)
                        @php($key = $field['key'])
                        @php($type = $field['type'] ?? 'text')
                        @php($value = old($key, $currentValues[$key] ?? env($key, '')))
                        <div class="col-md-6">
                            <label class="form-label">{{ $field['label'] }}</label>
                            @if($type === 'select')
                                <select name="{{ $key }}" class="form-select">
                                    @foreach($field['options'] as $option)
                                        <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif($type === 'boolean')
                                <select name="{{ $key }}" class="form-select">
                                    <option value="1" @selected(filter_var($value, FILTER_VALIDATE_BOOLEAN))>true</option>
                                    <option value="0" @selected(!filter_var($value, FILTER_VALIDATE_BOOLEAN))>false</option>
                                </select>
                            @else
                                <input
                                    type="{{ in_array($type, ['url', 'email', 'number'], true) ? $type : 'text' }}"
                                    name="{{ $key }}"
                                    class="form-control"
                                    value="{{ $value }}"
                                >
                            @endif
                            <div class="form-text"><code>{{ $key }}</code></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-dark" type="submit">Save .env Settings</button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

