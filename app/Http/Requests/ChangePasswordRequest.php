<?php

namespace App\Http\Requests;

use App\Models\NhanVien;
use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof NhanVien;
    }

    public function rules(): array
    {
        return [
            'mat_khau_hien_tai' => ['required', 'string'],
            'mat_khau_moi' => ['required', 'string', 'min:8', 'confirmed', 'different:mat_khau_hien_tai'],
            'mat_khau_moi_confirmation' => ['required', 'string'],
            'ma_nv' => ['prohibited'],
            'target_ma_nv' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'mat_khau_hien_tai' => 'Mật khẩu hiện tại',
            'mat_khau_moi' => 'Mật khẩu mới',
            'mat_khau_moi_confirmation' => 'Xác nhận mật khẩu mới',
            'ma_nv' => 'Mã nhân viên',
            'target_ma_nv' => 'Tài khoản đích',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'min' => ':attribute phải có ít nhất 8 ký tự.',
            'confirmed' => 'Xác nhận mật khẩu mới không khớp.',
            'different' => ':attribute phải khác mật khẩu hiện tại.',
            'prohibited' => ':attribute không được phép gửi từ biểu mẫu này.',
        ];
    }
}
