<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\WebsiteMenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        return view('admin.menus.index', [
            'menuItems' => WebsiteMenuItem::query()
                ->whereNull('parent_id')
                ->with(['page', 'children.page'])
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menus.form', $this->formData(new WebsiteMenuItem()));
    }

    public function store(Request $request): RedirectResponse
    {
        $menu = WebsiteMenuItem::create($this->validated($request));
        $this->audit()->logModelCreated($menu, 'Website menu item created');

        return redirect()->route('admin.menus.index')->with('success', 'Menu item created successfully.');
    }

    public function edit(WebsiteMenuItem $menu): View
    {
        return view('admin.menus.form', $this->formData($menu));
    }

    public function update(Request $request, WebsiteMenuItem $menu): RedirectResponse
    {
        $beforeState = $this->audit()->snapshotModel($menu);
        $menu->update($this->validated($request, $menu));
        $menu->refresh();
        $this->audit()->logModelUpdated($menu, $beforeState, 'Website menu item updated');

        return redirect()->route('admin.menus.index')->with('success', 'Menu item updated successfully.');
    }

    public function destroy(WebsiteMenuItem $menu): RedirectResponse
    {
        $beforeState = $this->audit()->snapshotModel($menu);
        $menuId = $menu->id;
        $menuLabel = $menu->title;
        $menu->delete();
        $this->audit()->logModelDeleted(WebsiteMenuItem::class, $menuId, $menuLabel, $beforeState, 'Website menu item deleted');

        return redirect()->route('admin.menus.index')->with('success', 'Menu item deleted successfully.');
    }

    private function validated(Request $request, ?WebsiteMenuItem $menu = null): array
    {
        $topLevelParentRule = Rule::exists('website_menu_items', 'id')->where(fn ($query) => $query->whereNull('parent_id'));

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'nullable|string|max:255',
            'page_id' => 'nullable|exists:content_pages,id',
            'parent_id' => ['nullable', $topLevelParentRule],
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'target' => 'required|in:_self,_blank',
            'is_active' => 'nullable|boolean',
        ]);

        $data['url'] = trim((string) ($data['url'] ?? ''));
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        if (($data['url'] ?? '') === '' && empty($data['page_id'])) {
            $data['url'] = '#';
        }

        if ($menu && (int) ($data['parent_id'] ?? 0) === (int) $menu->id) {
            $data['parent_id'] = null;
        }

        return $data;
    }

    private function formData(WebsiteMenuItem $menu): array
    {
        $parentItems = WebsiteMenuItem::query()
            ->whereNull('parent_id')
            ->when($menu->exists, fn ($query) => $query->whereKeyNot($menu->id))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return [
            'menu' => $menu,
            'parentItems' => $parentItems,
            'pages' => ContentPage::query()->orderBy('title')->get(),
        ];
    }
}
