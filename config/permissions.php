<?php

use App\Enums\NhanVienPermission;
use App\Enums\PhongBanPermission;
use App\Enums\ChucVuPermission;

return [
    // Add future module permission enums here; provider/service stay unchanged.
    'definitions' => [
        NhanVienPermission::class,
        PhongBanPermission::class,
        ChucVuPermission::class,
    ],
];
