<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\ChamCongController;
use App\Http\Controllers\Backend\ChucVuController;
use App\Http\Controllers\Backend\LuongController;
use App\Http\Controllers\Backend\NghiPhepController;
use App\Http\Controllers\Backend\LuongChucVuController;
use App\Http\Controllers\Backend\LuongPhongBanController;

Route::middleware('api')->prefix('v1')->group(function () {
    Route::apiResource('cham-cong', ChamCongController::class);
    Route::apiResource('nghi-phep', NghiPhepController::class);

    // lookup endpoints for salary filters
    /*
     * Endpoint phụ thuộc nghiệp vụ module lương.
     * Khai báo route cố định trước resource lương.
     */
    Route::prefix('luong')->group(function (): void {
        Route::get(
            'phong-ban',
            [LuongPhongBanController::class, 'index']
        )->name('api.v1.luong.phong-ban');

        Route::get(
            'chuc-vu',
            [LuongChucVuController::class, 'index']
        )->name('api.v1.luong.chuc-vu');
    });
    Route::apiResource('luong', LuongController::class);
});

