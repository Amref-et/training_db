<?php

namespace App\Http\Controllers;

use App\Models\GeneratedCrud;
use App\Services\CrudBuilderService;
use App\Support\ResourceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrudBuilderController extends Controller
{
    public function index(): View
    {
        return view('admin.crud-builders.index', [
            'cruds' => GeneratedCrud::query()->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('admin.crud-builders.create');
    }

    public function store(Request $request, CrudBuilderService $service): RedirectResponse
    {
        $staticSlugs = collect(ResourceRegistry::staticResources())
            ->pluck('path')
            ->map(fn ($path) => str_replace('/', '-', $path))
            ->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::notIn($staticSlugs), 'unique:generated_cruds,slug'],
            'table_name' => ['required', 'string', 'max:255', Rule::notIn(array_keys(ResourceRegistry::staticResources())), 'unique:generated_cruds,table_name'],
            'singular_label' => ['nullable', 'string', 'max:255'],
            'plural_label' => ['nullable', 'string', 'max:255'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(['string', 'text', 'integer', 'bigInteger', 'decimal', 'boolean', 'date', 'dateTime'])],
            'fields.*.nullable' => ['nullable', 'boolean'],
            'fields.*.unique' => ['nullable', 'boolean'],
            'fields.*.show_in_index' => ['nullable', 'boolean'],
            'fields.*.in_form' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = Str::kebab($data['slug'] ?: $data['table_name']);
        $data['table_name'] = Str::snake($data['table_name']);

        $crud = $service->create($data);

        return redirect('admin/'.$crud->slug)
            ->with('success', 'Table and CRUD generated successfully.');
    }

    public function destroy(Request $request, GeneratedCrud $crud, CrudBuilderService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasPermission('crud_builder.delete') || $user->hasPermission('crud_builder.create')),
            403
        );

        try {
            $service->delete($crud);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'crud_builder' => 'Unable to delete this CRUD right now. '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.crud-builders.index')
            ->with('success', 'CRUD deleted successfully.');
    }
}
