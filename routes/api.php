<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Backend\ChamCongController;
use App\Http\Controllers\Backend\ChucVuController;
use App\Http\Controllers\Backend\LuongController;
use App\Http\Controllers\Backend\NghiPhepController;
use App\Http\Controllers\Backend\LuongChucVuController;
use App\Http\Controllers\Backend\LuongPhongBanController;
use App\Http\Controllers\Backend\LuongHeSoLuongController;
use App\Http\Middleware\EnsureNhanVienModuleEnabled;

Route::middleware('api')
    ->prefix('v1')
    ->group(function (): void {

        /*
         * ==================================================
         * CHẤM CÔNG
         * ==================================================
         */

        Route::prefix('cham-cong')
            ->group(function (): void {

                Route::get(
                    'nhan-vien',
                    [ChamCongController::class, 'employees']
                )->middleware(EnsureNhanVienModuleEnabled::class)->name(
                    'api.v1.cham-cong.nhan-vien'
                );

                Route::get(
                    'phong-ban',
                    [ChamCongController::class, 'phongBan']
                )->name(
                    'api.v1.cham-cong.phong-ban'
                );
            });

        Route::apiResource(
            'cham-cong',
            ChamCongController::class
        )->only([
            'index',
            'update',
        ]);


        /*
         * ==================================================
         * NGHỈ PHÉP
         * ==================================================
         */

        Route::prefix('nghi-phep')
            ->group(function (): void {

                Route::get(
                    'nhan-vien',
                    [NghiPhepController::class, 'employees']
                )->middleware(EnsureNhanVienModuleEnabled::class);

                Route::post(
                    'nhan-vien',
                    [NghiPhepController::class, 'storeEmployee']
                )->middleware(EnsureNhanVienModuleEnabled::class);

                Route::match(
                    ['PUT', 'PATCH'],
                    'nhan-vien/{ma_nv}',
                    [NghiPhepController::class, 'updateEmployee']
                )->middleware(EnsureNhanVienModuleEnabled::class);

                Route::get(
                    'phong-ban',
                    [NghiPhepController::class, 'phongBan']
                );

                Route::get(
                    'chuc-vu',
                    [NghiPhepController::class, 'chucVu']
                );

                Route::get(
                    'loai-phep',
                    [NghiPhepController::class, 'loaiPhep']
                );

                Route::patch(
                    '{ma_np}/duyet',
                    [NghiPhepController::class, 'duyet']
                )->name(
                    'api.v1.nghi-phep.duyet'
                );
            });

        Route::apiResource(
            'nghi-phep',
            NghiPhepController::class
        );


        /*
         * ==================================================
         * LƯƠNG
         * ==================================================
         */

        Route::prefix('luong')
            ->group(function (): void {

                Route::get(
                    'phong-ban',
                    [LuongPhongBanController::class, 'index']
                )->name(
                    'api.v1.luong.phong-ban'
                );

                Route::get(
                    'chuc-vu',
                    [LuongChucVuController::class, 'index']
                )->name(
                    'api.v1.luong.chuc-vu'
                );

                Route::get(
                    'he-so-luong',
                    [LuongHeSoLuongController::class, 'index']
                )->name(
                    'api.v1.luong.he-so-luong'
                );

                Route::post(
                    'he-so-luong',
                    [LuongHeSoLuongController::class, 'store']
                )->name(
                    'api.v1.luong.he-so-luong.store'
                );

                Route::get(
                    'he-so-luong/{ma_ls}',
                    [LuongHeSoLuongController::class, 'show']
                )->name(
                    'api.v1.luong.he-so-luong.show'
                );

                Route::match(
                    ['PUT', 'PATCH'],
                    'he-so-luong/{ma_ls}',
                    [LuongHeSoLuongController::class, 'update']
                )->name(
                    'api.v1.luong.he-so-luong.update'
                );
            });

        Route::apiResource(
            'luong',
            LuongController::class
        );
    });
