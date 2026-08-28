<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Backend\DashboardController;
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

        Route::middleware('web')
            ->prefix('auth')
            ->group(function (): void {
                Route::get('me', [CurrentUserController::class, 'me'])
                    ->middleware(['auth'])
                    ->name('api.v1.auth.me');
            });

        /*
         * ==================================================
         * CHẤM CÔNG
         * ==================================================
         */

        Route::middleware([
            'web',
            'auth',
        ])
            ->prefix('cham-cong')
            ->group(function (): void {

                Route::get(
                    'nhan-vien',
                    [ChamCongController::class, 'employees']
                )->middleware(['auth'])
                ->name(
                    'api.v1.cham-cong.nhan-vien'
                );

                Route::get(
                    'phong-ban',
                    [ChamCongController::class, 'phongBan']
                )->middleware(['auth'])
                ->name(
                    'api.v1.cham-cong.phong-ban'
                );
            });

        Route::prefix('cham-cong')
            ->middleware([
                'web',
                'auth',
            ])
            ->group(function (): void {

                /*
                 * Export dữ liệu chấm công
                 *
                 * GET /api/v1/cham-cong/export
                 * ?thang=8
                 * &nam=2026
                 * &format=xlsx
                 */
                Route::get(
                    'export',
                    [
                        ChamCongController::class,
                        'export',
                    ]
                )->name(
                    'api.v1.cham-cong.export'
                );


                /*
                 * Export template để import chấm công
                 *
                 * GET /api/v1/cham-cong/template
                 * ?format=xlsx
                 */
                Route::get(
                    'template',
                    [
                        ChamCongController::class,
                        'exportImportTemplate',
                    ]
                )->name(
                    'api.v1.cham-cong.template'
                );


                /*
                 * Import dữ liệu chấm công
                 *
                 * POST /api/v1/cham-cong/import
                 *
                 * multipart/form-data
                 * file = ...
                 */
                Route::post(
                    'import',
                    [
                        ChamCongController::class,
                        'import',
                    ]
                )->name(
                    'api.v1.cham-cong.import'
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
        ]);

        Route::apiResource(
            'cham-cong',
            ChamCongController::class
        )
            ->only([
                'index',
                'store',
                'update',
                'destroy',
            ])->middleware([
                'web',
                'auth',
            ]);


        /*
         * ==================================================
         * NGHỈ PHÉP
         * ==================================================
         */

        Route::middleware([
            'web',
            'auth',
        ])
            ->prefix('nghi-phep')
            ->group(function (): void {

                Route::get(
                    'nhan-vien',
                    [NghiPhepController::class, 'employeesV2']
                )->middleware(['auth']);

                Route::get(
                    'phe-duyet',
                    [NghiPhepController::class, 'approvalList']
                )->middleware(['auth']);

                Route::get(
                    'phong-ban',
                    [NghiPhepController::class, 'phongBan']
                )->middleware(['auth']);

                Route::get(
                    'chuc-vu',
                    [NghiPhepController::class, 'chucVu']
                )->middleware(['auth']);

                Route::get(
                    'loai-phep',
                    [NghiPhepController::class, 'loaiPhep']
                )->middleware(['auth']);

                Route::patch(
                    '{ma_np}/duyet',
                    [NghiPhepController::class, 'duyet']
                )->name(
                    'api.v1.nghi-phep.duyet'
                )->middleware(['auth']);
            });

        Route::apiResource(
            'nghi-phep',
            NghiPhepController::class
        )->middleware([
            'web',
            'auth',
        ]);


        /*
         * ==================================================
         * LƯƠNG
         * ==================================================
         */

        Route::middleware([
            'web',
            'auth',
        ])
            ->prefix('luong')
            ->group(function (): void {

                Route::get(
                    'phong-ban',
                    [LuongPhongBanController::class, 'index']
                )->name(
                    'api.v1.luong.phong-ban'
                )->middleware(['auth']);

                Route::get(
                    'chuc-vu',
                    [LuongChucVuController::class, 'index']
                )->name(
                    'api.v1.luong.chuc-vu'
                )->middleware(['auth']);

                Route::get(
                    'export',
                    [LuongController::class, 'export']
                )->name(
                    'api.v1.luong.export'
                )->middleware(['auth']);

                Route::get(
                    'he-so-luong',
                    [LuongHeSoLuongController::class, 'index']
                )->name(
                    'api.v1.luong.he-so-luong'
                )->middleware(['auth']);

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
        )->middleware([
            'web',
            'auth',
        ]);
    });

/*
 * ==================================================
 * DASHBOARD - API cho trang tổng quan
 * ==================================================
 */
Route::middleware(['web', 'auth'])
    ->prefix('v1/dashboard')
    ->group(function () {
        Route::get('overview', [DashboardController::class, 'overview'])
            ->name('api.v1.dashboard.overview');

        Route::get('education-stats', [DashboardController::class, 'educationStats'])
            ->name('api.v1.dashboard.education-stats');

        Route::get('department-stats', [DashboardController::class, 'departmentStats'])
            ->name('api.v1.dashboard.department-stats');

        Route::get('expiring-contracts', [DashboardController::class, 'expiringContracts'])
            ->name('api.v1.dashboard.expiring-contracts');

        Route::get('attendance-report', [DashboardController::class, 'attendanceReport'])
            ->name('api.v1.dashboard.attendance-report');

        Route::get('salary-report', [DashboardController::class, 'salaryReport'])
            ->name('api.v1.dashboard.salary-report');
    });
