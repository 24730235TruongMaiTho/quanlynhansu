<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOwnNghiPhepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ma_nv' => ['prohibited'],
            'tu_khoa' => ['prohibited'],
            'ma_pb' => ['prohibited'],
            'ma_cv' => ['prohibited'],
            'trang_thai_duyet' => ['nullable', 'integer', 'in:0,1,2'],
            'tu_ngay' => ['nullable', 'date_format:Y-m-d'],
            'den_ngay' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
            'tab' => ['nullable', 'in:pending,history'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'trang_thai_duyet' => $validated['trang_thai_duyet'] ?? null,
            'tu_ngay' => $validated['tu_ngay'] ?? null,
            'den_ngay' => $validated['den_ngay'] ?? null,
            'tab' => $validated['tab'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }
}
