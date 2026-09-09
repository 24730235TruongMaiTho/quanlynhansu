<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum NghiPhepPermission: string implements PermissionDefinitionContract
{
    case Xem = 'NghiPhep.Read';
    case Tao = 'NghiPhep.Insert';
    case Sua = 'NghiPhep.Update';
    case Xoa = 'NghiPhep.Delete';
    case Duyet = 'NghiPhep.Approve';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 25, self::Tao => 26, self::Sua => 27, self::Xoa => 28,
            self::Duyet => 43,
        };
    }

    public function symbol(): string { return $this->value; }
    public function module(): string { return 'NghiPhep'; }

    public function action(): ?PermissionAction
    {
        return match ($this) {
            self::Xem => PermissionAction::View,
            self::Tao => PermissionAction::Create,
            self::Sua => PermissionAction::Edit,
            self::Xoa => PermissionAction::Delete,
            self::Duyet => null,
        };
    }
}
