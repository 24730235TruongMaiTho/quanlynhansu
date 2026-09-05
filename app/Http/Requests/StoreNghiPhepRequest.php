<?php

namespace App\Http\Requests;

use App\Support\NormalizesDisplayDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNghiPhepRequest extends FormRequest
{
    use NormalizesDisplayDates;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $this->normalizeDisplayDateFields(['tu_ngay', 'den_ngay']);

        return [
            'ma_nv' => [
                'required',
                'string',
                'regex:/\A[0-9]{5}\z/',
                'max:5',
                Rule::exists('nhan_vien', 'ma_nv'),
            ],
            'tu_ngay' => ['required', 'date_format:Y-m-d'],
            'den_ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
            'ma_lp' => 'required|integer',
            'ly_do' => 'required|string|max:255',
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
            'tu_ngay.date_format' => 'Từ ngày phải có định dạng dd/mm/yyyy.',
            'den_ngay.date_format' => 'Đến ngày phải có định dạng dd/mm/yyyy.',
            'den_ngay.after_or_equal' => 'Đến ngày phải sau hoặc bằng Từ ngày.',
            'ma_lp.required' => 'Loại phép không được để trống',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectNonDisplayDates($validator, [
                'tu_ngay' => 'Từ ngày',
                'den_ngay' => 'Đến ngày',
            ]);
        });
    }
}
