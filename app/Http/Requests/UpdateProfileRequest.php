<?php

namespace App\Http\Requests;

use App\Models\NhanVien;
use App\Support\NormalizesDisplayDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProfileRequest extends FormRequest
{
    use NormalizesDisplayDates;

    public function authorize(): bool
    {
        return $this->user() instanceof NhanVien;
    }

    protected function prepareForValidation(): void
    {
        $normalized = $this->normalizeStrings($this->all());

        if (isset($normalized['email']) && is_string($normalized['email'])) {
            $normalized['email'] = Str::lower($normalized['email']);
        }

        if (isset($normalized['gioi_tinh'])
            && is_string($normalized['gioi_tinh'])
            && preg_match('/\A[0-9]+\z/', $normalized['gioi_tinh']) === 1) {
            $normalized['gioi_tinh'] = (int) $normalized['gioi_tinh'];
        }

        foreach (['dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh'] as $field) {
            if (array_key_exists($field, $normalized) && $normalized[$field] === '') {
                $normalized[$field] = null;
            }
        }

        $this->merge($normalized);
        $this->normalizeDisplayDateFields(['ngay_sinh']);
    }

    public function rules(): array
    {
        $actorId = $this->user()?->getAuthIdentifier();

        $rules = [
            'ho_ten' => ['required', 'string', 'max:50'],
            'ngay_sinh' => ['bail', 'required', 'date_format:Y-m-d'],
            'gioi_tinh' => ['required', 'integer', Rule::in([0, 1])],
            'sdt' => ['required', 'string', 'regex:/\A0[0-9]{9}\z/'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:100',
                Rule::unique('nhan_vien', 'email')->ignore($actorId, 'ma_nv'),
                function (string $attribute, mixed $value, \Closure $fail) use ($actorId): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $query = DB::table('nhan_vien')
                        ->whereRaw('LOWER(TRIM(email)) = ?', [Str::lower(trim($value))]);
                    if ($actorId !== null) {
                        $query->where('ma_nv', '<>', $actorId);
                    }

                    if ($query->exists()) {
                        $fail('Email đã tồn tại.');
                    }
                },
            ],
            'dan_toc' => ['required', 'string', 'max:50'],
            'cccd' => [
                'required', 'string', 'regex:/\A[0-9]{12}\z/',
                Rule::unique('nhan_vien', 'cccd')->ignore($actorId, 'ma_nv'),
            ],
            'noi_cap_cccd' => ['required', 'string', 'max:50'],
            'hoc_van' => ['required', 'string', 'max:50'],
            'dia_chi_cu_the' => ['nullable', 'string', 'max:255'],
            'phuong_xa' => ['nullable', 'string', 'max:100'],
            'quan_huyen' => ['nullable', 'string', 'max:100'],
            'tinh_thanh' => ['nullable', 'string', 'max:100'],
            'anh_dai_dien' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'xoa_anh_dai_dien' => ['sometimes', 'boolean'],
        ];

        foreach ([
            'ma_nv', 'ngay_vao_lam', 'ma_pb', 'ma_cv', 'ma_vt', 'ma_tt',
            'ngay_nghi_viec', 'mat_khau', 'mat_khau_hash', 'password',
            'password_hash', 'current_password', 'new_password',
            'password_confirmation',
        ] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->hasFile('anh_dai_dien')
                && in_array($this->input('xoa_anh_dai_dien'), [true, 1, '1'], true)) {
                $validator->errors()->add(
                    'xoa_anh_dai_dien',
                    'Không thể đồng thời tải ảnh mới và yêu cầu xóa ảnh đại diện.',
                );
            }

            if ($this->input('ngay_sinh') !== null
                && $this->input('ngay_sinh') !== ''
                && ! $validator->errors()->has('ngay_sinh')
                && ! $this->wasDisplayDateNormalized('ngay_sinh')) {
                $validator->errors()->add('ngay_sinh', 'Ngày sinh không đúng định dạng dd/mm/yyyy.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ho_ten' => 'Họ và tên', 'ngay_sinh' => 'Ngày sinh', 'gioi_tinh' => 'Giới tính',
            'sdt' => 'Số điện thoại', 'email' => 'Email', 'dan_toc' => 'Dân tộc',
            'cccd' => 'Số CCCD', 'noi_cap_cccd' => 'Nơi cấp CCCD', 'hoc_van' => 'Trình độ học vấn',
            'dia_chi_cu_the' => 'Địa chỉ cụ thể', 'phuong_xa' => 'Phường/Xã',
            'quan_huyen' => 'Quận/Huyện', 'tinh_thanh' => 'Tỉnh/Thành phố',
            'anh_dai_dien' => 'Ảnh đại diện', 'xoa_anh_dai_dien' => 'Xóa ảnh đại diện',
            'ma_nv' => 'Mã nhân viên', 'ngay_vao_lam' => 'Ngày vào làm',
            'ma_pb' => 'Mã phòng ban', 'ma_cv' => 'Mã chức vụ', 'ma_vt' => 'Mã vai trò',
            'ma_tt' => 'Mã trạng thái', 'ngay_nghi_viec' => 'Ngày nghỉ việc',
            'mat_khau' => 'Mật khẩu', 'mat_khau_hash' => 'Mã băm mật khẩu',
            'password' => 'Mật khẩu', 'password_hash' => 'Mã băm mật khẩu',
            'current_password' => 'Mật khẩu hiện tại', 'new_password' => 'Mật khẩu mới',
            'password_confirmation' => 'Xác nhận mật khẩu mới',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'date_format' => ':attribute không đúng định dạng dd/mm/yyyy.',
            'email' => ':attribute không đúng định dạng.',
            'regex' => ':attribute không đúng định dạng.',
            'unique' => ':attribute đã tồn tại.',
            'prohibited' => ':attribute không được phép cập nhật từ hồ sơ cá nhân.',
            'different' => ':attribute phải khác mật khẩu hiện tại.',
        ];
    }

    private function normalizeStrings(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeStrings($item);
        }

        return $value;
    }
}
