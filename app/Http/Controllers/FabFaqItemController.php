<?php

namespace App\Http\Controllers;

use App\Models\FabFaqItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FabFaqItemController extends Controller
{
    public function index(): View
    {
        return view('admin.fab-faqs.index', [
            'items' => FabFaqItem::flattened(),
        ]);
    }

    public function create(): View
    {
        return view('admin.fab-faqs.form', [
            'item' => new FabFaqItem([
                'type' => FabFaqItem::TYPE_CATEGORY,
                'visibility' => FabFaqItem::VISIBILITY_BOTH,
                'sort_order' => $this->nextSortOrder(null),
                'is_active' => true,
            ]),
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $item = FabFaqItem::query()->create($data);

        $this->audit()->logCustom('FAB FAQ item created', 'fab_faq.created', [
            'auditable_type' => FabFaqItem::class,
            'auditable_id' => $item->id,
            'auditable_label' => $item->title,
            'new_values' => $item->only(['parent_id', 'type', 'visibility', 'title', 'answer', 'link_label', 'link_url', 'sort_order', 'is_active']),
        ]);

        return redirect()->route('admin.fab-faqs.index')->with('success', 'FAQ item created successfully.');
    }

    public function edit(FabFaqItem $fabFaq): View
    {
        return view('admin.fab-faqs.form', [
            'item' => $fabFaq,
            'parentOptions' => $this->parentOptions($fabFaq),
        ]);
    }

    public function update(Request $request, FabFaqItem $fabFaq): RedirectResponse
    {
        if ($request->input('type') === FabFaqItem::TYPE_QUESTION && $fabFaq->children()->exists()) {
            return back()
                ->withErrors(['type' => 'An item with child items cannot be changed into a question. Move or delete its children first.'])
                ->withInput();
        }

        $beforeState = $this->audit()->snapshotModel($fabFaq);
        $data = $this->validatedData($request, $fabFaq);
        $fabFaq->update($data);
        $fabFaq->refresh();

        $this->audit()->logModelUpdated($fabFaq, $beforeState, 'FAB FAQ item updated');

        return redirect()->route('admin.fab-faqs.index')->with('success', 'FAQ item updated successfully.');
    }

    public function destroy(FabFaqItem $fabFaq): RedirectResponse
    {
        $beforeValues = $fabFaq->only(['parent_id', 'type', 'visibility', 'title', 'answer', 'link_label', 'link_url', 'sort_order', 'is_active']);
        $itemId = $fabFaq->id;
        $itemTitle = $fabFaq->title;

        $fabFaq->delete();

        $this->audit()->logCustom('FAB FAQ item deleted', 'fab_faq.deleted', [
            'auditable_type' => FabFaqItem::class,
            'auditable_id' => $itemId,
            'auditable_label' => $itemTitle,
            'old_values' => $beforeValues,
        ]);

        return redirect()->route('admin.fab-faqs.index')->with('success', 'FAQ item deleted successfully.');
    }

    public function move(Request $request, FabFaqItem $fabFaq): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $siblings = FabFaqItem::query()
            ->where('parent_id', $fabFaq->parent_id)
            ->ordered()
            ->get();
        $currentIndex = $siblings->search(fn (FabFaqItem $item) => (int) $item->id === (int) $fabFaq->id);
        $targetIndex = $data['direction'] === 'up'
            ? ((int) $currentIndex - 1)
            : ((int) $currentIndex + 1);

        if ($currentIndex === false || ! $siblings->has($targetIndex)) {
            return back()->with('warning', 'FAQ item is already at the requested position.');
        }

        $ordered = $siblings->values()->all();
        [$ordered[$currentIndex], $ordered[$targetIndex]] = [$ordered[$targetIndex], $ordered[$currentIndex]];

        foreach ($ordered as $index => $item) {
            $item->forceFill(['sort_order' => ($index + 1) * 10])->save();
        }

        return back()->with('success', 'FAQ item reordered successfully.');
    }

    private function validatedData(Request $request, ?FabFaqItem $item = null): array
    {
        $excludedParentIds = $item ? array_merge([$item->id], $item->descendantIds()) : [];

        $data = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('fab_faq_items', 'id')->where(fn ($query) => $query->where('type', FabFaqItem::TYPE_CATEGORY)),
                Rule::notIn($excludedParentIds),
            ],
            'type' => ['required', Rule::in(FabFaqItem::TYPES)],
            'visibility' => ['required', Rule::in(FabFaqItem::VISIBILITIES)],
            'title' => ['required', 'string', 'max:255'],
            'answer' => [Rule::requiredIf($request->input('type') === FabFaqItem::TYPE_QUESTION), 'nullable', 'string'],
            'link_label' => ['nullable', 'string', 'max:255'],
            'link_url' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    $url = (string) $value;
                    if (! str_starts_with($url, '/') && ! preg_match('/^https?:\/\//i', $url)) {
                        $fail('The navigation link URL must start with /, http://, or https://.');
                    }
                },
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $data['parent_id'] = isset($data['parent_id']) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null;
        $data['sort_order'] = (int) $data['sort_order'];
        $data['is_active'] = $request->boolean('is_active');

        if ($data['type'] === FabFaqItem::TYPE_CATEGORY) {
            $data['answer'] = null;
        }

        if (blank($data['link_url'] ?? null)) {
            $data['link_label'] = null;
            $data['link_url'] = null;
        } elseif (blank($data['link_label'] ?? null)) {
            $data['link_label'] = 'Open link';
        }

        return $data;
    }

    private function parentOptions(?FabFaqItem $item = null): array
    {
        $excludedIds = $item ? array_merge([$item->id], $item->descendantIds()) : [];

        return FabFaqItem::flattened()
            ->filter(fn (FabFaqItem $option) => $option->type === FabFaqItem::TYPE_CATEGORY && ! in_array($option->id, $excludedIds, true))
            ->map(fn (FabFaqItem $option) => [
                'id' => $option->id,
                'label' => str_repeat('-- ', (int) $option->getAttribute('depth')).$option->title,
            ])
            ->values()
            ->all();
    }

    private function nextSortOrder(?int $parentId): int
    {
        return ((int) FabFaqItem::query()
            ->where('parent_id', $parentId)
            ->max('sort_order')) + 10;
    }
}
