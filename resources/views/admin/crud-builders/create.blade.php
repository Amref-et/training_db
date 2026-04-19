@extends('layouts.admin')

@section('eyebrow', 'Automation')
@section('title', 'Create CRUD Builder')
@section('subtitle', 'Define a table schema and generate its migration, model, permissions, and admin CRUD.')

@section('content')
<div class="panel p-4">
    <form method="POST" action="{{ route('admin.crud-builders.store') }}" class="row g-4">
        @csrf
        <div class="col-md-4"><label class="form-label">Resource Name</label><input class="form-control" type="text" name="name" value="{{ old('name', 'Contacts') }}"></div>
        <div class="col-md-4"><label class="form-label">Table Name</label><input class="form-control" type="text" name="table_name" value="{{ old('table_name', 'contacts') }}"></div>
        <div class="col-md-4"><label class="form-label">Admin Path Slug</label><input class="form-control" type="text" name="slug" value="{{ old('slug', 'contacts') }}"></div>
        <div class="col-md-6"><label class="form-label">Singular Label</label><input class="form-control" type="text" name="singular_label" value="{{ old('singular_label', 'Contact') }}"></div>
        <div class="col-md-6"><label class="form-label">Plural Label</label><input class="form-control" type="text" name="plural_label" value="{{ old('plural_label', 'Contacts') }}"></div>

        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Columns</h2>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add-field">Add Column</button>
            </div>
            <div id="field-rows" class="d-grid gap-3">
                @php($oldFields = old('fields', [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'string', 'nullable' => false, 'unique' => false, 'show_in_index' => true, 'in_form' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'string', 'nullable' => true, 'unique' => true, 'show_in_index' => true, 'in_form' => true],
                ]))
                @foreach($oldFields as $index => $field)
                    <div class="border rounded-4 p-3 field-row">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3"><label class="form-label">Column Name</label><input class="form-control" type="text" name="fields[{{ $index }}][name]" value="{{ $field['name'] ?? '' }}"></div>
                            <div class="col-md-3"><label class="form-label">Label</label><input class="form-control" type="text" name="fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}"></div>
                            <div class="col-md-2"><label class="form-label">Type</label><select class="form-select" name="fields[{{ $index }}][type]">@foreach(['string','text','integer','bigInteger','decimal','boolean','date','dateTime'] as $type)<option value="{{ $type }}" @selected(($field['type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
                            <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[{{ $index }}][nullable]" value="1" @checked(!empty($field['nullable']))><label class="form-check-label">Null</label></div></div>
                            <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[{{ $index }}][unique]" value="1" @checked(!empty($field['unique']))><label class="form-check-label">Unique</label></div></div>
                            <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[{{ $index }}][show_in_index]" value="1" @checked(!empty($field['show_in_index']))><label class="form-check-label">List</label></div></div>
                            <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[{{ $index }}][in_form]" value="1" @checked(!empty($field['in_form']))><label class="form-check-label">Form</label></div></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-dark" type="submit">Generate Table & CRUD</button>
            <a href="{{ route('admin.crud-builders.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const container = document.getElementById('field-rows');
    const addButton = document.getElementById('add-field');
    let index = container.querySelectorAll('.field-row').length;

    addButton.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'border rounded-4 p-3 field-row';
        row.innerHTML = `
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Column Name</label><input class="form-control" type="text" name="fields[${index}][name]"></div>
                <div class="col-md-3"><label class="form-label">Label</label><input class="form-control" type="text" name="fields[${index}][label]"></div>
                <div class="col-md-2"><label class="form-label">Type</label><select class="form-select" name="fields[${index}][type]"><option value="string">string</option><option value="text">text</option><option value="integer">integer</option><option value="bigInteger">bigInteger</option><option value="decimal">decimal</option><option value="boolean">boolean</option><option value="date">date</option><option value="dateTime">dateTime</option></select></div>
                <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[${index}][nullable]" value="1"><label class="form-check-label">Null</label></div></div>
                <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[${index}][unique]" value="1"><label class="form-check-label">Unique</label></div></div>
                <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[${index}][show_in_index]" value="1" checked><label class="form-check-label">List</label></div></div>
                <div class="col-md-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="fields[${index}][in_form]" value="1" checked><label class="form-check-label">Form</label></div></div>
            </div>`;
        container.appendChild(row);
        index += 1;
    });
})();
</script>
@endsection
