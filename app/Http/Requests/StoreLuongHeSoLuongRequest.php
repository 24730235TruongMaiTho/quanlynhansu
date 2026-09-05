<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLuongHeSoLuongRequest extends FormRequest
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
                'regex:/\A[0-9]{5}\z/',
                Rule::exists('nhan_vien', 'ma_nv'),
            ],
            'he_so_luong' => ['required', 'regex:/\A(?:\d{1,3})(?:\.\d{1,2})?\z/', 'numeric', 'gt:0', 'max:999.99'],
            'tu_ngay' => ['required', 'date_format:Y-m-d'],
            'den_ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
        ];
    }

    public function messages(): array
    {
        return [
            'ma_nv.required' => 'Mã nhân viên không được để trống.',
            'ma_nv.regex' => 'Mã nhân viên phải gồm đúng 5 chữ số.',
            'ma_nv.exists' => 'Mã nhân viên không tồn tại.',
            'he_so_luong.required' => 'Hệ số lương không được để trống.',
            'he_so_luong.regex' => 'Hệ số lương phải là số dương, tối đa 2 chữ số thập phân.',
            'he_so_luong.gt' => 'Hệ số lương phải lớn hơn 0.',
            'he_so_luong.max' => 'Hệ số lương không được vượt quá 999,99.',
            'tu_ngay.required' => 'Từ ngày không được để trống.',
            'tu_ngay.date_format' => 'Từ ngày phải có định dạng YYYY-MM-DD.',
            'den_ngay.required' => 'Đến ngày không được để trống.',
            'den_ngay.date_format' => 'Đến ngày phải có định dạng YYYY-MM-DD.',
            'den_ngay.after_or_equal' => 'Đến ngày phải sau hoặc bằng Từ ngày.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ma_nv' => 'Mã nhân viên',
            'he_so_luong' => 'Hệ số lương',
            'tu_ngay' => 'Từ ngày',
            'den_ngay' => 'Đến ngày',
        ];
    }
}
