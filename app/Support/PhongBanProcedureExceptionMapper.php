<?php

namespace App\Support;

use App\Exceptions\PhongBanDomainException;
use Illuminate\Database\QueryException;

final class PhongBanProcedureExceptionMapper
{
    private const ERRORS = [
        'PB_ID_INVALID' => ['Phòng ban không hợp lệ.', null],
        'PB_NOT_FOUND' => ['Không tìm thấy phòng ban.', null],
        'PB_NAME_REQUIRED' => ['Tên phòng ban không được để trống.', 'ten_pb'],
        'PB_NAME_TOO_LONG' => ['Tên phòng ban không được dài quá 100 ký tự.', 'ten_pb'],
        'PB_NAME_DUPLICATE' => ['Tên phòng ban đã tồn tại.', 'ten_pb'],
        'PB_IN_USE' => ['Không thể xóa phòng ban vì đang có nhân viên thuộc phòng ban này.', 'phong_ban'],
    ];

    public function map(QueryException $exception): PhongBanDomainException
    {
        $databaseMessage = $exception->getPrevious()?->getMessage() ?? '';

        if (preg_match(
            "/Duplicate entry\\b.*\\bfor key (?:'uq_phong_ban_ten_pb'|\\x60uq_phong_ban_ten_pb\\x60)/i",
            $databaseMessage,
        ) === 1) {
            return $this->domainException('PB_NAME_DUPLICATE');
        }

        if (preg_match('/\b(PB_(?:ID_INVALID|NOT_FOUND|NAME_REQUIRED|NAME_TOO_LONG|NAME_DUPLICATE|IN_USE))\b/', $databaseMessage, $matches)) {
            return $this->domainException($matches[1]);
        }

        return new PhongBanDomainException(
            'Không thể xử lý phòng ban lúc này. Vui lòng thử lại sau.',
            'PB_DATABASE_ERROR',
        );
    }

    private function domainException(string $domainCode): PhongBanDomainException
    {
        [$message, $field] = self::ERRORS[$domainCode];

        return new PhongBanDomainException($message, $domainCode, $field);
    }
}
