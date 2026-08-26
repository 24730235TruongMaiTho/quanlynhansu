<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNghiPhepRequest extends FormRequest
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
            'tu_ngay' => 'required|date',
            'den_ngay' => 'required|date|after_or_equal:tu_ngay',
            'ma_lp' => 'required|integer',
            'ly_do' => 'nullable|string|max:255',
            'trang_thai_duyet' => 'nullable|integer|in:0,1,2',
        ];
    }

    public function messages()
    {
        return [
            'ma_nv.required' => 'Mã nhân viên không được để trống',
            'tu_ngay.required' => 'Ngày bắt đầu không được để trống',
            'den_ngay.required' => 'Ngày kết thúc không được để trống',
            'den_ngay.after_or_equal' => 'Ngày kết thúc phải sau ngày bắt đầu',
            'ma_lp.required' => 'Loại phép không được để trống',
        ];
    }
}
