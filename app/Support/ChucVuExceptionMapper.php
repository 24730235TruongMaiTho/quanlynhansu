<?php

namespace App\Support;

use App\Exceptions\ChucVuDomainException;
use Illuminate\Database\QueryException;

final class ChucVuExceptionMapper
{
    private const ERRORS = [
        'CV_ID_INVALID' => ['Chức vụ không hợp lệ.', null],
        'CV_NOT_FOUND' => ['Không tìm thấy chức vụ.', null],
        'CV_NAME_REQUIRED' => ['Tên chức vụ không được để trống.', 'ten_cv'],
        'CV_NAME_TOO_LONG' => ['Tên chức vụ không được dài quá 100 ký tự.', 'ten_cv'],
        'CV_NAME_DUPLICATE' => ['Tên chức vụ đã tồn tại.', 'ten_cv'],
        'CV_RATE_REQUIRED' => ['Hệ số phụ cấp không được để trống.', 'he_so_phu_cap'],
        'CV_RATE_INVALID' => ['Hệ số phụ cấp phải là số từ 0 đến 99.99, tối đa 2 chữ số thập phân.', 'he_so_phu_cap'],
        'CV_IN_USE' => ['Không thể xóa chức vụ vì đang có nhân viên thuộc chức vụ này.', 'chuc_vu'],
    ];

    public function map(QueryException $exception): ChucVuDomainException
    {
        $message = $exception->getMessage().' '.($exception->getPrevious()?->getMessage() ?? '');

        if (preg_match(
            "/Duplicate entry\\b.*\\bfor key (?:'|`)?(?:[A-Za-z0-9_]+\\.)?(?:uq_chuc_vu_ten|ten_cv)(?:'|`)?/i",
            $message,
        ) === 1 || preg_match('/unique constraint failed:\s*chuc_vu\.ten_cv/i', $message) === 1) {
            return $this->domainException('CV_NAME_DUPLICATE');
        }

        if (preg_match('/(?:foreign key constraint fails|foreign key constraint failed|cannot delete or update a parent row)/i', $message) === 1
            && preg_match('/(?:chuc_vu|nhan_vien)/i', $message) === 1) {
            return $this->domainException('CV_IN_USE');
        }

        if (preg_match('/\b(CV_(?:ID_INVALID|NOT_FOUND|NAME_REQUIRED|NAME_TOO_LONG|NAME_DUPLICATE|RATE_REQUIRED|RATE_INVALID|IN_USE))\b/', $message, $matches)) {
            return $this->domainException($matches[1]);
        }

        return new ChucVuDomainException(
            'Không thể xử lý chức vụ lúc này. Vui lòng thử lại sau.',
            'CV_DATABASE_ERROR',
        );
    }

    private function domainException(string $code): ChucVuDomainException
    {
        [$message, $field] = self::ERRORS[$code];

        return new ChucVuDomainException($message, $code, $field);
    }
}
