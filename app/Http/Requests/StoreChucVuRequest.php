<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreChucVuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ten_cv') && is_string($this->input('ten_cv'))) {
            $this->merge(['ten_cv' => trim($this->input('ten_cv'))]);
        }
    }

    public function rules(): array
    {
        return [
            'ten_cv' => ['required', 'string', 'max:100', 'unique:chuc_vu,ten_cv'],
            'he_so_phu_cap' => ['required', 'numeric', 'min:0', 'max:99.99', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'ten_cv.required' => 'Tên chức vụ không được để trống.',
            'ten_cv.max' => 'Tên chức vụ không được dài quá 100 ký tự.',
            'ten_cv.unique' => 'Tên chức vụ đã tồn tại.',
            'he_so_phu_cap.required' => 'Hệ số phụ cấp không được để trống.',
            'he_so_phu_cap.numeric' => 'Hệ số phụ cấp phải là số.',
            'he_so_phu_cap.min' => 'Hệ số phụ cấp không được âm.',
            'he_so_phu_cap.max' => 'Hệ số phụ cấp không được lớn hơn 99.99.',
            'he_so_phu_cap.decimal' => 'Hệ số phụ cấp chỉ được có tối đa 2 chữ số thập phân.',
        ];
    }
}
