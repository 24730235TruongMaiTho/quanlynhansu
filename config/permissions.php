<?php

use App\Enums\NhanVienPermission;
use App\Enums\PhongBanPermission;

return [
    // Add future module permission enums here; provider/service stay unchanged.
    'definitions' => [
        NhanVienPermission::class,
        PhongBanPermission::class,
    ],
];
