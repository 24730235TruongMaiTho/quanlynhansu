<?php

use App\Enums\NhanVienPermission;
use App\Enums\PhongBanPermission;
use App\Enums\ChucVuPermission;
use App\Enums\HopDongPermission;
use App\Enums\PhanQuyenPermission;
use App\Enums\VaiTroPermission;
use App\Enums\NghiPhepPermission;
use App\Enums\ChamCongPermission;
use App\Enums\LuongPermission;

return [
    // Các module mới chỉ cần đăng ký enum quyền canonical tại đây.
    'definitions' => [
        VaiTroPermission::class,
        NhanVienPermission::class,
        PhongBanPermission::class,
        ChucVuPermission::class,
        HopDongPermission::class,
        PhanQuyenPermission::class,
        NghiPhepPermission::class,
        ChamCongPermission::class,
        LuongPermission::class,
    ],
];
