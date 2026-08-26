<?php
// Request được dùng để giữ lại query string khi chuyển hướng URL cũ.
use Illuminate\Http\Request;
// Facade Route dùng để khai báo các endpoint web.
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Frontend Routes - Trang ngoài
|--------------------------------------------------------------------------
*/
// Controller xử lý trang ngoài và các thao tác đăng nhập.
use App\Http\Controllers\Auth\AuthenticatedSessionController;
// Danh sách quyền dùng cho middleware phân quyền route.
use App\Enums\NhanVienPermission;
use App\Enums\PhongBanPermission;
use App\Enums\ChucVuPermission;
use App\Enums\HopDongPermission;
use App\Enums\PhanQuyenPermission;


/*
|--------------------------------------------------------------------------
| Backend Routes - Trang quản trị
|--------------------------------------------------------------------------
*/
// Các controller xử lý chức năng trong khu vực quản trị.
use App\Http\Controllers\Backend\ {
    TongQuanController,
    ChamCongController,
    ChucVuController,
    HopDongController,
    LichSuHeSoLuongController,
    LuongController,
    NghiPhepController,
    NhanVienController,
    NhomQuyenController,
    PhongBanController,
    PhanQuyenController,
    QuyenController,
    TaiKhoanController,
    TruyCapController,
    VaiTroController
};

// Hiển thị form đăng nhập tại URL tiếng Việt.
Route::get('/dang-nhap', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

// Tiếp nhận thông tin đăng nhập và tạo session người dùng.
Route::post('/dang-nhap', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

// Giữ URL cũ để tương thích với các liên kết đã tồn tại.
Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');

// Khách được đưa tới trang đăng nhập; người đã đăng nhập được đưa tới dashboard.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('backend.tongquan.index')
        : redirect()->route('login');
})->name('home');

// Đăng xuất người dùng hiện tại; chỉ session đã xác thực mới được gọi route này.
Route::post('/dang-xuat', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Chỉ tạo endpoint hỗ trợ kiểm thử khi ứng dụng đang ở môi trường testing.
if (app()->environment('testing')) {
    Route::get('/_test/employee-authenticated', fn () => response()->json([
        'ma_nv' => auth()->id(),
    ]))->middleware(['auth']);
}

// Chuyển URL cũ của danh sách nhân viên sang route chuẩn mới.
Route::get('/admin/nhan-vien/danh-sach-nhan-vien', function (Request $request) {
    return redirect()->route('backend.nhanvien.index', $request->query(), 301);
})->middleware([
    'auth',
    'can:'.NhanVienPermission::Xem->value,
]);

// Chuyển URL cũ của form thêm nhân viên sang route chuẩn mới.
Route::get('/admin/nhan-vien/them-nhan-vien', function (Request $request) {
    return redirect()->route('backend.nhanvien.create', $request->query(), 301);
})->middleware([
    'auth',
    'can:'.NhanVienPermission::Tao->value,
]);

// Các route quản trị dùng tiền tố tên backend; từng route nghiệp vụ khai báo
// middleware đăng nhập và quyền tương ứng ngay tại nơi định nghĩa.
Route::prefix('')->name('backend.')
->group(function () {
    // Dashboard tổng quan.
    Route::get('/tong-quan', [TongQuanController::class, 'index'])->name('tongquan.index');

    // Danh sách phòng ban, yêu cầu quyền xem.
    Route::get('/phong-ban', [PhongBanController::class, 'index'])
        ->middleware(['auth', 'can:'.PhongBanPermission::Xem->value])
        ->name('phongban.index');

    // Hiển thị form tạo phòng ban, yêu cầu quyền tạo.
    Route::get('/phong-ban/create', [PhongBanController::class, 'create'])
        ->middleware(['auth', 'can:'.PhongBanPermission::Tao->value])
        ->name('phongban.create');

    // Lưu phòng ban mới.
    Route::post('/phong-ban', [PhongBanController::class, 'store'])
        ->middleware(['auth', 'can:'.PhongBanPermission::Tao->value])
        ->name('phongban.store');

    // Hiển thị form chỉnh sửa phòng ban theo mã phòng ban.
    Route::get('/phong-ban/{ma_pb}/edit', [PhongBanController::class, 'edit'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.PhongBanPermission::Sua->value])
        ->name('phongban.edit');

    // Cập nhật phòng ban bằng PUT hoặc PATCH.
    Route::match(['put', 'patch'], '/phong-ban/{ma_pb}', [PhongBanController::class, 'update'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.PhongBanPermission::Sua->value])
        ->name('phongban.update');

    // Xóa phòng ban theo mã phòng ban.
    Route::delete('/phong-ban/{ma_pb}', [PhongBanController::class, 'destroy'])
        ->where('ma_pb', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.PhongBanPermission::Xoa->value])
        ->name('phongban.destroy');

    // Trang và API quản lý vai trò.
    Route::view('/vai-tro', 'backend.vaitro.index')->middleware(['auth', 'can:'.PhanQuyenPermission::Xem->value])->name('vaitro.index');
    Route::get('/vai-tro/data', [VaiTroController::class, 'index'])->middleware(['auth', 'can:'.PhanQuyenPermission::Xem->value])->name('vaitro.data');
    Route::get('/vai-tro/search', [VaiTroController::class, 'search'])->middleware(['auth', 'can:'.PhanQuyenPermission::Xem->value])->name('vaitro.search');
    Route::get('/vai-tro/{ma_vt}', [VaiTroController::class, 'show'])
        ->where('ma_vt', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.PhanQuyenPermission::Xem->value])->name('vaitro.show');
    Route::post('/vai-tro', [VaiTroController::class, 'store'])->middleware(['auth', 'can:'.PhanQuyenPermission::QuanLy->value])->name('vaitro.store');
    Route::match(['put', 'patch'], '/vai-tro/{ma_vt}', [VaiTroController::class, 'update'])
        ->where('ma_vt', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.PhanQuyenPermission::QuanLy->value])->name('vaitro.update');
    Route::delete('/vai-tro/{ma_vt}', [VaiTroController::class, 'destroy'])
        ->where('ma_vt', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.PhanQuyenPermission::QuanLy->value])->name('vaitro.destroy');

    Route::get('/vai-tro/{ma_vt}/phan-quyen', [PhanQuyenController::class, 'editRole'])
        ->where('ma_vt', '[1-9][0-9]*')->middleware(['auth', 'can:'.PhanQuyenPermission::Xem->value])->name('vaitro.permissions.edit');
    Route::put('/vai-tro/{ma_vt}/phan-quyen', [PhanQuyenController::class, 'syncRole'])
        ->where('ma_vt', '[1-9][0-9]*')->middleware(['auth', 'can:'.PhanQuyenPermission::QuanLy->value])->name('vaitro.permissions.update');
    Route::get('/tai-khoan', [PhanQuyenController::class, 'accounts'])
        ->middleware(['auth', 'can:'.PhanQuyenPermission::Xem->value])->name('taikhoan.index');
    Route::patch('/tai-khoan/{ma_nv}/vai-tro', [PhanQuyenController::class, 'assignRole'])
        ->where('ma_nv', 'NV[0-9]{3}')->middleware(['auth', 'can:'.PhanQuyenPermission::QuanLy->value])->name('taikhoan.assign-role');

    Route::get('/hop-dong', [HopDongController::class, 'index'])->middleware(['auth', 'can:'.HopDongPermission::Xem->value])->name('hopdong.index');
    Route::get('/hop-dong/create', [HopDongController::class, 'create'])->middleware(['auth', 'can:'.HopDongPermission::Tao->value])->name('hopdong.create');
    Route::post('/hop-dong', [HopDongController::class, 'store'])->middleware(['auth', 'can:'.HopDongPermission::Tao->value])->name('hopdong.store');
    Route::get('/hop-dong/{ma_hd}/edit', [HopDongController::class, 'edit'])->where('ma_hd', '[1-9][0-9]*')->middleware(['auth', 'can:'.HopDongPermission::Sua->value])->name('hopdong.edit');
    Route::match(['put', 'patch'], '/hop-dong/{ma_hd}', [HopDongController::class, 'update'])->where('ma_hd', '[1-9][0-9]*')->middleware(['auth', 'can:'.HopDongPermission::Sua->value])->name('hopdong.update');
    Route::delete('/hop-dong/{ma_hd}', [HopDongController::class, 'destroy'])->where('ma_hd', '[1-9][0-9]*')->middleware(['auth', 'can:'.HopDongPermission::Xoa->value])->name('hopdong.destroy');

    // Danh sách nhân viên.
    Route::get('/chuc-vu', [ChucVuController::class, 'index'])
        ->middleware(['auth', 'can:'.ChucVuPermission::Xem->value])
        ->name('chucvu.index');

    Route::get('/chuc-vu/create', [ChucVuController::class, 'create'])
        ->middleware(['auth', 'can:'.ChucVuPermission::Tao->value])
        ->name('chucvu.create');

    Route::post('/chuc-vu', [ChucVuController::class, 'store'])
        ->middleware(['auth', 'can:'.ChucVuPermission::Tao->value])
        ->name('chucvu.store');

    Route::get('/chuc-vu/{ma_cv}/edit', [ChucVuController::class, 'edit'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.ChucVuPermission::Sua->value])
        ->name('chucvu.edit');

    Route::match(['put', 'patch'], '/chuc-vu/{ma_cv}', [ChucVuController::class, 'update'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.ChucVuPermission::Sua->value])
        ->name('chucvu.update');

    Route::delete('/chuc-vu/{ma_cv}', [ChucVuController::class, 'destroy'])
        ->where('ma_cv', '[1-9][0-9]*')
        ->middleware(['auth', 'can:'.ChucVuPermission::Xoa->value])
        ->name('chucvu.destroy');

    Route::get('/nhan-vien', [NhanVienController::class, 'index'])
        ->middleware(['auth', 'can:'.NhanVienPermission::Xem->value])
        ->name('nhanvien.index');

    // Hiển thị form thêm nhân viên.
    Route::get('/nhan-vien/create', [NhanVienController::class, 'create'])
        ->middleware(['auth', 'can:'.NhanVienPermission::Tao->value])
        ->name('nhanvien.create');

    // Lưu nhân viên mới.
    Route::post('/nhan-vien', [NhanVienController::class, 'store'])
        ->middleware(['auth', 'can:'.NhanVienPermission::Tao->value])
        ->name('nhanvien.store');

    // Hiển thị form chỉnh sửa nhân viên.
    Route::get('/nhan-vien/{ma_nv}/edit', [NhanVienController::class, 'edit'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware(['auth', 'can:'.NhanVienPermission::Sua->value])
        ->name('nhanvien.edit');

    // Cập nhật thông tin nhân viên.
    Route::match(['put', 'patch'], '/nhan-vien/{ma_nv}', [NhanVienController::class, 'update'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware(['auth', 'can:'.NhanVienPermission::Sua->value])
        ->name('nhanvien.update');

    // Xóa hoặc chuyển trạng thái nhân viên theo nghiệp vụ controller.
    Route::delete('/nhan-vien/{ma_nv}', [NhanVienController::class, 'destroy'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware(['auth', 'can:'.NhanVienPermission::Xoa->value])
        ->name('nhanvien.destroy');

    // Xem chi tiết nhân viên.
    Route::get('/nhan-vien/{ma_nv}', [NhanVienController::class, 'show'])
        ->where('ma_nv', 'NV[0-9]{3}')
        ->middleware(['auth', 'can:'.NhanVienPermission::Xem->value])
        ->name('nhanvien.show');

    // Trang quản lý lương hiện trả về view trực tiếp.
    Route::get('/luong', function () {
        return view('backend.luong.index');
    })->name('luong.index');

    // Trang quản lý chấm công hiện trả về view trực tiếp.
    Route::get('/cham-cong', function () {
        return view('backend.chamcong.index');
    })->name('chamcong.index');

    // Trang quản lý nghỉ phép hiện trả về view trực tiếp.
    Route::get('/nghi-phep', function () {
        return view('backend.nghiphep.index');
    })->name('nghiphep.index');

});
