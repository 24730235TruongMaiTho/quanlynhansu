<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\ {
    DashboardController,
    PhongBanController
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

// Lương
Route::get('/luong', function () {
    return view('backend.luong.index');
})->name('backend.luong.index');

// Chấm công
Route::get('/cham-cong', function () {
    return view('backend.chamcong.index');
})->name('backend.chamcong.index');

// Nghỉ phép
Route::get('/nghi-phep', function () {
    return view('backend.nghiphep.index');
})->name('backend.nghiphep.index');
