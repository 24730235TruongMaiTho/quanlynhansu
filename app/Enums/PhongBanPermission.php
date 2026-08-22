<?php

namespace App\Enums;

enum PhongBanPermission: string
{
    case Xem = 'PHONG_BAN_XEM';
    case Tao = 'PHONG_BAN_TAO';
    case Sua = 'PHONG_BAN_SUA';
    case Xoa = 'PHONG_BAN_XOA';
}
