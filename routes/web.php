<?php
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Frontend Routes - Trang ngoài
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Frontend\HomeController;


/*
|--------------------------------------------------------------------------
| Backend Routes - Trang quản trị
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Backend\ {
    BangDieuKhienController,
    ChamCongController,
    ChucVuController,
    HopDongController,
    LichSuHeSoLuongController,
    LuongController,
    NghiPhepController,
    NhanVienController,
    NhomQuyenController,
    PhongBanController,
    QuyenController,
    TaiKhoanController,
    TruyCapController,
    VaiTroController
};

Route::prefix('admin')->name('backend.')->group(function () {
#                   url                                  tên hàm trong controller        tên file view
    Route::get('/bang-dieu-khien', [BangDieuKhienController::class, 'index'])->name('bangdieukhien.index');

    Route::get('/', [HomeController::class, 'index'])->name('frontend.home');

    // Tìm kiếm phòng ban
    Route::get('/phong-ban', [PhongBanController::class, 'index'])->name('phongban.index');

    // Tạo mới phòng ban
    Route::get('/phong-ban/create', [PhongBanController::class, 'create'])->name('phongban.create');
    Route::post('/phong-ban', [PhongBanController::class, 'store'])->name('phongban.store');

    // Sửa phòng ban
    Route::get('/phong-ban/{id}/sua', [PhongBanController::class, 'edit'])->name('phongban.edit');
    Route::put('/phong-ban/{id}', [PhongBanController::class, 'show'])->name('phongban.show');

    // Xóa phòng ban
    Route::delete('/phong-ban/{id}', [PhongBanController::class, 'destroy'])->name('phongban.destroy');

    // Tìm kiếm nhân viên
    Route::get('/nhan-vien/danh-sach-nhan-vien', [NhanVienController::class, 'index'])->name('nhanvien.index');

    // Tạo mới phòng ban
    Route::get('/nhan-vien/them-nhan-vien', [NhanVienController::class, 'create'])->name('nhanvien.create');
    Route::post('/nhan-vien', [NhanVienController::class, 'store'])->name('nhanvien.store');

    // Sửa phòng ban
    Route::get('/nhan-vien/{id}/sua', [NhanVienController::class, 'edit'])->name('nhanvien.edit');
    Route::put('/nhan-vien/{id}', [NhanVienController::class, 'show'])->name('nhanvien.show');

    // Xóa phòng ban
    Route::delete('/nhan-vien/{id}', [NhanVienController::class, 'destroy'])->name('nhanvien.destroy');

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

});