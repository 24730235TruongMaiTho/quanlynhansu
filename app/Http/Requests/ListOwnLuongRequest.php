<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOwnLuongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ky_luong' => ['nullable', 'date_format:Y-m'],
            'ma_nv' => ['prohibited'],
            'tu_khoa' => ['prohibited'],
            'ma_pb' => ['prohibited'],
            'ma_cv' => ['prohibited'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $validated = $this->validated();
        $period = $validated['ky_luong'] ?? null;

        return [
            'ky_luong' => $period === null ? null : $period.'-01',
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }
}
