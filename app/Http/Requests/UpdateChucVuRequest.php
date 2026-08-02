<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChucVuRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'ten_cv' => 'sometimes|required|string|max:100|unique:chuc_vu,ten_cv,' . $this->route('chuc_vu'),
            'he_so_phu_cap' => 'sometimes|required|numeric|min:0|max:99.99',
        ];
    }

    public function messages()
    {
        return [
            'ten_cv.required' => 'Tên chức vụ không được để trống',
            'ten_cv.unique' => 'Tên chức vụ đã tồn tại',
            'he_so_phu_cap.required' => 'Hệ số phụ cấp không được để trống',
        ];
    }
}
