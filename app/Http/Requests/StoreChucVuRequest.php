<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChucVuRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ten_cv' => 'required|string|max:100|unique:chuc_vu,ten_cv',
            'he_so_phu_cap' => 'required|numeric|min:0|max:99.99',
        ];
    }

    public function messages()
    {
        return [
            'ten_cv.required' => 'Tên chức vụ không được để trống',
            'ten_cv.unique' => 'Tên chức vụ đã tồn tại',
            'he_so_phu_cap.required' => 'Hệ số phụ cấp không được để trống',
            'he_so_phu_cap.numeric' => 'Hệ số phụ cấp phải là số',
        ];
    }
}
