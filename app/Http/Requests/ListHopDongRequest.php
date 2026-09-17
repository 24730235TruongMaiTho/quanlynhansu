<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListHopDongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'ma_lhd' => ['nullable', 'integer', 'exists:loai_hop_dong,ma_lhd'],
            'sap_het_han' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'in:5,10,20,50,100'],
            'sort' => ['sometimes', 'string', Rule::in([
                'ma_hd', 'ma_nv', 'ho_ten', 'ten_lhd', 'ngay_ky', 'ngay_het_han', 'luong_co_ban',
            ])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sort = strtolower(trim((string) $this->input('sort', 'ma_hd')));
        $direction = strtolower(trim((string) $this->input('direction', 'desc')));

        $this->merge([
            'keyword' => trim((string) $this->input('keyword', '')),
            'sap_het_han' => $this->boolean('sap_het_han'),
            'sort' => $sort ?: 'ma_hd',
            'direction' => $direction ?: 'desc',
        ]);
    }
}
