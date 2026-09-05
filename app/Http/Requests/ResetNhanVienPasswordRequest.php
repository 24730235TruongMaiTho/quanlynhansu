<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ResetNhanVienPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Password reset derives both target and password server-side. These
     * fields are deliberately prohibited so a crafted body cannot override
     * the route target or provide plaintext/hash material.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_fill_keys([
            'ma_nv',
            'target_ma_nv',
            'mat_khau',
            'mat_khau_hash',
            'password',
            'password_hash',
            'new_password',
            'new_password_confirmation',
        ], ['prohibited']);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.prohibited' => 'Không được gửi dữ liệu mật khẩu hoặc mục tiêu trong yêu cầu này.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ma_nv' => 'mã nhân viên',
            'target_ma_nv' => 'mục tiêu đặt lại mật khẩu',
            'mat_khau' => 'mật khẩu',
            'mat_khau_hash' => 'mã băm mật khẩu',
            'password' => 'mật khẩu',
            'password_hash' => 'mã băm mật khẩu',
            'new_password' => 'mật khẩu mới',
            'new_password_confirmation' => 'xác nhận mật khẩu mới',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, ['_token', '_method'], true)) {
                    $validator->errors()->add(
                        $field,
                        'Yêu cầu đặt lại mật khẩu không nhận dữ liệu từ phía người dùng.',
                    );
                }
            }
        });
    }
}
