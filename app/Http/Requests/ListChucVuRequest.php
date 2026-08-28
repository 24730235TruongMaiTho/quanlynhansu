<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListChucVuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['ten_cv', 'page', 'so_dong'] as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $value = $this->input($key);
            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }

        if (($normalized['ten_cv'] ?? null) === '') {
            $normalized['ten_cv'] = null;
        }
        if (($normalized['page'] ?? null) === null || ($normalized['page'] ?? null) === '') {
            $normalized['page'] = 1;
        }
        if (($normalized['so_dong'] ?? null) === null || ($normalized['so_dong'] ?? null) === '') {
            $normalized['so_dong'] = 20;
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'ten_cv' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'so_dong' => ['sometimes', 'integer', Rule::in([5, 10, 20, 50, 100])],
        ];
    }

    /** @return array{ten_cv: ?string, page: int, so_dong: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'ten_cv' => $validated['ten_cv'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'so_dong' => (int) ($validated['so_dong'] ?? 20),
        ];
    }
}
