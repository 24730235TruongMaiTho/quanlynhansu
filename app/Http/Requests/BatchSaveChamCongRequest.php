<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchSaveChamCongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ma_nv' => [
                'required',
                'string',
                'max:5',
                Rule::exists('nhan_vien', 'ma_nv'),
            ],

            'thang' => [
                'required',
                'integer',
                'between:1,12',
            ],

            'nam' => [
                'required',
                'integer',
                'between:2000,2100',
            ],

            /*
             * Một tháng tối đa 31 ngày.
             */
            'rows' => [
                'required',
                'array',
                'min:1',
                'max:31',
            ],

            'rows.*.ma_cc' => [
                'nullable',
                'integer',
            ],

            'rows.*.ngay_lam' => [
                'required',
                'date_format:Y-m-d',
                'distinct',
            ],

            /*
             * -1 = bỏ/xóa dữ liệu ngày đó.
             */
            'rows.*.so_gio_lam' => [
                'required',
                'numeric',
                'min:-1',
                'max:24',
            ],

            'rows.*.vao_muon' => [
                'required',
                'integer',
                'in:0,1',
            ],

            'rows.*.ve_som' => [
                'required',
                'integer',
                'in:0,1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required' =>
                'Danh sách chấm công không được để trống.',

            'rows.max' =>
                'Một lần chỉ được lưu tối đa 31 ngày.',

            'rows.*.ngay_lam.distinct' =>
                'Danh sách có ngày chấm công bị trùng.',

            'rows.*.so_gio_lam.min' =>
                'Số giờ làm tối thiểu là -1.',

            'rows.*.so_gio_lam.max' =>
                'Số giờ làm tối đa là 24.',
        ];
    }
}
