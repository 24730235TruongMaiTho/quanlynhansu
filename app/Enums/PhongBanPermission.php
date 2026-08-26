<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum PhongBanPermission: string implements PermissionDefinitionContract
{
    case Xem = 'PhongBan.Read';
    case Tao = 'PhongBan.Insert';
    case Sua = 'PhongBan.Update';
    case Xoa = 'PhongBan.Delete';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 9,
            self::Tao => 10,
            self::Sua => 11,
            self::Xoa => 12,
        };
    }

    public function symbol(): string
    {
        return $this->value;
    }

    public function module(): string
    {
        return 'PhongBan';
    }

    public function action(): ?PermissionAction
    {
        return match ($this) {
            self::Xem => PermissionAction::View,
            self::Tao => PermissionAction::Create,
            self::Sua => PermissionAction::Edit,
            self::Xoa => PermissionAction::Delete,
        };
    }
}
