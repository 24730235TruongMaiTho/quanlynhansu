<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum LuongPermission: string implements PermissionDefinitionContract
{
    case Xem = 'Luong.Read';
    case Tao = 'Luong.Insert';
    case Sua = 'Luong.Update';
    case Xoa = 'Luong.Delete';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 33, self::Tao => 34, self::Sua => 35, self::Xoa => 36,
        };
    }

    public function symbol(): string { return $this->value; }
    public function module(): string { return 'Luong'; }

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
