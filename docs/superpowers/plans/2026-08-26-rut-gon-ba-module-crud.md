# Kế hoạch triển khai rút gọn ứng dụng về ba module CRUD

> **Dành cho agent triển khai:** BẮT BUỘC dùng `superpowers:executing-plans` để thực hiện kế hoạch theo từng task. Đánh dấu tiến độ bằng checkbox và dừng khi phát hiện thay đổi của đồng nghiệp chồng lên phạm vi.

**Goal:** Tháo toàn bộ đăng nhập, RBAC, giới hạn trưởng phòng và đặt lại mật khẩu do chúng ta xây dựng, đồng thời giữ nguyên CRUD đầy đủ của Nhân viên, Phòng ban và Chức vụ.

**Architecture:** Thực hiện thay đổi tiến về phía trước, không revert commit. Route và giao diện ba module trở thành công khai; tầng CRUD, validation, transaction, avatar và quy tắc xóa an toàn được giữ. File dùng chung chỉ được sửa theo hunk tối thiểu và code module đồng nghiệp luôn được ưu tiên.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, JavaScript/Vite 7, Query Builder, MariaDB 10.4.

**Spec:** `docs/superpowers/specs/2026-08-26-rut-gon-ba-module-crud-design.md`

## Ràng buộc toàn cục

- Không sửa `database/tao_bang.sql`, `database/du_lieu_mau.sql`, `database/sql/du_lieu_mau.sql` hoặc database live.
- Không tạo `database/du_lieu_mauV2.sql`.
- Không sửa nghiệp vụ Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò hoặc Phân quyền ngoài đúng hunk auth/RBAC do chúng ta từng thêm.
- Không fetch, merge, rebase, commit hoặc push.
- Bảo toàn `AIAssistantInput-a1d28494-8caf-4d5a-8217-4d71fad94b75.chatInput`.
- Comment/docblock mới hoặc được sửa phải viết đầy đủ bằng tiếng Việt.
- Không trả password/hash, raw exception, SQL hoặc stack trace ra UI.
- Mỗi task phải chạy test tập trung và `git diff --check` trước khi chuyển task.

---

### Task 1: Khóa hợp đồng route công khai và loại bỏ điểm vào đăng nhập

**Files:**
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Modify: `bootstrap/app.php`
- Modify: `.env.example`
- Modify: `config/nhanvien.php`
- Delete: `app/Http/Middleware/EnsureNhanVienModuleEnabled.php`
- Modify: `tests/Feature/ExampleTest.php`
- Create: `tests/Feature/PublicCrudRouteTest.php`

**Interfaces:**
- Consumes: các route name `backend.nhanvien.*`, `backend.phongban.*`, `backend.chucvu.*` hiện có.
- Produces: `/` redirect tới `backend.nhanvien.index`; ba module không có middleware `auth`/`can:*`; route login/logout/reset-password không tồn tại.

- [x] **Step 1: Viết test thất bại cho hợp đồng route mới**

Tạo `PublicCrudRouteTest` kiểm tra tối thiểu:

```php
public function test_root_redirects_to_employee_index_without_login(): void
{
    $this->get('/')->assertRedirect(route('backend.nhanvien.index'));
}

public function test_login_logout_and_reset_password_routes_are_absent(): void
{
    $this->get('/dang-nhap')->assertNotFound();
    $this->post('/dang-xuat')->assertNotFound();
    $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')->assertNotFound();
}
```

Thêm assertion lấy route collection và xác nhận action của ba module không chứa middleware `auth` hoặc chuỗi bắt đầu bằng `can:`.

- [x] **Step 2: Chạy test để chứng minh trạng thái cũ thất bại**

Run: `php artisan test tests/Feature/PublicCrudRouteTest.php tests/Feature/ExampleTest.php`

Expected: thất bại vì `/` còn chuyển tới login và route còn middleware auth/RBAC.

- [x] **Step 3: Sửa route tối thiểu**

Trong `routes/web.php`:

```php
Route::redirect('/', '/admin/nhan-vien')->name('home');

Route::prefix('admin')->name('backend.')->group(function (): void {
    // Giữ nguyên route và tên route nghiệp vụ hiện có.
});
```

Xóa import/controller/enum permission, route login/logout/test-auth, middleware `auth`, `can:*`, `EnsureNhanVienModuleEnabled` và route reset-password. Trong `bootstrap/app.php`, xóa `redirectGuestsTo()` và `redirectUsersTo()` đang tham chiếu route login/dashboard; giữ `dontFlash(['mat_khau'])`. Xóa `NHAN_VIEN_MODULE_ENABLED` khỏi `.env.example`, xóa khóa `enabled` khỏi `config/nhanvien.php`, nhưng giữ `avatar_prefix` cho CRUD ảnh đại diện.

Trong `routes/api.php`, chỉ gỡ `web`, `auth` và `can:*` do chúng ta thêm vào lookup Nhân viên/Phòng ban và API Chấm công liên quan. Không đổi URL, name, controller hoặc danh sách method của module đồng nghiệp.

- [x] **Step 4: Chạy lại test route**

Run: `php artisan test tests/Feature/PublicCrudRouteTest.php tests/Feature/ExampleTest.php`

Expected: PASS; login/logout/reset-password trả 404 và route CRUD không có auth/can.

- [x] **Step 5: Kiểm tra route inventory và diff**

Run: `php artisan route:list --except-vendor`

Run: `git diff --check`

Expected: route ba module còn đủ index/create/store/edit/update/destroy; Nhân viên còn show; không commit.

---

### Task 2: Tháo hạ tầng đăng nhập và RBAC dùng chung

**Files:**
- Delete: `app/Auth/NhanVienUserProvider.php`
- Delete: `app/Authorization/PermissionRegistry.php`
- Delete: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Delete: `app/Http/Requests/Auth/LoginRequest.php`
- Delete: `resources/views/auth/login.blade.php`
- Delete: `app/Contracts/PermissionDefinitionContract.php`
- Delete: `app/Contracts/PermissionRegistryContract.php`
- Delete: `app/Contracts/PermissionRepositoryContract.php`
- Delete: `app/Repositories/PermissionRepository.php`
- Delete: `app/Services/PermissionService.php`
- Delete: `app/Enums/PermissionAction.php`
- Delete: `app/Enums/NhanVienPermission.php`
- Delete: `app/Enums/PhongBanPermission.php`
- Delete: `app/Enums/ChucVuPermission.php`
- Delete: `config/permissions.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Models/NhanVien.php`
- Modify: `config/auth.php`
- Modify: `resources/views/backend/layouts/sidebar.blade.php`
- Modify: `resources/views/backend/layouts/topbar.blade.php`
- Create: `tests/Unit/Architecture/PublicCrudBoundaryTest.php`
- Delete: `tests/Feature/Auth/EmployeeAuthenticationTest.php`
- Delete: `tests/Feature/Authorization/PermissionIntegrationTest.php`
- Delete: `tests/Unit/Auth/NhanVienUserProviderTest.php`
- Delete: `tests/Unit/Services/PermissionServiceTest.php`
- Modify: `tests/Unit/Models/NhanVienTest.php`

**Interfaces:**
- Consumes: repository/service bindings của ba module.
- Produces: provider chỉ đăng ký CRUD; layout không gọi `auth()`/Gate/Permission Service; source runtime không tham chiếu lớp permission đã xóa.

- [x] **Step 1: Viết source-boundary test thất bại**

Test quét `app`, `routes`, `resources/views` và assert không còn các chuỗi runtime:

```php
$forbidden = [
    'AuthenticatedSessionController',
    'NhanVienUserProvider',
    'PermissionService',
    'PermissionRegistry',
    "middleware('auth')",
    "'can:",
    '@can',
    'Gate::',
];
```

Không quét `docs`, `database/sql` hoặc test historical ngoài runtime boundary.

- [x] **Step 2: Chạy source-boundary test để thấy thất bại**

Run: `php artisan test tests/Unit/Architecture/PublicCrudBoundaryTest.php`

Expected: FAIL và liệt kê các tham chiếu auth/RBAC hiện tại.

- [x] **Step 3: Rút gọn AppServiceProvider và auth config**

`AppServiceProvider` chỉ còn bindings `NhanVien`, `PhongBan`, `ChucVu`; `boot()` không đăng ký custom auth provider hoặc Gate.

Trong `config/auth.php`, bỏ import `NhanVien` và custom driver. Dùng provider framework trung tính không tham chiếu class dự án đã xóa:

```php
'providers' => [
    'users' => [
        'driver' => 'database',
        'table' => 'users',
    ],
],
```

Không tạo bảng `users` và không thêm login thay thế.

- [x] **Step 4: Xóa hạ tầng và làm sạch layout**

Xóa các file liệt kê ở trên. Chuyển `NhanVien` về Eloquent `Model` thông thường, bỏ `Authenticatable`, `fromAuthRow()` và các method remember-token; vẫn giữ `$hidden = ['mat_khau']`. Sidebar render trực tiếp liên kết Nhân viên/Phòng ban/Chức vụ, không đọc actor. Topbar bỏ toàn bộ dropdown đăng nhập/đăng xuất; giữ phần tìm kiếm/thông báo hiện có mà không thay đổi nghiệp vụ.

- [x] **Step 5: Chạy test boundary, view compile và diff**

Run: `php artisan test tests/Unit/Architecture/PublicCrudBoundaryTest.php`

Run: `php artisan view:cache; php artisan view:clear`

Run: `git diff --check`

Expected: PASS; Blade compile được; không còn runtime reference tới class đã xóa.

---

### Task 3: Giữ CRUD Nhân viên và tháo scope, target guard, reset mật khẩu

**Files:**
- Modify: `app/Contracts/NhanVienRepositoryContract.php`
- Modify: `app/Contracts/NhanVienServiceContract.php`
- Modify: `app/Http/Controllers/Backend/NhanVienController.php`
- Modify: `app/Http/Requests/UpdateNhanVienRequest.php`
- Modify: `app/Repositories/NhanVienRepository.php`
- Modify: `app/Services/NhanVienService.php`
- Modify: `app/Support/NhanVienProcedureExceptionMapper.php`
- Delete: `app/Support/NhanVienDepartmentScope.php`
- Delete: `app/Support/NhanVienTargetGuard.php`
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/nhanvien/show.blade.php`
- Modify: `resources/views/backend/nhanvien/create.blade.php`
- Modify: `resources/views/backend/nhanvien/edit.blade.php`
- Modify: `resources/views/backend/nhanvien/partials/action-dialogs.blade.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienIndexTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienShowTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienCreatePageTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienStoreTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienValidationTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienLifecycleTest.php`
- Modify: `tests/Unit/Services/NhanVienServiceLifecycleTest.php`
- Modify: `tests/Unit/Support/NhanVienProcedureExceptionMapperTest.php`
- Modify: `tests/Support/MariaDbEmployeeLifecycleWorker.php`
- Delete: `tests/Feature/Backend/NhanVien/NhanVienAuthorizationTest.php`
- Delete: `tests/Feature/Backend/NhanVien/NhanVienDepartmentScopeTest.php`
- Delete: `tests/Unit/Support/NhanVienTargetGuardTest.php`
- Delete: `tests/Unit/Repositories/NhanVienRepositoryScopeTest.php`

**Interfaces:**
- Produces: `NhanVienController` CRUD không cần actor; service/repository không có `resetPassword*`; create vẫn ghi bcrypt nội bộ; removeOrTerminate giữ nguyên contract.

- [x] **Step 1: Chuyển test CRUD sang guest và thêm regression đa vai trò/phòng ban**

Thay helper `actingAsEmployeeWithPermissions()` bằng request trực tiếp. Thêm test danh sách chứa đồng thời nhân viên `ma_vt = 1` và `ma_vt = 5`, thuộc hai `ma_pb` khác nhau. Thêm test edit/update/destroy không trả 403 chỉ vì `ma_vt`.

- [x] **Step 2: Chạy nhóm test Nhân viên để xác nhận thất bại**

Run: `php artisan test tests/Feature/Backend/NhanVien tests/Unit/Services/NhanVienServiceLifecycleTest.php`

Expected: FAIL vì controller/request/view còn scope, Gate, target guard và reset password.

- [x] **Step 3: Rút gọn controller và request**

Controller dùng trực tiếp:

```php
$filters = $request->filters();
$employees = $this->employees->paginate($filters);
$lookups = $this->employees->lookups();
```

`show`, `edit`, `destroy` chỉ gọi `findOrFail()` và nghiệp vụ CRUD. `UpdateNhanVienRequest::authorize()` xác minh target tồn tại rồi trả `true`; bỏ department actor state và validation đổi phòng theo trưởng phòng. Giữ validation trạng thái nghỉ việc hiện có.

- [x] **Step 4: Xóa reset password/scope/target guard ở contract và data layer**

Xóa `resetPassword()` khỏi service contract/service. Xóa chính xác bốn method chỉ phục vụ auth/bootstrap khỏi repository contract/repository: `resetPasswordHash()`, `rehashAuthenticatedPassword()`, `findAccountByIdentifier()` và `assignRoleForBootstrap()`. Giữ toàn bộ method create/update/find/lookups/avatar/removeOrTerminate và paginate dùng cho CRUD.

Trong `removeOrTerminate()`, xóa kiểm tra `ma_vt` và lỗi `NV_PRIVILEGED_TARGET`; vẫn khóa dòng, kiểm tra dependency và chọn xóa cứng/chuyển nghỉ việc. Loại `NV_PRIVILEGED_TARGET` và `NV_AUTH_HASH_STALE` khỏi exception mapper, unit test mapper và allowlist của lifecycle worker.

Service `create()` tiếp tục dùng `Hasher::make()` để cung cấp hash cho cột NOT NULL, nhưng bỏ flash `password_convention` và mọi mô tả tài khoản.

- [x] **Step 5: Làm sạch Blade Nhân viên**

Render trực tiếp nút thêm/sửa/xóa. Xóa nút/dialog reset password, `$can*`, `@can` và kiểm tra `NhanVienRole::Employee`. Giữ dialog xác nhận xóa/chuyển nghỉ việc, form avatar và address.

- [x] **Step 6: Chạy test Nhân viên và frontend liên quan**

Run: `php artisan test tests/Feature/Backend/NhanVien tests/Unit/Services/NhanVienServiceCreateTest.php tests/Unit/Services/NhanVienServiceLifecycleTest.php tests/Unit/Services/NhanVienServiceAvatarTest.php tests/Unit/Repositories/NhanVienRepositoryTest.php`

Run: `npm run test:frontend`

Expected: CRUD/validation/avatar/lifecycle PASS; không còn test reset/auth/scope.

- [x] **Step 7: Kiểm tra source và diff**

Run: `rg -n "resetPassword|DatLaiMatKhau|NhanVienTargetGuard|NhanVienDepartmentScope|NV_RESET_PASSWORD" app routes resources tests`

Expected: không còn runtime/test active reference; chỉ tài liệu lịch sử ngoài phạm vi có thể còn.

Run: `git diff --check`

---

### Task 4: Giữ CRUD Phòng ban/Chức vụ và tháo Gate khỏi UI/test

**Files:**
- Modify: `resources/views/backend/phongban/index.blade.php`
- Modify: `resources/views/backend/chucvu/index.blade.php`
- Modify: `tests/Feature/Backend/PhongBan/PhongBanFeatureTest.php`
- Modify: `tests/Feature/Backend/ChucVu/ChucVuFeatureTest.php`
- Delete: `tests/Support/InteractsWithPhongBanModule.php`
- Delete: `tests/Support/InteractsWithChucVuModule.php`
- Delete: `tests/Support/InteractsWithEmployeeModule.php`

**Interfaces:**
- Produces: action CRUD hai module luôn hiển thị và endpoint chạy không cần actor; repository/service contract không đổi.

- [x] **Step 1: Sửa test feature thành truy cập công khai**

Loại `actingAs...` và expectation Permission Service. Giữ nguyên assertion JSON/HTML, validation, duplicate, missing và in-use. Thêm assertion guest thấy nút thêm/sửa/xóa.

- [x] **Step 2: Chạy test để xác nhận Blade Gate còn gây thất bại**

Run: `php artisan test tests/Feature/Backend/PhongBan/PhongBanFeatureTest.php tests/Feature/Backend/ChucVu/ChucVuFeatureTest.php`

Expected: FAIL trước khi bỏ điều kiện Gate.

- [x] **Step 3: Bỏ điều kiện Gate khỏi Blade**

Xóa `$canCreate`, `$canEdit`, `$canDelete`; render trực tiếp action. Không thay đổi form, route name, thông báo hoặc JavaScript.

- [x] **Step 4: Chạy toàn bộ test hai module**

Run: `php artisan test tests/Feature/Backend/PhongBan tests/Feature/Backend/ChucVu tests/Unit/Repositories/PhongBanRepositoryTest.php tests/Unit/Repositories/ChucVuRepositoryTest.php tests/Unit/Support/PhongBanExceptionMapperTest.php tests/Unit/Support/ChucVuExceptionMapperTest.php`

Expected: PASS.

- [x] **Step 5: Chạy frontend test và diff**

Run: `npm run test:frontend`

Run: `git diff --check`

Expected: test Phòng ban/Chức vụ PASS; không commit.

---

### Task 5: Hoàn tác đúng phần scope từng cài vào module đồng nghiệp

**Files:**
- Modify: `app/Http/Controllers/Backend/ChamCongController.php`
- Modify: `app/Http/Controllers/Backend/NghiPhepController.php`
- Modify: `tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php`
- Modify: `tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php`

**Interfaces:**
- Produces: lookup nhân viên/phòng ban của module đồng nghiệp trở về gọi service/repository không qua `NhanVienDepartmentScope`; toàn bộ nghiệp vụ khác giữ nguyên.

- [x] **Step 1: Viết lại test compatibility theo contract không actor**

Test phải gọi endpoint không đăng nhập và xác nhận lookup không bị lọc theo `ma_pb`. Không đổi shape response hiện có.

- [x] **Step 2: Chạy test compatibility để thấy phụ thuộc scope hiện tại**

Run: `php artisan test tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php`

Expected: FAIL vì controller còn inject/use `NhanVienDepartmentScope` hoặc route còn auth/can.

- [x] **Step 3: Gỡ đúng dependency scope khỏi hai controller**

Xóa constructor parameter/import `NhanVienDepartmentScope` và chỉ thay biểu thức constrain bằng dữ liệu lookup/paginate sẵn có. Không đổi validation, API resource, tính toán hoặc response field của hai module.

- [x] **Step 4: Chạy lại compatibility test và kiểm tra diff hẹp**

Run: `php artisan test tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php`

Run: `git diff -- app/Http/Controllers/Backend/ChamCongController.php app/Http/Controllers/Backend/NghiPhepController.php`

Expected: PASS; diff chỉ chứa việc bỏ scope do chúng ta từng thêm.

---

### Task 6: Xóa công cụ/test auth-RBAC còn sót và cập nhật tài liệu tiếng Việt

**Files:**
- Delete: `app/Console/Commands/BootstrapNhanVienDemo.php`
- Delete: `tests/Integration/MariaDb/BootstrapNhanVienDemoTest.php`
- Delete: `tests/Integration/MariaDb/EmployeeAuthProcedureTest.php`
- Delete: `tests/Integration/MariaDb/EmployeeRbacProcedureTest.php`
- Delete: `tests/Integration/MariaDb/EmployeeAcceptanceEnvironmentTest.php`
- Delete: `tests/Unit/Support/EmployeeAcceptanceEnvironmentSafetyTest.php`
- Delete: `tests/Support/EmployeeAcceptanceEnvironment.php`
- Delete: `tests/Support/PrepareEmployeeAcceptanceDependency.php`
- Delete: `tests/Support/employee-acceptance-router.php`
- Delete: `tests/Support/employee-acceptance.ps1`
- Modify: `tests/Integration/MariaDb/FreshEmployeeSchemaContractTest.php`
- Modify: `tests/Unit/Repositories/NhanVienRepositoryTest.php`
- Modify: `docs/PROJECT_STATUS.md`
- Modify: `docs/CODEX_NEXT_HANDOFF.md`
- Modify: `docs/EMPLOYEE_MODULE_GUIDE.md`
- Modify: `docs/ARCHITECTURE.md`

**Interfaces:**
- Produces: tài liệu hiện hành mô tả đúng ba CRUD công khai và không tuyên bố auth/RBAC/scope đang hoạt động.

- [x] **Step 1: Lập inventory tham chiếu trước khi xóa**

Run: `rg -n "BootstrapNhanVienDemo|EmployeeAuth|EmployeeRbac|PermissionService|PermissionRegistry|NhanVienDepartmentScope|NhanVienTargetGuard|dang-nhap|NV_RESET_PASSWORD" app routes resources tests docs`

Phân loại: runtime/test active phải xóa; `database/sql` là lịch sử và không sửa; docs hiện hành phải cập nhật.

- [x] **Step 2: Xóa test/tool chỉ phục vụ auth/RBAC**

Xóa đúng các file đã liệt kê. Trong `FreshEmployeeSchemaContractTest` và `NhanVienRepositoryTest`, bỏ assertion gọi `findAccountByIdentifier()` nhưng giữ assertion schema, CRUD, counter và lifecycle. Không xóa worker/test lifecycle, counter, CRUD hoặc schema contract vẫn chứng minh ba module.

- [x] **Step 3: Cập nhật tài liệu**

Ghi rõ bằng tiếng Việt:

```markdown
Nhánh hiện tại giữ CRUD công khai của Nhân viên, Phòng ban và Chức vụ.
Đăng nhập, RBAC, giới hạn Trưởng phòng và đặt lại mật khẩu do nhánh này từng cung cấp đã được tháo.
Schema 15 bảng và dữ liệu vai trò/quyền vẫn được giữ để tương thích dữ liệu và chờ module của thành viên phụ trách.
```

Đánh dấu evidence cũ là lịch sử; không xóa số liệu cũ nếu cần truy nguyên, nhưng không dùng nó làm trạng thái hiện hành.

- [x] **Step 4: Kiểm tra comment/ngôn ngữ và diff**

Run: `git diff --check`

Review thủ công mọi comment/docblock đã sửa để bảo đảm tiếng Việt rõ nghĩa; không dịch file ngoài phạm vi.

---

### Task 7: Gate xác minh tổng thể và bàn giao

**Files:**
- Modify only if a failing in-scope test exposes a regression caused by Tasks 1–6.

**Interfaces:**
- Produces: evidence cuối cùng phân biệt PASS, FAIL, BLOCKED và chưa kiểm chứng.

- [x] **Step 1: Chạy PHP lint cho mọi file PHP đã sửa**

Run một vòng `php -l` trên danh sách lấy từ `git diff --name-only --diff-filter=ACM -- '*.php'` bằng PowerShell, không truyền file đã xóa.

Expected: mọi file trả `No syntax errors detected`.

- [x] **Step 2: Chạy route và focused suite**

Run: `php artisan route:list --except-vendor`

Run: `php artisan test tests/Feature/PublicCrudRouteTest.php tests/Feature/Backend/NhanVien tests/Feature/Backend/PhongBan tests/Feature/Backend/ChucVu tests/Feature/Compatibility`

Expected: PASS; không có route login/logout/reset; ba CRUD công khai.

- [x] **Step 3: Chạy full Laravel và frontend**

Run: `php artisan test`

Run: `npm run test:frontend`

Run: `npm run build`

Expected: PASS. Nếu lỗi thuộc baseline/module đồng nghiệp, ghi file/test và bằng chứng; không sửa ngoài phạm vi để làm xanh giả.

- [x] **Step 4: Chạy MariaDB disposable**

Run: `powershell -ExecutionPolicy Bypass -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb`

Expected: fresh 15-table CRUD/counter/lifecycle còn lại PASS và schema disposable được cleanup. Không chạy trên database live.

- [x] **Step 5: Chạy kiểm tra hygiene**

Run: `composer validate --no-check-publish`

Run: `git diff --check`

Run: `git status --short --branch`

Expected: chỉ có thay đổi thuộc kế hoạch, hai tài liệu spec/plan mới và file untracked của người dùng được bảo toàn; không có `public/build`, secret hoặc database dump mới.

- [ ] **Step 6: Browser smoke nếu công cụ/môi trường sẵn sàng**

Trên database disposable, mở `/`, Nhân viên, Phòng ban, Chức vụ; xác minh không gặp login, action CRUD hiện, không có lỗi console và thao tác mutation dùng fixture có thể dọn. Browser smoke dùng công cụ trình duyệt hiện có, không khôi phục acceptance harness đã xóa. Khi môi trường không cung cấp trình duyệt, ghi kết quả là `chưa kiểm chứng browser`; HTTP test hoặc Vite build không được dùng thay thế.

- [x] **Step 7: Bàn giao không commit**

Báo cáo file sửa/xóa, test thực chạy, kết quả, giới hạn và rủi ro merge. Không commit/push. Nhắc rằng lần merge sau phải ưu tiên code đồng nghiệp tại module họ sở hữu và giải quyết file dùng chung theo hunk.
