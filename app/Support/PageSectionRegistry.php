<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

class PageSectionRegistry
{
    public static function styleChoices(): array
    {
        return [
            ['value' => 'default', 'label' => 'Default'],
            ['value' => 'muted', 'label' => 'Muted'],
            ['value' => 'accent', 'label' => 'Accent'],
            ['value' => 'dark', 'label' => 'Dark'],
        ];
    }

    public static function normalizePayload(?string $payload): array
    {
        if ($payload === null || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The page sections could not be parsed.');
        }

        return self::normalize($decoded);
    }

    public static function normalize(array $sections): array
    {
        $allowedStyles = collect(self::styleChoices())->pluck('value')->all();

        return collect($sections)
            ->values()
            ->map(function ($section) use ($allowedStyles) {
                if (! is_array($section)) {
                    throw new InvalidArgumentException('One of the page sections is invalid.');
                }

                $title = self::cleanString($section['title'] ?? '');
                $anchor = self::cleanString($section['anchor'] ?? '');
                $intro = self::cleanString($section['intro'] ?? '');
                $style = self::cleanString($section['style'] ?? 'default');
                $blocks = PageBlockRegistry::normalize($section['blocks'] ?? []);

                if (! in_array($style, $allowedStyles, true)) {
                    $style = 'default';
                }

                $normalized = [
                    'title' => $title,
                    'anchor' => $anchor !== '' ? Str::slug($anchor) : '',
                    'intro' => $intro,
                    'style' => $style,
                    'blocks' => $blocks,
                ];

                return self::hasContent($normalized) ? $normalized : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function forForm(array $sections = [], array $legacyBlocks = []): array
    {
        if ($sections !== []) {
            return collect($sections)
                ->map(function ($section) {
                    if (! is_array($section)) {
                        return null;
                    }

                    $section['style'] = $section['style'] ?? 'default';
                    $section['blocks'] = PageBlockRegistry::forForm($section['blocks'] ?? []);

                    return $section;
                })
                ->filter()
                ->values()
                ->all();
        }

        if ($legacyBlocks !== []) {
            return [[
                'title' => 'Main Section',
                'anchor' => '',
                'intro' => '',
                'style' => 'default',
                'blocks' => PageBlockRegistry::forForm($legacyBlocks),
            ]];
        }

        return [];
    }

    public static function forDisplay(array $sections = [], array $legacyBlocks = []): array
    {
        if ($sections !== []) {
            return collect($sections)
                ->map(function ($section) {
                    if (! is_array($section)) {
                        return null;
                    }

                    return [
                        'title' => $section['title'] ?? '',
                        'anchor' => $section['anchor'] ?? '',
                        'intro' => $section['intro'] ?? '',
                        'style' => $section['style'] ?? 'default',
                        'blocks' => collect($section['blocks'] ?? [])
                            ->map(fn ($block) => is_array($block) ? PageBlockRegistry::sanitizeForDisplay($block) : null)
                            ->filter()
                            ->values()
                            ->all(),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        if ($legacyBlocks !== []) {
            return [[
                'title' => '',
                'anchor' => '',
                'intro' => '',
                'style' => 'default',
                'blocks' => collect($legacyBlocks)
                    ->map(fn ($block) => is_array($block) ? PageBlockRegistry::sanitizeForDisplay($block) : null)
                    ->filter()
                    ->values()
                    ->all(),
            ]];
        }

        return [];
    }

    private static function hasContent(array $section): bool
    {
        return trim((string) ($section['title'] ?? '')) !== ''
            || trim((string) ($section['intro'] ?? '')) !== ''
            || ! empty($section['blocks']);
    }

    private static function cleanString(mixed $value): string
    {
        return trim(preg_replace("/\r\n?/", "\n", (string) $value));
    }
}
