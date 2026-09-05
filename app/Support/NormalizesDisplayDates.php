<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;
use Throwable;

trait NormalizesDisplayDates
{
    /** @var array<string, bool> */
    private array $normalizedDisplayDates = [];

    /**
     * Convert strict Vietnamese display dates to the ISO values used by
     * validation and persistence. Invalid values are intentionally left
     * untouched so the existing validator can reject them.
     *
     * @param array<int, string> $fields
     */
    protected function normalizeDisplayDateFields(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            $value = $this->input($field);
            if ($value === null || $value === '' || ! is_string($value)) {
                continue;
            }

            if (preg_match('/\A[0-9]{2}\/[0-9]{2}\/[0-9]{4}\z/', $value) !== 1) {
                continue;
            }

            try {
                $date = CarbonImmutable::createFromFormat('!d/m/Y', $value);
                $errors = CarbonImmutable::getLastErrors();
            } catch (Throwable) {
                continue;
            }

            if ($date === false
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $date->format('d/m/Y') !== $value) {
                continue;
            }

            $normalized[$field] = $date->format('Y-m-d');
            $this->normalizedDisplayDates[$field] = true;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    protected function wasDisplayDateNormalized(string $field): bool
    {
        return $this->normalizedDisplayDates[$field] ?? false;
    }

    protected function isCanonicalIsoDate(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            return false;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            $errors = CarbonImmutable::getLastErrors();
        } catch (Throwable) {
            return false;
        }

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    /**
     * Add a stable, field-labelled error for values that were not supplied as
     * a strict display date. JSON clients may send an already canonical ISO
     * date; browser form submissions must use dd/mm/yyyy.
     *
     * @param array<string, string> $fields
     */
    protected function rejectNonDisplayDates(Validator $validator, array $fields): void
    {
        foreach ($fields as $field => $label) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            if ($value === null || $value === '' || $this->wasDisplayDateNormalized($field)) {
                continue;
            }

            if ($this->isJson() && $this->isCanonicalIsoDate($value)) {
                continue;
            }

            $validator->errors()->add(
                $field,
                $label.' phải có định dạng dd/mm/yyyy.',
            );
        }
    }
}
