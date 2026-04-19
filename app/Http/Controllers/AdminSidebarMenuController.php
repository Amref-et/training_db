<?php

namespace App\Http\Controllers;

use App\Models\AdminSidebarMenuItem;
use App\Models\AdminSidebarMenuSection;
use App\Support\AdminSidebarMenuDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSidebarMenuController extends Controller
{
    public function index(Request $request): View
    {
        $rootItems = AdminSidebarMenuItem::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('section_sort_order')
            ->orderBy('section_title')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $sections = $this->availableSections();
        $editingSection = null;
        $editingSectionId = (int) $request->query('edit_section');
        if ($editingSectionId > 0) {
            $editingSection = $sections->firstWhere('id', $editingSectionId);
        }

        return view('admin.sidebar-menus.index', [
            'menuRows' => $this->flattenRows($rootItems),
            'hasItems' => $rootItems->isNotEmpty(),
            'sections' => $sections,
            'editingSection' => $editingSection,
        ]);
    }

    public function create(): View
    {
        return view('admin.sidebar-menus.form', $this->formData(new AdminSidebarMenuItem()));
    }

    public function store(Request $request): RedirectResponse
    {
        $menu = AdminSidebarMenuItem::query()->create($this->validated($request));
        $this->syncChildrenSection($menu);
        $menu->refresh();
        $this->audit()->logModelCreated($menu, 'Admin sidebar item created');

        return redirect()->route('admin.sidebar-menus.index')->with('success', 'Sidebar menu item created successfully.');
    }

    public function edit(AdminSidebarMenuItem $sidebarMenu): View
    {
        return view('admin.sidebar-menus.form', $this->formData($sidebarMenu));
    }

    public function update(Request $request, AdminSidebarMenuItem $sidebarMenu): RedirectResponse
    {
        $beforeState = $this->audit()->snapshotModel($sidebarMenu);
        $sidebarMenu->update($this->validated($request, $sidebarMenu));
        $this->syncChildrenSection($sidebarMenu->fresh());
        $sidebarMenu->refresh();
        $this->audit()->logModelUpdated($sidebarMenu, $beforeState, 'Admin sidebar item updated');

        return redirect()->route('admin.sidebar-menus.index')->with('success', 'Sidebar menu item updated successfully.');
    }

    public function destroy(AdminSidebarMenuItem $sidebarMenu): RedirectResponse
    {
        $beforeState = $this->audit()->snapshotModel($sidebarMenu);
        $menuId = $sidebarMenu->id;
        $menuLabel = $sidebarMenu->title;
        $sidebarMenu->delete();
        $this->audit()->logModelDeleted(AdminSidebarMenuItem::class, $menuId, $menuLabel, $beforeState, 'Admin sidebar item deleted');

        return redirect()->route('admin.sidebar-menus.index')->with('success', 'Sidebar menu item deleted successfully.');
    }

    public function seedSuggested(Request $request): RedirectResponse
    {
        $replace = $request->boolean('replace_existing');
        AdminSidebarMenuDefaults::seedSuggested($replace);
        $this->audit()->logCustom('Suggested admin sidebar seeded', 'sidebar.seeded', [
            'metadata' => ['replace_existing' => $replace],
        ]);

        return redirect()->route('admin.sidebar-menus.index')->with('success', 'Suggested sidebar structure loaded successfully.');
    }

    public function storeSection(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('admin_sidebar_menu_sections')) {
            return redirect()->route('admin.sidebar-menus.index')->with('error', 'Sidebar sections table is not ready yet.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:admin_sidebar_menu_sections,name',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $section = AdminSidebarMenuSection::query()->create([
            'name' => trim((string) $data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $this->audit()->logModelCreated($section, 'Admin sidebar section created');

        return redirect()->route('admin.sidebar-menus.index')->with('success', 'Sidebar section created successfully.');
    }

    public function updateSection(Request $request, AdminSidebarMenuSection $section): RedirectResponse
    {
        if (! Schema::hasTable('admin_sidebar_menu_sections')) {
            return redirect()->route('admin.sidebar-menus.index')->with('error', 'Sidebar sections table is not ready yet.');
        }

        $beforeState = $this->audit()->snapshotModel($section);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('admin_sidebar_menu_sections', 'name')->ignore($section->id)],
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $section->update([
            'name' => trim((string) $data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        AdminSidebarMenuItem::query()
            ->where('section_id', $section->id)
            ->update([
                'section_title' => $section->name,
                'section_sort_order' => $section->sort_order,
                'updated_at' => now(),
            ]);
        $section->refresh();
        $this->audit()->logModelUpdated($section, $beforeState, 'Admin sidebar section updated');

        return redirect()->route('admin.sidebar-menus.index')->with('success', 'Sidebar section updated successfully.');
    }

    public function destroySection(AdminSidebarMenuSection $section): RedirectResponse
    {
        if (! Schema::hasTable('admin_sidebar_menu_sections')) {
            return redirect()->route('admin.sidebar-menus.index')->with('error', 'Sidebar sections table is not ready yet.');
        }

        $deletedSectionName = trim((string) $section->name);
        $beforeState = $this->audit()->snapshotModel($section);
        $sectionId = $section->id;
        $section->delete();

        $fallbackSection = $this->defaultSection();
        if (! $fallbackSection) {
            $fallbackSection = AdminSidebarMenuSection::query()->create([
                'name' => 'General',
                'sort_order' => 0,
                'is_active' => true,
            ]);
        }

        AdminSidebarMenuItem::query()
            ->whereNull('section_id')
            ->update([
                'section_id' => $fallbackSection->id,
                'section_title' => $fallbackSection->name,
                'section_sort_order' => (int) $fallbackSection->sort_order,
                'updated_at' => now(),
            ]);
        $this->audit()->logModelDeleted(AdminSidebarMenuSection::class, $sectionId, $deletedSectionName, $beforeState, 'Admin sidebar section deleted', [
            'fallback_section' => $fallbackSection->name,
        ]);

        return redirect()
            ->route('admin.sidebar-menus.index')
            ->with('success', "Sidebar section \"{$deletedSectionName}\" deleted successfully.");
    }

    private function validated(Request $request, ?AdminSidebarMenuItem $menu = null): array
    {
        $topLevelParentRule = Rule::exists('admin_sidebar_menu_items', 'id')->where(fn ($query) => $query->whereNull('parent_id'));
        $sectionRule = Schema::hasTable('admin_sidebar_menu_sections')
            ? Rule::exists('admin_sidebar_menu_sections', 'id')
            : 'nullable';

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'route_name' => 'nullable|string|max:255',
            'url' => 'nullable|string|max:255',
            'target' => 'required|in:_self,_blank',
            'required_permission' => 'nullable|string|max:255',
            'section_id' => ['nullable', $sectionRule],
            'parent_id' => ['nullable', $topLevelParentRule],
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $data['icon'] = trim((string) ($data['icon'] ?? '')) ?: null;
        $data['route_name'] = trim((string) ($data['route_name'] ?? '')) ?: null;
        $data['url'] = trim((string) ($data['url'] ?? '')) ?: null;
        $data['required_permission'] = trim((string) ($data['required_permission'] ?? '')) ?: null;
        $data['section_id'] = ! empty($data['section_id']) ? (int) $data['section_id'] : null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        if ($menu && (int) ($data['parent_id'] ?? 0) === (int) $menu->id) {
            $data['parent_id'] = null;
        }

        if (! empty($data['parent_id'])) {
            $parent = AdminSidebarMenuItem::query()->find($data['parent_id']);
            $data['section_id'] = $parent?->section_id;
            $data['section_title'] = $parent?->section_title ?: 'General';
            $data['section_sort_order'] = (int) ($parent?->section_sort_order ?? 0);
        } else {
            $section = $data['section_id']
                ? AdminSidebarMenuSection::query()->find($data['section_id'])
                : $this->defaultSection();

            if ($section) {
                $data['section_id'] = $section->id;
                $data['section_title'] = $section->name;
                $data['section_sort_order'] = (int) $section->sort_order;
            } else {
                $data['section_id'] = null;
                $data['section_title'] = 'General';
                $data['section_sort_order'] = 0;
            }
        }

        return $data;
    }

    private function formData(AdminSidebarMenuItem $menu): array
    {
        $parentItems = AdminSidebarMenuItem::query()
            ->whereNull('parent_id')
            ->when($menu->exists, fn ($query) => $query->whereKeyNot($menu->id))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $sections = $this->availableSections();

        return [
            'menu' => $menu,
            'parentItems' => $parentItems,
            'sections' => $sections,
        ];
    }

    private function flattenRows($rootItems): array
    {
        $rows = [];
        $currentSection = null;

        foreach ($rootItems as $item) {
            $sectionTitle = trim((string) ($item->section_title ?: 'General'));
            if ($sectionTitle !== $currentSection) {
                $rows[] = ['type' => 'section', 'section_title' => $sectionTitle];
                $currentSection = $sectionTitle;
            }

            $rows[] = ['type' => 'item', 'item' => $item, 'depth' => 0];

            foreach ($item->children as $child) {
                $rows[] = ['type' => 'item', 'item' => $child, 'depth' => 1];
            }
        }

        return $rows;
    }

    private function syncChildrenSection(?AdminSidebarMenuItem $menu): void
    {
        if (! $menu || $menu->parent_id) {
            return;
        }

        $menu->children()->update([
            'section_id' => $menu->section_id,
            'section_title' => $menu->section_title ?: 'General',
            'section_sort_order' => (int) ($menu->section_sort_order ?? 0),
        ]);
    }

    private function availableSections()
    {
        if (! Schema::hasTable('admin_sidebar_menu_sections')) {
            return collect();
        }

        return AdminSidebarMenuSection::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function defaultSection(): ?AdminSidebarMenuSection
    {
        $sections = $this->availableSections();
        if ($sections->isEmpty()) {
            return null;
        }

        $general = $sections->first(fn ($section) => mb_strtolower(trim((string) $section->name)) === 'general');

        return $general ?: $sections->first();
    }
}
