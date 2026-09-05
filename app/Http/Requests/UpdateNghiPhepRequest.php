<?php

namespace App\Http\Requests;

use App\Support\NormalizesDisplayDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNghiPhepRequest extends FormRequest
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
            'tu_ngay' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'den_ngay' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
            'ma_lp' => 'sometimes|required|integer',
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
            'tu_ngay.date_format' => 'Từ ngày phải có định dạng dd/mm/yyyy.',
            'den_ngay.date_format' => 'Đến ngày phải có định dạng dd/mm/yyyy.',
            'den_ngay.after_or_equal' => 'Đến ngày phải sau hoặc bằng Từ ngày.',
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
