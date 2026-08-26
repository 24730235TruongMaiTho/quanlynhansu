<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum VaiTroPermission: string implements PermissionDefinitionContract
{
    case Xem = 'VaiTro.Read';
    case Tao = 'VaiTro.Insert';
    case Sua = 'VaiTro.Update';
    case Xoa = 'VaiTro.Delete';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 1,
            self::Tao => 2,
            self::Sua => 3,
            self::Xoa => 4,
        };
    }

    public function symbol(): string { return $this->value; }
    public function module(): string { return 'VaiTro'; }

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
