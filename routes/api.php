<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\ChamCongController;
use App\Http\Controllers\Backend\ChucVuController;
use App\Http\Controllers\Backend\LuongController;
use App\Http\Controllers\Backend\NghiPhepController;
use App\Http\Controllers\Backend\LuongChucVuController;
use App\Http\Controllers\Backend\LuongPhongBanController;
use App\Http\Controllers\Backend\LuongHeSoLuongController;

Route::middleware('api')->prefix('v1')->group(function () {
    Route::apiResource('cham-cong', ChamCongController::class);
    // additional endpoints used by frontend nghiphep module
    Route::prefix('nghi-phep')->group(function () {
        Route::get('nhan-vien', [NghiPhepController::class, 'employees']);
        Route::post('nhan-vien', [NghiPhepController::class, 'storeEmployee']);
        Route::match(['PUT','PATCH'], 'nhan-vien/{ma_nv}', [NghiPhepController::class, 'updateEmployee']);

        Route::get('phong-ban', [NghiPhepController::class, 'phongBan']);
        Route::get('chuc-vu', [NghiPhepController::class, 'chucVu']);
        Route::get('loai-phep', [NghiPhepController::class, 'loaiPhep']);
    });

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

        // hệ số lương cho nhân viên
        Route::get(
            'he-so-luong',
            [LuongHeSoLuongController::class, 'index']
        )->name('api.v1.luong.he-so-luong');

        // create
        Route::post(
            'he-so-luong',
            [LuongHeSoLuongController::class, 'store']
        )->name('api.v1.luong.he-so-luong.store');

        // show single he-so-luong by id
        Route::get(
            'he-so-luong/{ma_ls}',
            [LuongHeSoLuongController::class, 'show']
        )->name('api.v1.luong.he-so-luong.show');

        // update (PUT) and partial update (PATCH)
        Route::match(
            ['PUT', 'PATCH'],
            'he-so-luong/{ma_ls}',
            [LuongHeSoLuongController::class, 'update']
        )->name('api.v1.luong.he-so-luong.update');
    });
    Route::apiResource('luong', LuongController::class);
});

