<?php

namespace App\Enums;

enum PhongBanPermission: string
{
    case Xem = 'PHONG_BAN_XEM';
    case Tao = 'PHONG_BAN_TAO';
    case Sua = 'PHONG_BAN_SUA';
    case Xoa = 'PHONG_BAN_XOA';

    public function id(): int
    {
        return match ($this) {
            self::Xem => 201,
            self::Tao => 202,
            self::Sua => 203,
            self::Xoa => 204,
        };
    }
}
