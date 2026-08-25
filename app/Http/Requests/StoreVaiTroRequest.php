<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreVaiTroRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { if (is_string($this->input('ten_vt'))) $this->merge(['ten_vt' => trim($this->input('ten_vt'))]); }
    public function rules(): array { return ['ten_vt' => ['required', 'string', 'max:100', 'unique:vai_tro,ten_vt'], 'mo_ta' => ['nullable', 'string', 'max:255']]; }
}
