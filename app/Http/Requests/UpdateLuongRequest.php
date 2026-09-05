<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLuongRequest extends FormRequest
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
            'ky_luong' => 'sometimes|required|date_format:Y-m-d',
            'thuong' => ['nullable', 'regex:/\A\d{1,18}\z/', 'numeric', 'min:0', 'max:999999999999999999'],
            'phat' => ['nullable', 'regex:/\A\d{1,18}\z/', 'numeric', 'min:0', 'max:999999999999999999'],
            'bao_hiem' => ['nullable', 'regex:/\A\d{1,18}\z/', 'numeric', 'min:0', 'max:999999999999999999'],
            'thue' => ['nullable', 'regex:/\A\d{1,18}\z/', 'numeric', 'min:0', 'max:999999999999999999'],
        ];
    }

    public function messages()
    {
        return [
            'ma_nv.required' => 'Mã nhân viên không được để trống',
            'ky_luong.required' => 'Kỳ lương không được để trống',
        ];
    }

    protected function prepareForValidation(): void
    {
        $period = (string) $this->input('ky_luong', '');

        if (preg_match('/\A\d{4}-\d{2}\z/', $period) === 1) {
            $this->merge(['ky_luong' => $period . '-01']);
        }
    }
}
