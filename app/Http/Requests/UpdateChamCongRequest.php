<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChamCongRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ma_nv' => 'sometimes|required|integer|exists:nhan_vien,ma_nv',
            'ngay_ky' => 'sometimes|required|date',
            'so_gio_lam' => 'sometimes|required|numeric|min:0',
            'vao_muon' => 'nullable|integer|min:0',
            've_som' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'ma_nv.required' => 'Mã nhân viên không được để trống',
            'ma_nv.exists' => 'Mã nhân viên không tồn tại',
            'ngay_ky.required' => 'Ngày kỳ không được để trống',
            'so_gio_lam.required' => 'Số giờ làm không được để trống',
        ];
    }
}
