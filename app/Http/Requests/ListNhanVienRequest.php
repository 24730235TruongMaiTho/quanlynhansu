<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNhanVienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['tu_khoa', 'ma_pb', 'ma_cv', 'ma_tt', 'page', 'so_dong'] as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $value = $this->input($key);
            $normalized[$key] = is_string($value) && trim($value) === ''
                ? null
                : (is_string($value) ? trim($value) : $value);
        }

        if (($normalized['page'] ?? null) === null) {
            $normalized['page'] = 1;
        }

        if (($normalized['so_dong'] ?? null) === null) {
            $normalized['so_dong'] = 20;
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'ma_pb' => ['nullable', 'integer', 'min:1'],
            'ma_cv' => ['nullable', 'integer', 'min:1'],
            'ma_tt' => ['nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'so_dong' => ['sometimes', 'integer', Rule::in([5, 10, 20, 50, 100])],
        ];
    }

    /**
     * @return array{tu_khoa: ?string, ma_pb: ?int, ma_cv: ?int, ma_tt: ?int, page: int, so_dong: int}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'tu_khoa' => $validated['tu_khoa'] ?? null,
            'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
            'ma_cv' => isset($validated['ma_cv']) ? (int) $validated['ma_cv'] : null,
            'ma_tt' => isset($validated['ma_tt']) ? (int) $validated['ma_tt'] : null,
            'page' => (int) ($validated['page'] ?? 1),
            'so_dong' => (int) ($validated['so_dong'] ?? 20),
        ];
    }
}
