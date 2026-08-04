<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLuongRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ma_nv' => 'sometimes|required|integer|exists:nhan_vien,ma_nv',
            'ky_luong' => 'sometimes|required|date',
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
            'ky_luong.required' => 'Kỳ lương không được để trống',
        ];
    }
}
