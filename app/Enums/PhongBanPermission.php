<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum PhongBanPermission: string implements PermissionDefinitionContract
{
    case Xem = 'PB_VIEW';
    case Tao = 'PB_CREATE';
    case Sua = 'PB_EDIT';
    case Xoa = 'PB_DELETE';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 201,
            self::Tao => 202,
            self::Sua => 203,
            self::Xoa => 204,
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
