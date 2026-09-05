<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum HeSoLuongPermission: string implements PermissionDefinitionContract
{
    case Xem = 'HeSoLuong.Read';
    case Tao = 'HeSoLuong.Insert';
    case Sua = 'HeSoLuong.Update';
    case Xoa = 'HeSoLuong.Delete';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 38,
            self::Tao => 39,
            self::Sua => 40,
            self::Xoa => 41,
        };
    }

    public function symbol(): string { return $this->value; }
    public function module(): string { return 'HeSoLuong'; }

    public function action(): PermissionAction
    {
        return match ($this) {
            self::Xem => PermissionAction::View,
            self::Tao => PermissionAction::Create,
            self::Sua => PermissionAction::Edit,
            self::Xoa => PermissionAction::Delete,
        };
    }
}
