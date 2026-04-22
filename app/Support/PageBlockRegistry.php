<?php

namespace App\Support;

use InvalidArgumentException;

class PageBlockRegistry
{
    public static function definitions(): array
    {
        return [
            'hero' => self::withCommonFields([
                'label' => 'Hero Banner',
                'description' => 'Large page intro with optional image and primary action.',
                'fields' => [
                    ['name' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'placeholder' => 'Program Update'],
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text', 'placeholder' => 'Build better learning outcomes'],
                    ['name' => 'content', 'label' => 'Content', 'type' => 'textarea', 'placeholder' => 'Short supporting copy for the hero section.'],
                    ['name' => 'button_label', 'label' => 'Button Label', 'type' => 'text', 'placeholder' => 'Explore Projects'],
                    ['name' => 'button_url', 'label' => 'Button URL', 'type' => 'url', 'placeholder' => '/pages/projects'],
                    ['name' => 'image_url', 'label' => 'Image URL', 'type' => 'url', 'placeholder' => 'https://example.com/hero.jpg'],
                ],
            ], 'full'),
            'rich_text' => self::withCommonFields([
                'label' => 'Rich Text',
                'description' => 'Freeform content section for paragraphs, lists, and embedded HTML.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Section Title', 'type' => 'text', 'placeholder' => 'About the Program'],
                    ['name' => 'content', 'label' => 'Content', 'type' => 'textarea', 'placeholder' => '<p>Write the section content here.</p>'],
                ],
            ], 'full'),
            'image' => self::withCommonFields([
                'label' => 'Image',
                'description' => 'Standalone visual block with caption support.',
                'fields' => [
                    ['name' => 'image_url', 'label' => 'Image URL', 'type' => 'url', 'placeholder' => 'https://example.com/photo.jpg'],
                    ['name' => 'alt_text', 'label' => 'Alt Text', 'type' => 'text', 'placeholder' => 'Describe the image'],
                    ['name' => 'caption', 'label' => 'Caption', 'type' => 'textarea', 'placeholder' => 'Optional context for the image.'],
                ],
            ], 'half'),
            'stats' => self::withCommonFields([
                'label' => 'Stats',
                'description' => 'Metric cards rendered from one line per statistic.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Section Title', 'type' => 'text', 'placeholder' => 'Impact Snapshot'],
                    ['name' => 'items', 'label' => 'Stats', 'type' => 'stats-items', 'placeholder' => "Participants | 124`nProjects | 32"],
                ],
            ], 'half'),
            'quote' => self::withCommonFields([
                'label' => 'Quote',
                'description' => 'Testimonial or statement with attribution.',
                'fields' => [
                    ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'placeholder' => 'A highlighted statement or testimonial.'],
                    ['name' => 'author', 'label' => 'Author', 'type' => 'text', 'placeholder' => 'Jane Doe'],
                    ['name' => 'role', 'label' => 'Role / Title', 'type' => 'text', 'placeholder' => 'Training Lead'],
                ],
            ], 'half'),
            'cta' => self::withCommonFields([
                'label' => 'Call to Action',
                'description' => 'Closing prompt with supporting text and button.',
                'fields' => [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text', 'placeholder' => 'Ready to get involved?'],
                    ['name' => 'content', 'label' => 'Content', 'type' => 'textarea', 'placeholder' => 'Explain the next step for the visitor.'],
                    ['name' => 'button_label', 'label' => 'Button Label', 'type' => 'text', 'placeholder' => 'Contact Us'],
                    ['name' => 'button_url', 'label' => 'Button URL', 'type' => 'url', 'placeholder' => '/pages/contact'],
                ],
            ], 'full'),
            'feature_list' => self::withCommonFields([
                'label' => 'Feature List',
                'description' => 'Short checklist or benefit grid.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Section Title', 'type' => 'text', 'placeholder' => 'Why this matters'],
                    ['name' => 'intro', 'label' => 'Intro', 'type' => 'textarea', 'placeholder' => 'Add short context before the list.'],
                    ['name' => 'items', 'label' => 'Items', 'type' => 'list-items', 'placeholder' => "Capacity building`nStronger partnerships`nMeasured outcomes"],
                ],
            ], 'half'),
            'gallery' => self::withCommonFields([
                'label' => 'Gallery',
                'description' => 'Simple image gallery with one image per line.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Section Title', 'type' => 'text', 'placeholder' => 'Field Highlights'],
                    ['name' => 'items', 'label' => 'Gallery Images', 'type' => 'gallery-items', 'placeholder' => "https://example.com/photo-1.jpg | Community session`nhttps://example.com/photo-2.jpg | Workshop team"],
                ],
            ], 'full'),
            'video_embed' => self::withCommonFields([
                'label' => 'Video Embed',
                'description' => 'Embed a hosted video using its embed URL.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Section Title', 'type' => 'text', 'placeholder' => 'Watch the overview'],
                    ['name' => 'embed_url', 'label' => 'Embed URL', 'type' => 'url', 'placeholder' => 'https://www.youtube.com/embed/VIDEO_ID'],
                    ['name' => 'caption', 'label' => 'Caption', 'type' => 'textarea', 'placeholder' => 'Optional supporting note.'],
                ],
            ], 'half'),
            'dashboard' => self::withCommonFields([
                'label' => 'Dashboard',
                'description' => 'Show participant, project, and pre/post result metrics inside the page.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Section Title', 'type' => 'text', 'placeholder' => 'Program Dashboard'],
                    ['name' => 'intro', 'label' => 'Intro', 'type' => 'textarea', 'placeholder' => 'Optional text above the dashboard metrics.'],
                    [
                        'name' => 'selected_filters',
                        'label' => 'Filters To Display',
                        'type' => 'checkbox-group',
                        'default' => [
                            'training_organizer_id',
                            'organized_by',
                            'gender',
                            'region_id',
                            'organization_id',
                            'profession',
                            'training_id',
                            'status',
                        ],
                        'choices' => self::dashboardFilterChoices(),
                        'help' => 'Select which live variables should appear as filter controls for this block.',
                    ],
                    ['name' => 'show_breakdowns', 'label' => 'Show Comparisons', 'type' => 'select', 'default' => 'yes', 'choices' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                    ]],
                ],
            ], 'full'),
            'callout' => self::withCommonFields([
                'label' => 'Callout',
                'description' => 'Highlighted message box for announcements and notices.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'placeholder' => 'Key update'],
                    ['name' => 'content', 'label' => 'Content', 'type' => 'textarea', 'placeholder' => 'Explain the announcement or important note.'],
                    ['name' => 'tone', 'label' => 'Tone', 'type' => 'select', 'default' => 'info', 'choices' => [
                        ['value' => 'info', 'label' => 'Info'],
                        ['value' => 'success', 'label' => 'Success'],
                        ['value' => 'warning', 'label' => 'Warning'],
                        ['value' => 'danger', 'label' => 'Danger'],
                    ]],
                ],
            ], 'half'),
        ];
    }

    public static function normalizePayload(?string $payload): array
    {
        if ($payload === null || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The page blocks could not be parsed.');
        }

        return self::normalize($decoded);
    }

    public static function normalize(array $blocks): array
    {
        $definitions = self::definitions();

        return collect($blocks)
            ->values()
            ->map(function ($block) use ($definitions) {
                if (! is_array($block)) {
                    throw new InvalidArgumentException('One of the page blocks is invalid.');
                }

                $type = (string) ($block['type'] ?? '');

                if (! isset($definitions[$type])) {
                    throw new InvalidArgumentException('One of the selected block types is not supported.');
                }

                $normalized = ['type' => $type];

                foreach ($definitions[$type]['fields'] as $field) {
                    $value = $block[$field['name']] ?? null;

                    if ($field['type'] === 'stats-items') {
                        $normalized[$field['name']] = self::normalizeStats($value);
                        continue;
                    }

                    if ($field['type'] === 'list-items') {
                        $normalized[$field['name']] = self::normalizeList($value);
                        continue;
                    }

                    if ($field['type'] === 'gallery-items') {
                        $normalized[$field['name']] = self::normalizeGallery($value);
                        continue;
                    }

                    if ($field['type'] === 'checkbox-group') {
                        $normalized[$field['name']] = self::normalizeCheckboxValues($value, $field['choices'] ?? []);
                        continue;
                    }

                    $value = self::cleanString($value);

                    if (isset($field['choices'])) {
                        $allowedValues = collect($field['choices'])->pluck('value')->all();
                        $value = in_array($value, $allowedValues, true) ? $value : ($field['default'] ?? $allowedValues[0] ?? '');
                    }

                    if ($value !== '') {
                        $normalized[$field['name']] = $value;
                    }
                }

                return $normalized;
            })
            ->filter(fn (array $block) => self::hasContent($block))
            ->values()
            ->all();
    }

    public static function forForm(array $blocks): array
    {
        return collect($blocks)
            ->map(function ($block) {
                if (! is_array($block)) {
                    return null;
                }

                if (($block['type'] ?? null) === 'stats') {
                    $block['items'] = collect($block['items'] ?? [])
                        ->map(fn (array $item) => trim(self::plainText($item['label'] ?? '').' | '.self::plainText($item['value'] ?? '')))
                        ->filter()
                        ->implode(PHP_EOL);
                }

                if (($block['type'] ?? null) === 'feature_list') {
                    $block['items'] = collect($block['items'] ?? [])
                        ->map(fn ($item) => self::plainText($item))
                        ->filter()
                        ->implode(PHP_EOL);
                }

                if (($block['type'] ?? null) === 'gallery') {
                    $block['items'] = collect($block['items'] ?? [])
                        ->map(fn (array $item) => trim(self::cleanString($item['url'] ?? '').' | '.self::plainText($item['caption'] ?? '')))
                        ->filter()
                        ->implode(PHP_EOL);
                }

                if (($block['type'] ?? null) === 'dashboard') {
                    $block['selected_filters'] = collect($block['selected_filters'] ?? [])->map(fn ($value) => (string) $value)->all();
                }

                return $block;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function sanitizeForDisplay(array $block): array
    {
        $type = (string) ($block['type'] ?? '');

        if ($type === 'stats') {
            $block['items'] = collect($block['items'] ?? [])
                ->map(function ($item) {
                    if (! is_array($item)) {
                        return null;
                    }

                    return [
                        'label' => self::plainText($item['label'] ?? ''),
                        'value' => self::plainText($item['value'] ?? ''),
                    ];
                })
                ->filter(fn ($item) => is_array($item) && (($item['label'] ?? '') !== '' || ($item['value'] ?? '') !== ''))
                ->values()
                ->all();
        }

        if ($type === 'feature_list') {
            $block['items'] = collect($block['items'] ?? [])
                ->map(fn ($item) => self::plainText($item))
                ->filter()
                ->values()
                ->all();
        }

        if ($type === 'gallery') {
            $block['items'] = collect($block['items'] ?? [])
                ->map(function ($item) {
                    if (! is_array($item)) {
                        return null;
                    }

                    $url = self::cleanString($item['url'] ?? '');

                    return $url !== '' ? [
                        'url' => $url,
                        'caption' => self::plainText($item['caption'] ?? ''),
                    ] : null;
                })
                ->filter()
                ->values()
                ->all();
        }

        return $block;
    }

    public static function widthChoices(): array
    {
        return [
            ['value' => 'full', 'label' => 'Full Width'],
            ['value' => 'two-thirds', 'label' => 'Two Thirds'],
            ['value' => 'half', 'label' => 'Half Width'],
            ['value' => 'third', 'label' => 'One Third'],
            ['value' => 'quarter', 'label' => 'One Quarter'],
        ];
    }

    public static function dashboardFilterChoices(): array
    {
        return [
            ['value' => 'training_organizer_id', 'label' => 'Project'],
            ['value' => 'organized_by', 'label' => 'Organized By'],
            ['value' => 'gender', 'label' => 'Gender'],
            ['value' => 'region_id', 'label' => 'Region'],
            ['value' => 'organization_id', 'label' => 'Organization'],
            ['value' => 'profession', 'label' => 'Profession'],
            ['value' => 'training_id', 'label' => 'Training'],
            ['value' => 'status', 'label' => 'Training Status'],
        ];
    }

    private static function withCommonFields(array $definition, string $defaultWidth): array
    {
        array_unshift($definition['fields'], [
            'name' => 'width',
            'label' => 'Width',
            'type' => 'select',
            'default' => $defaultWidth,
            'choices' => self::widthChoices(),
        ]);

        return $definition;
    }

    private static function normalizeStats(mixed $value): array
    {
        $lines = self::linesFromEditorValue($value);

        return collect($lines)
            ->map(function ($line) {
                if (is_array($line)) {
                    $label = self::cleanString($line['label'] ?? '');
                    $value = self::cleanString($line['value'] ?? '');

                    return ($label !== '' || $value !== '')
                        ? ['label' => $label, 'value' => $value]
                        : null;
                }

                $line = self::cleanString($line);

                if ($line === '') {
                    return null;
                }

                [$label, $stat] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

                return ['label' => $label, 'value' => $stat];
            })
            ->filter(fn ($item) => is_array($item) && ($item['label'] !== '' || $item['value'] !== ''))
            ->values()
            ->all();
    }

    private static function normalizeList(mixed $value): array
    {
        $lines = self::linesFromEditorValue($value);

        return collect($lines)
            ->map(fn ($line) => self::cleanString(is_array($line) ? ($line['value'] ?? '') : $line))
            ->filter()
            ->values()
            ->all();
    }

    private static function normalizeGallery(mixed $value): array
    {
        $lines = self::linesFromEditorValue($value);

        return collect($lines)
            ->map(function ($line) {
                if (is_array($line)) {
                    $url = self::cleanString($line['url'] ?? '');
                    $caption = self::cleanString($line['caption'] ?? '');
                } else {
                    $line = self::cleanString($line);

                    if ($line === '') {
                        return null;
                    }

                    [$url, $caption] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
                }

                return $url !== '' ? ['url' => $url, 'caption' => $caption] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function normalizeCheckboxValues(mixed $value, array $choices): array
    {
        $allowedValues = collect($choices)->pluck('value')->map(fn ($item) => (string) $item)->all();
        $values = is_array($value) ? $value : preg_split('/\r\n|\r|\n|,/', (string) $value);

        return collect($values)
            ->map(fn ($item) => self::cleanString($item))
            ->filter(fn ($item) => $item !== '' && in_array($item, $allowedValues, true))
            ->unique()
            ->values()
            ->all();
    }

    private static function hasContent(array $block): bool
    {
        foreach ($block as $key => $value) {
            if (in_array($key, ['type', 'width'], true)) {
                continue;
            }

            if (is_array($value) && $value !== []) {
                return true;
            }

            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function cleanString(mixed $value): string
    {
        return trim(preg_replace("/\r\n?/", "\n", (string) $value));
    }

    private static function plainText(mixed $value): string
    {
        $text = (string) $value;
        $text = preg_replace('/<\\s*br\\s*\\/?\\s*>/i', ' ', $text);
        $text = preg_replace('/<\\s*\\/?\\s*(p|div|h[1-6]|li)\\b[^>]*>/i', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    private static function linesFromEditorValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $text = (string) $value;

        // TinyMCE often stores block HTML; convert block boundaries into line breaks.
        $text = preg_replace('/<\\s*\\/?\\s*(p|div|h[1-6]|li)\\b[^>]*>/i', "\n", $text);
        $text = preg_replace('/<\\s*br\\s*\\/?\\s*>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_split('/\r\n|\r|\n/', $text);
    }
}

