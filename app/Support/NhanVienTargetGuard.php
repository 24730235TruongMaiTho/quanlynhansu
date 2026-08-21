<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;

final class NhanVienTargetGuard
{
    public function assertManageable(object $employee): void
    {
        if (($employee->ky_hieu_vai_tro ?? null) !== 'NHAN_VIEN_MAC_DINH') {
            throw new AuthorizationException;
        }
    }
}
