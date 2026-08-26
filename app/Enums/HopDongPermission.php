<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum HopDongPermission: string implements PermissionDefinitionContract
{
    case Xem = 'HopDong.Read';
    case Tao = 'HopDong.Insert';
    case Sua = 'HopDong.Update';
    case Xoa = 'HopDong.Delete';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 21, self::Tao => 22, self::Sua => 23, self::Xoa => 24,
        };
    }

    public function symbol(): string { return $this->value; }
    public function module(): string { return 'HopDong'; }

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
