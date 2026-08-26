<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Backend\ChamCongController;
use App\Http\Controllers\Backend\LuongController;
use App\Http\Controllers\Backend\NghiPhepController;
use App\Http\Controllers\Backend\LuongChucVuController;
use App\Http\Controllers\Backend\LuongPhongBanController;
use App\Http\Controllers\Backend\LuongHeSoLuongController;
use App\Enums\ChamCongPermission;
use App\Enums\NghiPhepPermission;
use App\Enums\LuongPermission;

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
                )
                ->middleware([
                    'web',
                    'auth',
                    'can:'.ChamCongPermission::Xem->value,
                ])
                ->name(
                    'api.v1.cham-cong.nhan-vien'
                );

                Route::get(
                    'phong-ban',
                    [ChamCongController::class, 'phongBan']
                )
                ->middleware([
                    'web',
                    'auth',
                    'can:'.ChamCongPermission::Xem->value,
                ])
                ->name(
                    'api.v1.cham-cong.phong-ban'
                );
            });

        Route::apiResource(
            'cham-cong',
            ChamCongController::class
        )->only([
            'index',
        ])->middleware([
            'web',
            'auth',
            'can:'.ChamCongPermission::Xem->value,
        ]);

        Route::apiResource(
            'cham-cong',
            ChamCongController::class
        )->only([
            'update',
        ])->middleware([
            'web',
            'auth',
            'can:'.ChamCongPermission::Sua->value,
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
                )
                ->middleware([
                    'web',
                    'auth',
                    'can:'.NghiPhepPermission::Xem->value,
                ])->name(
                    'api.v1.nghi-phep.nhan-vien'
                );

                Route::get(
                    'phong-ban',
                    [NghiPhepController::class, 'phongBan']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.NghiPhepPermission::Xem->value,
                ])->name(
                    'api.v1.nghi-phep.phong-ban'
                );

                Route::get(
                    'chuc-vu',
                    [NghiPhepController::class, 'chucVu']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.NghiPhepPermission::Xem->value,
                ])->name(
                    'api.v1.nghi-phep.chuc-vu'
                );

                Route::get(
                    'loai-phep',
                    [NghiPhepController::class, 'loaiPhep']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.NghiPhepPermission::Xem->value,
                ])->name(
                    'api.v1.nghi-phep.loai-phep'
                );

                Route::patch(
                    '{ma_np}/duyet',
                    [NghiPhepController::class, 'duyet']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.NghiPhepPermission::Sua->value,
                ])->name(
                    'api.v1.nghi-phep.duyet'
                );
            });

        Route::apiResource(
            'nghi-phep',
            NghiPhepController::class
        )->only([
            'index',
            'show',
        ])->middleware([
            'web',
            'auth',
            'can:'.NghiPhepPermission::Xem->value,
        ]);

        Route::apiResource(
            'nghi-phep',
            NghiPhepController::class
        )->only([
            'store',
        ])->middleware([
            'web',
            'auth',
            'can:'.NghiPhepPermission::Tao->value,
        ]);

        Route::apiResource(
            'nghi-phep',
            NghiPhepController::class
        )->only([
            'update',
        ])->middleware([
            'web',
            'auth',
            'can:'.NghiPhepPermission::Sua->value,
        ]);

        Route::apiResource(
            'nghi-phep',
            NghiPhepController::class
        )->only([
            'destroy',
        ])->middleware([
            'web',
            'auth',
            'can:'.NghiPhepPermission::Xoa->value,
        ]);


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
                )->middleware([
                    'web',
                    'auth',
                    'can:'.LuongPermission::Xem->value,
                ])->name(
                    'api.v1.luong.phong-ban'
                );

                Route::get(
                    'chuc-vu',
                    [LuongChucVuController::class, 'index']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.LuongPermission::Xem->value,
                ])->name(
                    'api.v1.luong.chuc-vu'
                );

                Route::get(
                    'he-so-luong',
                    [LuongHeSoLuongController::class, 'index']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.LuongPermission::Xem->value,
                ])->name(
                    'api.v1.luong.he-so-luong'
                );

                Route::post(
                    'he-so-luong',
                    [LuongHeSoLuongController::class, 'store']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.LuongPermission::Tao->value,
                ])->name(
                    'api.v1.luong.he-so-luong.store'
                );

                Route::get(
                    'he-so-luong/{ma_ls}',
                    [LuongHeSoLuongController::class, 'show']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.LuongPermission::Xem->value,
                ])->name(
                    'api.v1.luong.he-so-luong.show'
                );

                Route::match(
                    ['PUT', 'PATCH'],
                    'he-so-luong/{ma_ls}',
                    [LuongHeSoLuongController::class, 'update']
                )->middleware([
                    'web',
                    'auth',
                    'can:'.LuongPermission::Sua->value,
                ])->name(
                    'api.v1.luong.he-so-luong.update'
                );
            });

        Route::apiResource(
            'luong',
            LuongController::class
        )->only([
            'index',
            'show',
        ])->middleware([
            'web',
            'auth',
            'can:'.LuongPermission::Xem->value,
        ]);

        Route::apiResource(
            'luong',
            LuongController::class
        )->only([
            'store',
        ])->middleware([
            'web',
            'auth',
            'can:'.LuongPermission::Tao->value,
        ]);

        Route::apiResource(
            'luong',
            LuongController::class
        )->only([
            'update',
        ])->middleware([
            'web',
            'auth',
            'can:'.LuongPermission::Sua->value,
        ]);

        Route::apiResource(
            'luong',
            LuongController::class
        )->only([
            'destroy',
        ])->middleware([
            'web',
            'auth',
            'can:'.LuongPermission::Xoa->value,
        ]);
    });
