<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum NhanVienPermission: string implements PermissionDefinitionContract
{
    case Xem = 'NV_VIEW';
    case Tao = 'NV_CREATE';
    case Sua = 'NV_EDIT';
    case Xoa = 'NV_DELETE';
    case DatLaiMatKhau = 'NV_RESET_PASSWORD';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 101,
            self::Tao => 102,
            self::Sua => 103,
            self::Xoa => 104,
            self::DatLaiMatKhau => 105,
        };
    }

    public function symbol(): string
    {
        return $this->value;
    }

    public function module(): string
    {
        return 'NhanVien';
    }

    public function action(): ?PermissionAction
    {
        return match ($this) {
            self::Xem => PermissionAction::View,
            self::Tao => PermissionAction::Create,
            self::Sua => PermissionAction::Edit,
            self::Xoa => PermissionAction::Delete,
            self::DatLaiMatKhau => null,
        };
    }
}
