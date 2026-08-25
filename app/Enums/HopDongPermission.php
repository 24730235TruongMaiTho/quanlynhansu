<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum HopDongPermission: string implements PermissionDefinitionContract
{
    case Xem = 'HD_VIEW';
    case Tao = 'HD_CREATE';
    case Sua = 'HD_EDIT';
    case Xoa = 'HD_DELETE';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 401, self::Tao => 402, self::Sua => 403, self::Xoa => 404,
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
