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

        foreach (['ten_cv', 'page', 'so_dong', 'sort', 'direction'] as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $value = $this->input($key);
            $normalized[$key] = is_string($value)
                ? (in_array($key, ['sort', 'direction'], true) ? strtolower(trim($value)) : trim($value))
                : $value;
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
        $normalized['sort'] = $normalized['sort'] ?? 'ma_cv';
        $normalized['direction'] = $normalized['direction'] ?? 'desc';

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'ten_cv' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'so_dong' => ['sometimes', 'integer', Rule::in([5, 10, 20, 50, 100])],
            'sort' => ['sometimes', 'string', Rule::in(['ma_cv', 'ten_cv', 'he_so_phu_cap', 'so_nhan_vien'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
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
            'sort' => $validated['sort'] ?? 'ma_cv',
            'direction' => $validated['direction'] ?? 'desc',
        ];
    }
}
