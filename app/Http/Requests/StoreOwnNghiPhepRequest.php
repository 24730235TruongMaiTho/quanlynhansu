<?php

namespace App\Http\Requests;

use App\Support\NormalizesDisplayDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreOwnNghiPhepRequest extends FormRequest
{
    use NormalizesDisplayDates;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $this->normalizeDisplayDateFields(['tu_ngay', 'den_ngay']);

        return [
            'ma_nv' => ['prohibited'],
            'tu_ngay' => ['required', 'date_format:Y-m-d'],
            'den_ngay' => ['required', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
            'ma_lp' => ['required', 'integer'],
            'ly_do' => ['required', 'string', 'max:255'],
            'trang_thai_duyet' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'ma_nv.prohibited' => 'Không được tự chọn nhân viên.',
            'trang_thai_duyet.prohibited' => 'Trạng thái duyệt do hệ thống thiết lập.',
            'tu_ngay.required' => 'Ngày bắt đầu không được để trống.',
            'den_ngay.required' => 'Ngày kết thúc không được để trống.',
            'den_ngay.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'ma_lp.required' => 'Loại phép không được để trống.',
            'ly_do.required' => 'Lý do không được để trống.',
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
