<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum NhanVienPermission: string implements PermissionDefinitionContract
{
    case Xem = 'NhanVien.Read';
    case Tao = 'NhanVien.Insert';
    case Sua = 'NhanVien.Update';
    case Xoa = 'NhanVien.Delete';
    case DatLaiMatKhau = 'NhanVien.ResetPassword';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 17,
            self::Tao => 18,
            self::Sua => 19,
            self::Xoa => 20,
            self::DatLaiMatKhau => 42,
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
