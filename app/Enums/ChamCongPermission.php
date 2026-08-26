<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum ChamCongPermission: string implements PermissionDefinitionContract
{
    case Xem = 'ChamCong.Read';
    case Tao = 'ChamCong.Insert';
    case Sua = 'ChamCong.Update';
    case Xoa = 'ChamCong.Delete';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 29, self::Tao => 30, self::Sua => 31, self::Xoa => 32,
        };
    }

    public function symbol(): string { return $this->value; }
    public function module(): string { return 'ChamCong'; }

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
