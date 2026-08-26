<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHopDongRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['ma_nv' => ['required', 'regex:/\A[0-9]{5}\z/', 'exists:nhan_vien,ma_nv'], 'ma_lhd' => ['required', 'integer', 'exists:loai_hop_dong,ma_lhd'], 'ngay_ky' => ['required', 'date'], 'ngay_het_han' => ['nullable', 'date', 'after_or_equal:ngay_ky'], 'luong_co_ban' => ['required', 'integer', 'min:0', 'max:999999999999999999']]; }
}
