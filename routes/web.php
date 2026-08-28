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
use App\Enums\PhongBanPermission;
use App\Enums\ChucVuPermission;


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

// Tương thích URL đăng nhập cũ (không đổi luồng nghiệp vụ mặc định)
Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');

// Root branches explicitly so guests enter login while authenticated users reach the dashboard.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('backend.bangdieukhien.index')
        : redirect()->route('login');
})->name('home');

Route::post('/dang-xuat', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

if (app()->environment('testing')) {
    Route::get('/_test/employee-authenticated', fn () => response()->json([
        'ma_nv' => auth()->id(),
    ]))->middleware(['auth']);
}

Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/nhan-vien/danh-sach-nhan-vien', function (Request $request) {
        return redirect()->route('backend.nhanvien.index', $request->query(), 301);
    })->middleware([
        EnsureNhanVienModuleEnabled::class,
        'can:'.NhanVienPermission::Xem->value,
    ]);

    Route::get('/nhan-vien/them-nhan-vien', function (Request $request) {
        return redirect()->route('backend.nhanvien.create', $request->query(), 301);
    })->middleware([
        EnsureNhanVienModuleEnabled::class,
        'can:'.NhanVienPermission::Tao->value,
    ]);
});

Route::prefix('admin')->name('backend.')->middleware('auth')->group(function () {
#                   url                                  tên hàm trong controller        tên file view
    Route::get('/bang-dieu-khien', [BangDieuKhienController::class, 'index'])->name('bangdieukhien.index');

    Route::get('/', [HomeController::class, 'index'])->name('frontend.home');

    Route::get('/phong-ban', [PhongBanController::class, 'index'])
        ->middleware('can:'.PhongBanPermission::Xem->value)
        ->name('phongban.index');

    Route::get('/phong-ban/create', [PhongBanController::class, 'create'])
        ->middleware('can:'.PhongBanPermission::Tao->value)
        ->name('phongban.create');

    Route::post('/phong-ban', [PhongBanController::class, 'store'])
        ->middleware('can:'.PhongBanPermission::Tao->value)
        ->name('phongban.store');

    Route::get('/phong-ban/{ma_pb}/edit', [PhongBanController::class, 'edit'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->middleware('can:'.PhongBanPermission::Sua->value)
        ->name('phongban.edit');

    Route::match(['put', 'patch'], '/phong-ban/{ma_pb}', [PhongBanController::class, 'update'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->middleware('can:'.PhongBanPermission::Sua->value)
        ->name('phongban.update');

    Route::delete('/phong-ban/{ma_pb}', [PhongBanController::class, 'destroy'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->middleware('can:'.PhongBanPermission::Xoa->value)
        ->name('phongban.destroy');

    Route::get('/chuc-vu', [ChucVuController::class, 'index'])
        ->middleware('can:'.ChucVuPermission::Xem->value)
        ->name('chucvu.index');

    Route::get('/chuc-vu/create', [ChucVuController::class, 'create'])
        ->middleware('can:'.ChucVuPermission::Tao->value)
        ->name('chucvu.create');

    Route::post('/chuc-vu', [ChucVuController::class, 'store'])
        ->middleware('can:'.ChucVuPermission::Tao->value)
        ->name('chucvu.store');

    Route::get('/chuc-vu/{ma_cv}/edit', [ChucVuController::class, 'edit'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->middleware('can:'.ChucVuPermission::Sua->value)
        ->name('chucvu.edit');

    Route::match(['put', 'patch'], '/chuc-vu/{ma_cv}', [ChucVuController::class, 'update'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->middleware('can:'.ChucVuPermission::Sua->value)
        ->name('chucvu.update');

    Route::delete('/chuc-vu/{ma_cv}', [ChucVuController::class, 'destroy'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->middleware('can:'.ChucVuPermission::Xoa->value)
        ->name('chucvu.destroy');

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

    Route::get('/duyet-nghi-phep', function () {
        return view('backend.nghiphep.duyet-nghi-phep');
    })->name('backend.nghiphep.duyet-nghi-phep');

    Route::get('/tao-nghi-phep', function () {
        return view('backend.nghiphep.create');
    })->name('backend.nghiphep.create');

});
