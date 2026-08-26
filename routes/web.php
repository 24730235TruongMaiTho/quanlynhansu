<?php
use Illuminate\Http\Request;
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

Route::redirect('/', '/admin/nhan-vien')->name('home');

Route::prefix('admin')->group(function (): void {
    Route::get('/nhan-vien/danh-sach-nhan-vien', function (Request $request) {
        return redirect()->route('backend.nhanvien.index', $request->query(), 301);
    });

    Route::get('/nhan-vien/them-nhan-vien', function (Request $request) {
        return redirect()->route('backend.nhanvien.create', $request->query(), 301);
    });
});

Route::prefix('admin')->name('backend.')->group(function (): void {
#                   url                                  tên hàm trong controller        tên file view
    Route::get('/bang-dieu-khien', [BangDieuKhienController::class, 'index'])->name('bangdieukhien.index');

    Route::get('/', [HomeController::class, 'index'])->name('frontend.home');

    Route::get('/phong-ban', [PhongBanController::class, 'index'])
        ->name('phongban.index');

    Route::get('/phong-ban/create', [PhongBanController::class, 'create'])
        ->name('phongban.create');

    Route::post('/phong-ban', [PhongBanController::class, 'store'])
        ->name('phongban.store');

    Route::get('/phong-ban/{ma_pb}/edit', [PhongBanController::class, 'edit'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->name('phongban.edit');

    Route::match(['put', 'patch'], '/phong-ban/{ma_pb}', [PhongBanController::class, 'update'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->name('phongban.update');

    Route::delete('/phong-ban/{ma_pb}', [PhongBanController::class, 'destroy'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->name('phongban.destroy');

    Route::get('/chuc-vu', [ChucVuController::class, 'index'])
        ->name('chucvu.index');

    Route::get('/chuc-vu/create', [ChucVuController::class, 'create'])
        ->name('chucvu.create');

    Route::post('/chuc-vu', [ChucVuController::class, 'store'])
        ->name('chucvu.store');

    Route::get('/chuc-vu/{ma_cv}/edit', [ChucVuController::class, 'edit'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->name('chucvu.edit');

    Route::match(['put', 'patch'], '/chuc-vu/{ma_cv}', [ChucVuController::class, 'update'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->name('chucvu.update');

    Route::delete('/chuc-vu/{ma_cv}', [ChucVuController::class, 'destroy'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->name('chucvu.destroy');

    Route::get('/nhan-vien', [NhanVienController::class, 'index'])
        ->name('nhanvien.index');

    Route::get('/nhan-vien/create', [NhanVienController::class, 'create'])
        ->name('nhanvien.create');

    Route::post('/nhan-vien', [NhanVienController::class, 'store'])
        ->name('nhanvien.store');

    Route::get('/nhan-vien/{ma_nv}/edit', [NhanVienController::class, 'edit'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->name('nhanvien.edit');

    Route::match(['put', 'patch'], '/nhan-vien/{ma_nv}', [NhanVienController::class, 'update'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->name('nhanvien.update');

    Route::delete('/nhan-vien/{ma_nv}', [NhanVienController::class, 'destroy'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->name('nhanvien.destroy');

    Route::get('/nhan-vien/{ma_nv}', [NhanVienController::class, 'show'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->name('nhanvien.show');

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
