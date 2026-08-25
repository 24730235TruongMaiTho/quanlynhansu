<?php

namespace App\Enums;

use App\Contracts\PermissionDefinitionContract;

enum PhanQuyenPermission: string implements PermissionDefinitionContract
{
    case Xem = 'PQ_ROLE_VIEW';
    case QuanLy = 'PQ_ROLE_MANAGE';

    public function id(): int { return $this === self::Xem ? 801 : 802; }
    public function symbol(): string { return $this->value; }
    public function module(): string { return 'PhanQuyen'; }
    public function action(): ?PermissionAction { return $this === self::Xem ? PermissionAction::View : null; }
}
