<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListVaiTroRequest extends FormRequest
{
    private const PAGE_SIZES = [10, 20, 50];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['ten_vt', 'page', 'per_page'] as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $value = $this->input($key);
            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }

        if (($normalized['ten_vt'] ?? null) === '') {
            $normalized['ten_vt'] = null;
        }
        if (($normalized['page'] ?? null) === null || ($normalized['page'] ?? null) === '') {
            $normalized['page'] = 1;
        }

        $perPage = filter_var($normalized['per_page'] ?? null, FILTER_VALIDATE_INT);
        $normalized['per_page'] = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 10;

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'ten_vt' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', Rule::in(self::PAGE_SIZES)],
        ];
    }

    /** @return array{ten_vt: ?string, page: int, per_page: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'ten_vt' => $validated['ten_vt'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }
}
