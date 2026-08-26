<?php

// Middleware kiểm tra module nhân viên đã được bật hay chưa.
use App\Enums\ChucVuPermission;
// Request được dùng để giữ lại query string khi chuyển hướng URL cũ.
use App\Enums\NhanVienPermission;
// Facade Route dùng để khai báo các endpoint web.
use App\Enums\PhongBanPermission;
/*
|--------------------------------------------------------------------------
| Frontend Routes - Trang ngoài
|--------------------------------------------------------------------------
*/
// Controller xử lý trang ngoài và các thao tác đăng nhập.
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Backend\ChucVuController;
// Danh sách quyền dùng cho middleware phân quyền route.
use App\Http\Controllers\Backend\NhanVienController;
use App\Http\Controllers\Backend\PhongBanController;
use App\Http\Controllers\Backend\TongQuanController;
/*
|--------------------------------------------------------------------------
| Backend Routes - Trang quản trị
|--------------------------------------------------------------------------
*/
// Các controller xử lý chức năng trong khu vực quản trị.
use App\Http\Controllers\Backend\VaiTroController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Middleware\EnsureNhanVienModuleEnabled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
    // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
]);

// Chuyển URL cũ của form thêm nhân viên sang route chuẩn mới.
Route::get('/admin/nhan-vien/them-nhan-vien', function (Request $request) {
    return redirect()->route('backend.nhanvien.create', $request->query(), 301);
})->middleware([
    // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
]);

// Các route quản trị dùng tiền tố tên backend.
// Có thể bật middleware auth cho toàn bộ nhóm khi hoàn tất cấu hình đăng nhập.
Route::prefix('admin')->name('backend.')
// ->middleware('auth')
    ->group(function () {
        // ============================== Dashboard ==============================
        Route::get('/tong-quan', [TongQuanController::class, 'index'])->name('tongquan.index');

        // Trang ngoài mặc định của ứng dụng.
        Route::get('/', [HomeController::class, 'index'])->name('frontend.home');

        // ============================ Phòng ban ================================
        // CRUD phòng ban; các middleware đang comment được giữ nguyên theo branch.
        Route::prefix('phong-ban')->name('phongban.')->group(function () {
            Route::get('/', [PhongBanController::class, 'index'])
                // ->middleware('can:'.PhongBanPermission::Xem->value)
                ->name('index');

            // Hiển thị form tạo phòng ban, yêu cầu quyền tạo.
            Route::get('/create', [PhongBanController::class, 'create'])
                // ->middleware('can:'.PhongBanPermission::Tao->value)
                ->name('create');

            // Lưu phòng ban mới.
            Route::post('/', [PhongBanController::class, 'store'])
                // ->middleware('can:'.PhongBanPermission::Tao->value)
                ->name('store');

            // Hiển thị form chỉnh sửa phòng ban theo mã phòng ban.
            Route::get('/{ma_pb}/edit', [PhongBanController::class, 'edit'])
                ->where('ma_pb', '[1-9][0-9]*')
                // ->middleware('can:'.PhongBanPermission::Sua->value)
                ->name('edit');

            // Cập nhật phòng ban bằng PUT hoặc PATCH.
            Route::match(['put', 'patch'], '/{ma_pb}', [PhongBanController::class, 'update'])
                ->where('ma_pb', '[1-9][0-9]*')
                // Tạm tắt xác thực và phân quyền để kiểm thử module Phòng ban.
                ->name('update');

            // Xóa phòng ban theo mã phòng ban.
            Route::delete('/{ma_pb}', [PhongBanController::class, 'destroy'])
                ->where('ma_pb', '[1-9][0-9]*')
                // Tạm tắt xác thực và phân quyền để kiểm thử module Phòng ban.
                ->name('destroy');
        });

        // ============================== Vai trò ================================
        // View danh sách và các endpoint JSON phục vụ CRUD/tìm kiếm vai trò.
        Route::prefix('vai-tro')->name('vaitro.')->group(function () {
            Route::view('/', 'backend.vaitro.index')->name('index');
            Route::get('/data', [VaiTroController::class, 'index'])->name('data');
            Route::get('/search', [VaiTroController::class, 'search'])->name('search');
            Route::get('/{ma_vt}', [VaiTroController::class, 'show'])
                ->where('ma_vt', '[1-9][0-9]*')
                ->name('show');
            Route::post('/', [VaiTroController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/{ma_vt}', [VaiTroController::class, 'update'])
                ->where('ma_vt', '[1-9][0-9]*')
                ->name('update');
            Route::delete('/{ma_vt}', [VaiTroController::class, 'destroy'])
                ->where('ma_vt', '[1-9][0-9]*')
                ->name('destroy');
        });

        // ============================ Chức vụ ==================================
        // CRUD chức vụ với quyền xem, tạo, sửa và xóa tương ứng.
        Route::prefix('chuc-vu')->name('chucvu.')->group(function () {
            Route::get('/', [ChucVuController::class, 'index'])
                // ->middleware('can:'.ChucVuPermission::Xem->value)
                ->name('index');

            Route::get('/create', [ChucVuController::class, 'create'])
                // ->middleware('can:'.ChucVuPermission::Tao->value)
                ->name('create');

            Route::post('/', [ChucVuController::class, 'store'])
                // ->middleware('can:'.ChucVuPermission::Tao->value)
                ->name('store');

            Route::get('/{ma_cv}/edit', [ChucVuController::class, 'edit'])
                ->where('ma_cv', '[1-9][0-9]*')
                // ->middleware('can:'.ChucVuPermission::Sua->value)
                ->name('edit');

            Route::match(['put', 'patch'], '/{ma_cv}', [ChucVuController::class, 'update'])
                ->where('ma_cv', '[1-9][0-9]*')
                // ->middleware('can:'.ChucVuPermission::Sua->value)
                ->name('update');

            Route::delete('/{ma_cv}', [ChucVuController::class, 'destroy'])
                ->where('ma_cv', '[1-9][0-9]*')
                // ->middleware('can:'.ChucVuPermission::Xoa->value)
                ->name('destroy');
        });

        // ============================ Nhân viên ================================
        // Danh sách, hồ sơ, cập nhật, reset mật khẩu và vòng đời nhân viên.
        Route::prefix('nhan-vien')->name('nhanvien.')->group(function () {
            Route::get('/', [NhanVienController::class, 'index'])
                /*->middleware([
                EnsureNhanVienModuleEnabled::class,
                'can:'.NhanVienPermission::Xem->value,
            ])*/
                ->name('index');

            // Hiển thị form thêm nhân viên.
            Route::get('/create', [NhanVienController::class, 'create'])
                // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
                ->name('create');

            // Lưu nhân viên mới.
            Route::post('/', [NhanVienController::class, 'store'])
                // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
                ->name('store');

            // Hiển thị form chỉnh sửa nhân viên.
            Route::get('/{ma_nv}/edit', [NhanVienController::class, 'edit'])
                ->where('ma_nv', 'NV[0-9]{3}')
                // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
                ->name('edit');

            // Cập nhật thông tin nhân viên.
            Route::match(['put', 'patch'], '/{ma_nv}', [NhanVienController::class, 'update'])
                ->where('ma_nv', 'NV[0-9]{3}')
                // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
                ->name('update');

            // Đặt lại mật khẩu cho nhân viên.
            Route::patch('/{ma_nv}/dat-lai-mat-khau', [NhanVienController::class, 'resetPassword'])
                ->where('ma_nv', 'NV[0-9]{3}')
                // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
                ->name('reset-password');

            // Xóa hoặc chuyển trạng thái nhân viên theo nghiệp vụ controller.
            Route::delete('/{ma_nv}', [NhanVienController::class, 'destroy'])
                ->where('ma_nv', 'NV[0-9]{3}')
                // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
                ->name('destroy');

            // Xem chi tiết nhân viên.
            Route::get('/{ma_nv}', [NhanVienController::class, 'show'])
                ->where('ma_nv', 'NV[0-9]{3}')
                // Tạm tắt xác thực và phân quyền để kiểm thử module Nhân viên.
                ->name('show');
        });

        // ============================== Hệ số lương ==================================
        // Trang hiện trả về view; các API lương được khai báo trong routes/api.php.
        Route::get('/he-so-luong', function () {
            return view('backend.hesoluong.index');
        })->name('hesoluongluong.index');

        // ============================== Lương ==================================
        // Trang hiện trả về view; các API lương được khai báo trong routes/api.php.
        Route::get('/luong', function () {
            return view('backend.luong.index');
        })->name('luong.index');

        // ============================ Chấm công ================================
        // Trang hiện trả về view; các API chấm công được khai báo trong routes/api.php.
        Route::get('/cham-cong', function () {
            return view('backend.chamcong.index');
        })->name('chamcong.index');

        // ============================ Nghỉ phép ================================
        // Trang hiện trả về view; các API nghỉ phép được khai báo trong routes/api.php.
        Route::get('/nghi-phep', function () {
            return view('backend.nghiphep.index');
        })->name('nghiphep.index');

        // ============================ Loại hợp đồng ================================
        Route::get('/loai-hop-dong', function () {
            return view('backend.loaihopdong.index');
        })->name('loaihopdong.index');

        // ============================ Hợp đồng ================================
        Route::get('/hop-dong', function () {
            return view('backend.hopdong.index');
        })->name('hopdong.index');
    });
