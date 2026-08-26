<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum ChucVuPermission: string implements PermissionDefinitionContract
{
    case Xem = 'ChucVu.Read';
    case Tao = 'ChucVu.Insert';
    case Sua = 'ChucVu.Update';
    case Xoa = 'ChucVu.Delete';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 13,
            self::Tao => 14,
            self::Sua => 15,
            self::Xoa => 16,
        };
    }

    public function symbol(): string
    {
        return $this->value;
    }

    public function module(): string
    {
        return 'ChucVu';
    }

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
