<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChamCongRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ma_nv' => [
                'required',
                'string',
                'regex:/\A[0-9]{5}\z/',
                'max:5',
                Rule::exists('nhan_vien', 'ma_nv'),
            ],
            'ngay_ky' => 'required|date',
            'so_gio_lam' => 'required|numeric|min:0',
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
            'ngay_ky.date' => 'Ngày kỳ phải là ngày hợp lệ',
            'so_gio_lam.required' => 'Số giờ làm không được để trống',
            'so_gio_lam.numeric' => 'Số giờ làm phải là số',
        ];
    }
}
