<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\ {
    DashboardController,
    PhongBanController,
    ChucVuController,
    NhanVienController
};
use App\Http\Controllers\Frontend\HomeController;

Route::get('/dashboard', [App\Http\Controllers\Backend\DashboardController::class, 'index']);

Route::get('/', [HomeController::class, 'index'])->name('frontend.home');

// Tìm kiếm phòng ban
Route::get('/phong-ban', [PhongBanController::class, 'index'])->name('backend.phongban.index');

// Tạo mới phòng ban
Route::get('/phong-ban/create', [PhongBanController::class, 'create'])->name('backend.phongban.create');
Route::post('/phong-ban', [PhongBanController::class, 'store'])->name('backend.phongban.store');

// Sửa phòng ban
Route::get('/phong-ban/{id}/sua', [PhongBanController::class, 'edit'])->name('backend.phongban.edit');
Route::put('/phong-ban/{id}', [PhongBanController::class, 'show'])->name('backend.phongban.show');

// Xóa phòng ban
Route::delete('/phong-ban/{id}', [PhongBanController::class, 'destroy'])->name('backend.phongban.destroy');


// Tìm kiếm nhân viên
Route::get('/nhan-vien', [NhanVienController::class, 'index'])->name('backend.nhanvien.index');

// Tạo mới phòng ban
Route::get('/nhan-vien/create', [NhanVienController::class, 'create'])->name('backend.nhanvien.create');
Route::post('/nhan-vien', [NhanVienController::class, 'store'])->name('backend.nhanvien.store');

// Sửa phòng ban
Route::get('/nhan-vien/{id}/sua', [NhanVienController::class, 'edit'])->name('backend.nhanvien.edit');
Route::put('/nhan-vien/{id}', [NhanVienController::class, 'show'])->name('backend.nhanvien.show');

// Xóa phòng ban
Route::delete('/nhan-vien/{id}', [NhanVienController::class, 'destroy'])->name('backend.nhanvien.destroy');