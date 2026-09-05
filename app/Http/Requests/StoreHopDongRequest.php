<?php

namespace App\Http\Requests;

use App\Support\NormalizesDisplayDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHopDongRequest extends FormRequest
{
    use NormalizesDisplayDates;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeDisplayDateFields(['ngay_ky', 'ngay_het_han']);

        if ((int) $this->input('ma_lhd') === 1) {
            $this->merge(['ngay_het_han' => null]);
        }

        $salary = $this->input('luong_co_ban');
        if (is_string($salary) && preg_match('/\A[0-9]{1,3}(?:\.[0-9]{3})+\z/', $salary) === 1) {
            $salary = str_replace('.', '', $salary);
        }
        if (is_string($salary) && preg_match('/\A[0-9]+\z/', $salary) === 1) {
            $this->merge(['luong_co_ban' => (int) $salary]);
        }
    }

    public function rules(): array
    {
        $expiry = (int) $this->input('ma_lhd') === 1
            ? ['nullable']
            : ['required', 'date_format:Y-m-d', 'after:ngay_ky'];

        return [
            'ma_nv' => ['required', 'regex:/\A[0-9]{5}\z/', 'exists:nhan_vien,ma_nv'],
            'ma_lhd' => ['required', 'integer', 'exists:loai_hop_dong,ma_lhd'],
            'ngay_ky' => ['required', 'date_format:Y-m-d'],
            'ngay_het_han' => $expiry,
            'luong_co_ban' => ['required', 'regex:/\A[0-9]+\z/', 'integer', 'min:0', 'max:999999999999999999'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectNonDisplayDates($validator, [
                'ngay_ky' => 'Ngày ký',
                'ngay_het_han' => 'Ngày hết hạn',
            ]);
        });
    }

    public function messages(): array
    {
        return [
            'ngay_ky.date_format' => 'Ngày ký phải có định dạng dd/mm/yyyy.',
            'ngay_het_han.required' => 'Ngày hết hạn là bắt buộc với hợp đồng có thời hạn.',
            'ngay_het_han.date_format' => 'Ngày hết hạn phải có định dạng dd/mm/yyyy.',
            'ngay_het_han.after' => 'Ngày hết hạn phải sau Ngày ký.',
            'luong_co_ban.regex' => 'Lương cơ bản phải là số nguyên hợp lệ.',
            'luong_co_ban.integer' => 'Lương cơ bản phải là số nguyên hợp lệ.',
        ];
    }
}
