<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SyncVaiTroQuyenRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { if (! $this->has('ma_quyen')) $this->merge(['ma_quyen' => []]); }
    public function rules(): array { return ['ma_quyen' => ['present', 'array'], 'ma_quyen.*' => ['integer', 'distinct', 'exists:quyen,ma_quyen']]; }
}
