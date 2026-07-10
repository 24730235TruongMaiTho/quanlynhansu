<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\ {
    DashboardController,
    PhongBanController
};

Route::get('/dashboard', [App\Http\Controllers\Backend\DashboardController::class, 'index']);

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