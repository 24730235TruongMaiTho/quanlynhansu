<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLuongRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ma_nv' => 'required|integer|exists:nhan_vien,ma_nv',
            'ky_luong' => 'required|date',
            'thuong' => 'nullable|numeric|min:0',
            'phat' => 'nullable|numeric|min:0',
            'bao_hiem' => 'nullable|numeric|min:0',
            'thue' => 'nullable|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'ma_nv.required' => 'Mã nhân viên không được để trống',
            'ma_nv.exists' => 'Mã nhân viên không tồn tại',
            'ky_luong.required' => 'Kỳ lương không được để trống',
            'ky_luong.date' => 'Kỳ lương phải là ngày hợp lệ',
        ];
    }
}
