<?php

namespace App\Services\Ticketing;

use App\Models\ServiceCatalog;
use App\Models\ServiceItem;
use App\Models\ServiceItemField;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServiceCatalogService
{
    /**
     * Catalogue actif avec items et champs.
     *
     * @return Collection<int, ServiceCatalog>
     */
    public function listActive(): Collection
    {
        return ServiceCatalog::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->with('fields'),
            ])
            ->get();
    }

    /**
     * Valide les champs dynamiques d’un item de service.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function validateCustomFields(ServiceItem $item, array $values): array
    {
        $fields = $item->relationLoaded('fields')
            ? $item->fields
            : $item->fields()->get();

        $errors = [];
        $normalized = [];

        /** @var ServiceItemField $field */
        foreach ($fields as $field) {
            $code = $field->code;
            $raw = $values[$code] ?? null;
            $empty = $raw === null || $raw === '' || $raw === [];

            if ($field->is_required && $empty) {
                $errors[$code] = ["Le champ « {$field->label} » est obligatoire."];

                continue;
            }

            if ($empty) {
                continue;
            }

            $normalized[$code] = match ($field->field_type) {
                'number', 'integer' => is_numeric($raw) ? $raw + 0 : $raw,
                'boolean', 'checkbox' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $raw,
                'select', 'radio' => $this->assertOption($field, $raw, $errors),
                'multiselect' => $this->assertOptions($field, (array) $raw, $errors),
                default => is_scalar($raw) || is_array($raw) ? $raw : (string) $raw,
            };
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function assertOption(ServiceItemField $field, mixed $raw, array &$errors): mixed
    {
        $options = collect($field->options ?? [])->map(fn ($o) => is_array($o) ? ($o['value'] ?? $o['code'] ?? null) : $o)->filter()->all();
        if ($options !== [] && ! in_array($raw, $options, true)) {
            $errors[$field->code] = ["Valeur invalide pour « {$field->label} »."];
        }

        return $raw;
    }

    /**
     * @param  array<int, mixed>  $raw
     * @param  array<string, list<string>>  $errors
     * @return list<mixed>
     */
    private function assertOptions(ServiceItemField $field, array $raw, array &$errors): array
    {
        $options = collect($field->options ?? [])->map(fn ($o) => is_array($o) ? ($o['value'] ?? $o['code'] ?? null) : $o)->filter()->all();
        foreach ($raw as $value) {
            if ($options !== [] && ! in_array($value, $options, true)) {
                $errors[$field->code] = ["Valeur invalide pour « {$field->label} »."];
                break;
            }
        }

        return array_values($raw);
    }
}
