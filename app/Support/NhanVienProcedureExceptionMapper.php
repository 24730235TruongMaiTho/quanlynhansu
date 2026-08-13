<?php

namespace App\Support;

use App\Exceptions\NhanVienDomainException;
use Illuminate\Database\QueryException;

final class NhanVienProcedureExceptionMapper
{
    private const ERRORS = [
        'NV_NOT_FOUND' => ['Không tìm thấy nhân viên.', null],
        'NV_EMAIL_DUPLICATE' => ['Email đã được sử dụng.', 'email'],
        'NV_CCCD_DUPLICATE' => ['Số CCCD đã được sử dụng.', 'cccd'],
        'NV_REFERENCE_INVALID' => ['Dữ liệu tham chiếu không hợp lệ.', null],
        'NV_CODE_EXHAUSTED' => ['Đã hết mã nhân viên để cấp.', null],
        'NV_STATUS_MISSING' => ['Trạng thái làm việc không hợp lệ.', 'ma_tt'],
        'NV_DEFAULT_ROLE_INVALID' => ['Vai trò mặc định không hợp lệ.', null],
        'NV_PRIVILEGED_TARGET' => ['Bạn không có quyền thực hiện thao tác này.', null],
        'NV_AUTH_HASH_STALE' => ['Thông tin đăng nhập không hợp lệ.', null],
        'NV_PAGINATION_INVALID' => ['Thông tin phân trang không hợp lệ.', null],
    ];

    public function map(QueryException $exception): NhanVienDomainException
    {
        $databaseMessage = $exception->getPrevious()?->getMessage() ?? '';

        if (preg_match('/\b(NV_(?:NOT_FOUND|EMAIL_DUPLICATE|CCCD_DUPLICATE|REFERENCE_INVALID|CODE_EXHAUSTED|STATUS_MISSING|DEFAULT_ROLE_INVALID|PRIVILEGED_TARGET|AUTH_HASH_STALE|PAGINATION_INVALID))\b/', $databaseMessage, $matches)) {
            return $this->domainException($matches[1]);
        }

        if ($this->hasConstraintName($databaseMessage, 'uq_nhan_vien_email')) {
            return $this->domainException('NV_EMAIL_DUPLICATE');
        }

        if ($this->hasConstraintName($databaseMessage, 'uq_nhan_vien_cccd')) {
            return $this->domainException('NV_CCCD_DUPLICATE');
        }

        return new NhanVienDomainException(
            'Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.',
            'NV_DATABASE_ERROR',
        );
    }

    private function domainException(string $domainCode): NhanVienDomainException
    {
        [$message, $field] = self::ERRORS[$domainCode];

        return new NhanVienDomainException($message, $domainCode, $field);
    }

    private function hasConstraintName(string $databaseMessage, string $constraintName): bool
    {
        return preg_match(
            '/(?<![A-Za-z0-9_])'.preg_quote($constraintName, '/').'(?![A-Za-z0-9_])/i',
            $databaseMessage,
        ) === 1;
    }
}
