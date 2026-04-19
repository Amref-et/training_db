<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Services\DashboardMetricsService;
use App\Support\PageSectionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', [
            'pages' => ContentPage::query()->latest()->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.form', $this->formViewData(new ContentPage()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $page = ContentPage::create($data);

        $this->ensureSingleHomepage($page);

        return redirect()->route('admin.pages.index')->with('success', 'Page created successfully.');
    }

    public function edit(ContentPage $page): View
    {
        return view('admin.pages.form', $this->formViewData($page));
    }

    public function update(Request $request, ContentPage $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));
        $this->ensureSingleHomepage($page);

        return redirect()->route('admin.pages.index')->with('success', 'Page updated successfully.');
    }

    public function destroy(ContentPage $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted successfully.');
    }

    private function validated(Request $request, ?ContentPage $page = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:content_pages,slug,'.($page?->id ?? 'NULL'),
            'summary' => 'nullable|string',
            'body' => 'nullable|string',
            'sections' => 'nullable|array',
            'sections_payload' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'is_homepage' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
        ]);

        $sections = $this->normalizedSections($request);

        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        $data['summary'] = $this->normalizeRichHtml($data['summary'] ?? null);
        $data['body'] = $this->normalizeRichHtml($data['body'] ?? null);
        $data['is_homepage'] = $request->boolean('is_homepage');
        $data['sections'] = $this->normalizeRichHtml($sections);
        $data['blocks'] = $this->flattenBlocks($data['sections']);
        unset($data['sections_payload']);

        return $data;
    }

    private function formViewData(ContentPage $page): array
    {
        $blockDefinitions = \App\Support\PageBlockRegistry::definitions();
        $dashboardFilterChoices = collect(app(DashboardMetricsService::class)->filterDefinitions())
            ->map(fn (array $definition) => [
                'value' => $definition['key'],
                'label' => $definition['label'],
            ])
            ->values()
            ->all();

        if ($dashboardFilterChoices !== []) {
            foreach ($blockDefinitions['dashboard']['fields'] as $index => $field) {
                if (($field['name'] ?? null) === 'selected_filters') {
                    $blockDefinitions['dashboard']['fields'][$index]['choices'] = $dashboardFilterChoices;
                    break;
                }
            }
        }

        return [
            'page' => $page,
            'blockDefinitions' => $blockDefinitions,
            'sectionStyles' => PageSectionRegistry::styleChoices(),
            'formSections' => PageSectionRegistry::forForm($page->sections ?? [], $page->blocks ?? []),
        ];
    }

    private function ensureSingleHomepage(ContentPage $page): void
    {
        if ($page->is_homepage) {
            ContentPage::query()->whereKeyNot($page->id)->update(['is_homepage' => false]);
        }
    }

    private function normalizedSections(Request $request): array
    {
        try {
            $sections = $request->input('sections');

            if (is_array($sections)) {
                return PageSectionRegistry::normalize($sections);
            }

            return PageSectionRegistry::normalizePayload($request->input('sections_payload'));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'sections' => $e->getMessage(),
            ]);
        }
    }

    private function flattenBlocks(array $sections): array
    {
        return collect($sections)
            ->flatMap(fn (array $section) => $section['blocks'] ?? [])
            ->values()
            ->all();
    }

    private function normalizeRichHtml(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeRichHtml($item);
            }

            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $value = preg_replace('/\s+sandbox=(["\']).*?\1/i', '', $value) ?? $value;

        return preg_replace_callback(
            '/(<iframe\b[^>]*\bsrc=)(["\'])(?:\.\.\/)+(embed\/training-events-calendar[^"\']*)(\2)/i',
            fn (array $matches) => $matches[1].$matches[2].url('/'.ltrim($matches[3], '/')).$matches[4],
            $value
        ) ?? $value;
    }
}
