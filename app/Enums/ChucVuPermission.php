<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum ChucVuPermission: string implements PermissionDefinitionContract
{
    case Xem = 'CV_VIEW';
    case Tao = 'CV_CREATE';
    case Sua = 'CV_EDIT';
    case Xoa = 'CV_DELETE';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 301,
            self::Tao => 302,
            self::Sua => 303,
            self::Xoa => 304,
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
