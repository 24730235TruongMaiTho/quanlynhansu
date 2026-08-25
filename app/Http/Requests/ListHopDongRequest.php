<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListHopDongRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['keyword' => ['nullable', 'string', 'max:100'], 'ma_lhd' => ['nullable', 'integer', 'exists:loai_hop_dong,ma_lhd'], 'sap_het_han' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'in:10,15,25,50']]; }
    protected function prepareForValidation(): void { $this->merge(['keyword' => trim((string) $this->input('keyword', '')), 'sap_het_han' => $this->boolean('sap_het_han')]); }
}
