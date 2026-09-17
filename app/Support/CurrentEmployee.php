<?php

namespace App\Support;

use App\Models\NhanVien;

final class CurrentEmployee
{
    public function id(?object $actor): string
    {
        abort_unless(
            $actor instanceof NhanVien,
            403,
            'Không xác định được nhân viên hiện tại.',
        );

        $identifier = $actor->getAuthIdentifier();

        abort_unless(
            is_string($identifier) && preg_match('/\A[0-9]{5}\z/', $identifier) === 1,
            403,
            'Không xác định được nhân viên hiện tại.',
        );

        return $identifier;
    }
}
