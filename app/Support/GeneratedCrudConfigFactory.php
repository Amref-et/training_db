<?php

namespace App\Support;

use App\Models\GeneratedCrud;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GeneratedCrudConfigFactory
{
    public static function make(GeneratedCrud $crud): array
    {
        $fields = collect($crud->schema['fields'] ?? []);

        $searchable = $fields
            ->filter(fn (array $field) => in_array($field['type'], ['string', 'text'], true))
            ->pluck('name')
            ->values()
            ->all();

        $columns = $fields
            ->filter(fn (array $field) => $field['show_in_index'] ?? true)
            ->map(fn (array $field) => [
                'label' => $field['label'] ?: Str::headline($field['name']),
                'value' => $field['name'],
            ])
            ->values()
            ->all();

        $formFields = $fields
            ->filter(fn (array $field) => $field['in_form'] ?? true)
            ->map(fn (array $field) => self::mapField($field))
            ->values()
            ->all();

        $titleField = $fields
            ->first(fn (array $field) => in_array($field['type'], ['string', 'text'], true), Arr::first($fields->all()));

        return [
            'path' => $crud->slug,
            'permission' => $crud->slug,
            'label' => $crud->plural_label,
            'singular' => $crud->singular_label,
            'model' => $crud->model_class,
            'title_column' => $titleField['name'] ?? 'id',
            'search' => $searchable,
            'columns' => $columns,
            'fields' => $formFields,
            'rules' => self::rules($crud),
            'order_by' => 'id',
            'generated' => true,
            'table_name' => $crud->table_name,
        ];
    }

    public static function rules(GeneratedCrud $crud): array
    {
        return collect($crud->schema['fields'] ?? [])->mapWithKeys(function (array $field) use ($crud) {
            $rules = [];

            if (! ($field['nullable'] ?? false)) {
                $rules[] = 'required';
            } else {
                $rules[] = 'nullable';
            }

            $rules[] = match ($field['type']) {
                'string', 'text' => 'string',
                'integer', 'bigInteger' => 'integer',
                'decimal' => 'numeric',
                'boolean' => 'boolean',
                'date', 'dateTime' => 'date',
                default => 'string',
            };

            if ($field['type'] === 'string') {
                $rules[] = 'max:255';
            }

            if ($field['unique'] ?? false) {
                $rules[] = 'unique:'.$crud->table_name.','.$field['name'].',{{id}},id';
            }

            return [$field['name'] => implode('|', $rules)];
        })->all();
    }

    private static function mapField(array $field): array
    {
        $base = [
            'name' => $field['name'],
            'label' => $field['label'] ?: Str::headline($field['name']),
            'required' => ! ($field['nullable'] ?? false),
        ];

        return match ($field['type']) {
            'text' => $base + ['type' => 'textarea'],
            'integer', 'bigInteger', 'decimal' => $base + ['type' => 'number'],
            'date' => $base + ['type' => 'date'],
            'dateTime' => $base + ['type' => 'datetime-local'],
            'boolean' => $base + [
                'type' => 'select',
                'choices' => [
                    ['value' => 1, 'label' => 'Yes'],
                    ['value' => 0, 'label' => 'No'],
                ],
            ],
            default => $base + ['type' => 'text'],
        };
    }
}
