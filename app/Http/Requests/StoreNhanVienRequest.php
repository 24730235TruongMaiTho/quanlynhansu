<?php

namespace App\Http\Requests;

use App\Rules\Du18TuoiTaiNgayVaoLam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class StoreNhanVienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = $this->normalizeStrings($this->all());

        if (isset($normalized['email']) && is_string($normalized['email'])) {
            $normalized['email'] = Str::lower($normalized['email']);
        }

        foreach (['gioi_tinh', 'ma_pb', 'ma_cv', 'ma_tt'] as $field) {
            if (isset($normalized[$field])
                && is_string($normalized[$field])
                && preg_match('/\A[0-9]+\z/', $normalized[$field]) === 1) {
                $normalized[$field] = (int) $normalized[$field];
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'ho_ten' => ['required', 'string', 'max:50'],
            'ngay_sinh' => ['required', 'date_format:Y-m-d', new Du18TuoiTaiNgayVaoLam],
            'gioi_tinh' => ['required', 'integer', Rule::in([0, 1])],
            'sdt' => ['required', 'string', 'regex:/\A0[0-9]{9}\z/'],
            'email' => ['required', 'string', 'email:rfc', 'max:100', $this->emailUniqueRule()],
            'ngay_vao_lam' => ['required', 'date_format:Y-m-d'],
            'ma_pb' => ['required', 'integer', Rule::exists('phong_ban', 'ma_pb')],
            'ma_cv' => ['required', 'integer', Rule::exists('chuc_vu', 'ma_cv')],
            'dan_toc' => ['required', 'string', 'max:50'],
            'cccd' => ['required', 'string', 'regex:/\A[0-9]{12}\z/', $this->cccdUniqueRule()],
            'noi_cap_cccd' => ['required', 'string', 'max:50'],
            'hoc_van' => ['required', 'string', 'max:50'],
            'ma_tt' => ['required', 'integer', $this->statusExistsRule()],
            'dia_chi_cu_the' => ['required', 'string', 'max:255'],
            'phuong_xa' => ['required', 'string', 'max:100'],
            'quan_huyen' => ['required', 'string', 'max:100'],
            'tinh_thanh' => ['required', 'string', 'max:100'],
            'anh_dai_dien' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'xoa_anh_dai_dien' => ['prohibited'],
            'ma_nv' => ['prohibited'],
            'ma_vt' => ['prohibited'],
            'mat_khau' => ['prohibited'],
            'mat_khau_hash' => ['prohibited'],
            'ngay_nghi_viec' => ['prohibited'],
        ];
    }

    protected function emailUniqueRule(): Unique
    {
        return Rule::unique('nhan_vien', 'email');
    }

    protected function cccdUniqueRule(): Unique
    {
        return Rule::unique('nhan_vien', 'cccd');
    }

    protected function statusExistsRule(): Exists
    {
        return Rule::exists('trang_thai_lam_viec', 'ma_tt')
            ->whereNot('ky_hieu', 'DA_NGHI');
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
