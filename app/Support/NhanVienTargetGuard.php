<?php

namespace App\Support;

use App\Enums\NhanVienRole;
use Illuminate\Auth\Access\AuthorizationException;

final class NhanVienTargetGuard
{
    public function assertManageable(object $employee): void
    {
        if ((int) ($employee->ma_vt ?? 0) !== NhanVienRole::Employee->value) {
            throw new AuthorizationException;
        }
    }
}
