<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignVaiTroRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['ma_vt' => ['required', 'integer', 'exists:vai_tro,ma_vt']]; }
}
