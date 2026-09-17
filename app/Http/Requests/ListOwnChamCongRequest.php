<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOwnChamCongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'thang' => ['nullable', 'integer', 'between:1,12'],
            'nam' => ['nullable', 'integer', 'between:2000,2100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
            'ma_nv' => ['prohibited'],
            'ma_pb' => ['prohibited'],
            'tu_khoa' => ['prohibited'],
        ];
    }

    /** @return array{thang:int,nam:int,page:int,per_page:int} */
    public function filters(): array
    {
        return [
            'thang' => (int) ($this->validated('thang') ?? now()->month),
            'nam' => (int) ($this->validated('nam') ?? now()->year),
            'page' => (int) ($this->validated('page') ?? 1),
            'per_page' => (int) ($this->validated('per_page') ?? 10),
        ];
    }
}
