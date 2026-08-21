<?php
use App\Http\Middleware\EnsureNhanVienModuleEnabled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Frontend Routes - Trang ngoài
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Enums\NhanVienPermission;


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

Route::get('/dang-nhap', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/dang-nhap', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::post('/dang-xuat', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

if (app()->environment('testing')) {
    Route::get('/_test/employee-authenticated', fn () => response()->json([
        'ma_nv' => auth()->id(),
    ]))->middleware(['auth']);
}

Route::get('/admin/nhan-vien/danh-sach-nhan-vien', function (Request $request) {
    return redirect()->route('backend.nhanvien.index', $request->query(), 301);
})->middleware([
    'auth',
    EnsureNhanVienModuleEnabled::class,
    'can:'.NhanVienPermission::Xem->value,
]);

Route::get('/admin/nhan-vien/them-nhan-vien', function (Request $request) {
    return redirect()->route('backend.nhanvien.create', $request->query(), 301);
})->middleware([
    'auth',
    EnsureNhanVienModuleEnabled::class,
    'can:'.NhanVienPermission::Tao->value,
]);

Route::prefix('admin')->name('backend.')->middleware('auth')->group(function () {
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

    Route::get('/nhan-vien', [NhanVienController::class, 'index'])
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::Xem->value,
        ])
        ->name('nhanvien.index');

    Route::get('/nhan-vien/create', [NhanVienController::class, 'create'])
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::Tao->value,
        ])
        ->name('nhanvien.create');

    Route::post('/nhan-vien', [NhanVienController::class, 'store'])
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::Tao->value,
        ])
        ->name('nhanvien.store');

    Route::get('/nhan-vien/{ma_nv}/edit', [NhanVienController::class, 'edit'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::Sua->value,
        ])
        ->name('nhanvien.edit');

    Route::match(['put', 'patch'], '/nhan-vien/{ma_nv}', [NhanVienController::class, 'update'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::Sua->value,
        ])
        ->name('nhanvien.update');

    Route::patch('/nhan-vien/{ma_nv}/dat-lai-mat-khau', [NhanVienController::class, 'resetPassword'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::DatLaiMatKhau->value,
        ])
        ->name('nhanvien.reset-password');

    Route::delete('/nhan-vien/{ma_nv}', [NhanVienController::class, 'destroy'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::Xoa->value,
        ])
        ->name('nhanvien.destroy');

    Route::get('/nhan-vien/{ma_nv}', [NhanVienController::class, 'show'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware([
            EnsureNhanVienModuleEnabled::class,
            'can:'.NhanVienPermission::Xem->value,
        ])
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
