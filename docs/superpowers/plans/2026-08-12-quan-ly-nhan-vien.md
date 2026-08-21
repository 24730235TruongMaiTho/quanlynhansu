# Quản lý nhân viên Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hoàn thiện chức năng quản lý nhân viên bằng stored procedure MariaDB, Blade server-rendered, tài khoản đăng nhập và RBAC, đồng thời giữ module hợp đồng độc lập.

**Architecture:** Blade gửi request qua FormRequest và `NhanVienController`; `NhanVienService` sở hữu transaction, hash và bù trừ file; `NhanVienRepository` là nơi duy nhất gọi procedure nhân viên trên cùng connection MariaDB. Auth dùng custom Laravel `UserProvider`; hợp đồng chỉ liên kết sau khi nhân viên đã commit bằng `ma_nv` và không xuất hiện trong transaction tạo nhân viên.

**Tech Stack:** Laravel 12.62.0, PHP 8.2+, Blade, JavaScript ES modules, Vite 7, Bootstrap runtime hiện tại, PHPUnit 11, MariaDB 10.4.32.

## Global Constraints

- Người dùng đã yêu cầu làm việc trên `feature/quanly-nhan-vien`, commit kỹ rồi push sau mỗi slice hoàn chỉnh; không merge, rebase, force-push, tạo PR hoặc lấy code từ nhánh `frontend`.
- Stored procedure là bắt buộc cho CRUD/query nghiệp vụ nhân viên và auth lookup; Laravel giữ validation, transaction orchestration, `Hash::make`, filesystem và ánh xạ lỗi.
- Chỉ chạy mutation test trên database disposable có tên khớp `^quan_ly_nhan_su_employee_test_[a-f0-9]+$`; tuyệt đối từ chối database `quan_ly_nhan_su`.
- Target được xác minh là MariaDB 10.4.32; không tuyên bố tương thích MySQL 8 khi chưa có suite riêng.
- Timezone nghiệp vụ là `Asia/Ho_Chi_Minh`; MariaDB session dùng `+07:00`; không dùng `CURDATE()` cho ngày tạo/reset/nghỉ việc.
- Mã nhân viên do hệ thống cấp theo `NV001` đến `NV999`; không dùng `MAX()+1`; mã đã commit không tái sử dụng.
- Mật khẩu demo là `nhom3@{năm thao tác}`, do Laravel hash; không có cờ bắt đổi mật khẩu; không trả hash ra UI/API/log.
- Email được `LOWER(TRIM(...))`, CCCD được trim; cả hai có unique constraint.
- Client không được gửi `ma_vt`. Web create luôn gán role `NHAN_VIEN_MAC_DINH` zero quyền; update giữ nguyên role; update/delete/reset chỉ tác động target role mặc định. Phân vai ngoài bootstrap guarded thuộc flow riêng chưa nằm trong scope.
- Nhân viên đã `DA_NGHI` không đăng nhập, không kích hoạt lại trong scope này và action kết thúc làm việc gọi lại phải idempotent.
- Runtime của nhánh hiện dùng `backend.layouts.app`; page chỉ thêm content/assets scoped, không thay global shell. Shell mục tiêu `Header + Sidebar + Main + Footer`, không global navbar, được tích hợp ở workstream khác.
- Mọi route/endpoint đọc hoặc ghi dữ liệu employee fail `404` sau rollout guard mặc định tắt ở Task 6, gồm caller nghỉ phép và chấm công; chỉ bật mặc định trong cùng commit Task 18 đã có auth + Gates, nên không có commit trung gian push PII/mutation công khai.
- Không tạo class/procedure/route hợp đồng giả. Chính sách xóa chỉ phụ thuộc interface bảng `hop_dong(ma_nv)` hiện có.
- `docs/CODEX_FRONTEND_HANDOFF.md` là local-only và không bao giờ được stage.
- Suite mặc định hiện có baseline 1 pass/1 fail do `/` trả 404; không xóa assertion cũ để làm xanh giả. Mỗi task phải chạy test đích, còn full-suite failure cũ phải được báo riêng.
- Mỗi task kết thúc bằng stage tường minh, xem diff, `git diff --cached --check`, commit đúng scope và push; không commit khi test đích thất bại.
- Plan và design spec phải được commit/push làm nguồn thực thi trước Task 1. Executor dừng nếu hai file chưa tracked hoặc upstream chưa chứa planning delivery; không chờ đến Task 20 mới stage plan.

## Baseline đã kiểm tra ngày 2026-08-12

- HEAD trước triển khai: `fb070606e50ef065a7a7a587c9ae83d1f3cd42e1`.
- 44 route; route nhân viên còn URI cũ và `PUT` đang map sai vào `show`.
- `npm run build`: pass.
- `composer validate --no-check-publish`: pass.
- `php artisan test`: 1 pass, 1 fail ở `tests/Feature/ExampleTest.php` vì `/` trả 404.
- MariaDB live có schema legacy nhưng các bảng nhân viên/master data/phụ thuộc đều trống; không được dùng live DB để test mutation.

## File Structure

### Database và test harness

- `database/sql/employee/README.md`: thứ tự chạy, preflight và rollback/restore guidance.
- `database/sql/employee/2026_08_12_001_schema.sql`: schema nhân viên, role mặc định, counter và safe view.
- `database/sql/employee/2026_08_12_002_read_routines.sql`: list/detail/lookup và employee-attendance aggregate contract.
- `database/sql/employee/2026_08_12_003_create_routines.sql`: tạo nhân viên và upsert địa chỉ.
- `database/sql/employee/2026_08_12_004_update_routines.sql`: sửa hồ sơ và avatar path.
- `database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql`: delete/terminate/reset/auth lookup.
- `database/sql/employee/2026_08_12_006_rbac.sql`: permission key safety và role deletion guard.
- `quan_ly_nhan_su.session.sql`: canonical clean-install dump, đồng bộ sau từng script.
- `phpunit.mariadb.xml`: suite MariaDB riêng, không chứa credential.
- `tests/Support/SqlScriptRunner.php`: thực thi script có `DELIMITER` bằng PDO.
- `tests/Support/CreatesDisposableMariaDb.php`: tạo/drop database test với guard fail-closed.
- `tests/Support/invoke-employee-mariadb-tests.ps1`: nạp credential theo từng process rồi chạy suite MariaDB; không lưu secret.
- `tests/Integration/MariaDb/MariaDbTestCase.php`: base test cấu hình connection Laravel cùng database disposable.
- `tests/Fixtures/MariaDb/employee_legacy_schema.sql`: fixture legacy tối thiểu, không có `DROP DATABASE`.

### Backend

- `config/nhanvien.php`: rollout guard fail-closed, prefix avatar mặc định và override acceptance cô lập.
- `app/Http/Middleware/EnsureNhanVienModuleEnabled.php`: ẩn route nhân viên cho tới checkpoint auth/RBAC.
- `app/Support/DisposableMariaDbGuard.php`: guard target dùng chung cho test harness, worker và bootstrap command runtime.
- `app/Models/NhanVien.php`: model/Authenticatable map bảng và khóa chuỗi.
- `app/Contracts/NhanVienRepositoryContract.php`: seam ổn định cho service/auth/permission tests.
- `app/Contracts/NhanVienServiceContract.php`: seam controller/cross-module cho feature tests.
- `app/Repositories/NhanVienRepository.php`: toàn bộ `CALL`, OUT parameter và result-set consumption.
- `app/Services/NhanVienService.php`: paginator, transaction, password, avatar compensation và lifecycle.
- `app/Exceptions/NhanVienDomainException.php`: lỗi miền không lộ SQL.
- `app/Support/NhanVienProcedureExceptionMapper.php`: map mã procedure/constraint sang lỗi field an toàn.
- `app/Support/NhanVienAvatarPath.php`: tạo/validate relative avatar path trước mọi ghi/xóa.
- `app/Support/NhanVienTargetGuard.php`: fail `403` nếu mutation nhắm tài khoản ngoài role mặc định; procedure lặp lại check sau row lock.
- `app/Enums/NhanVienRemovalAction.php`: `DELETED` và `TERMINATED`.
- `app/Enums/NhanVienPermission.php`: năm permission symbol đã duyệt.
- `app/Auth/NhanVienUserProvider.php`: custom provider gọi repository.
- `app/Services/NhanVienPermissionService.php`: đọc/cached permission symbols trong một request.
- `app/Console/Commands/BootstrapNhanVienDemo.php`: bootstrap local/testing có guard và input tường minh.
- `app/Http/Controllers/Backend/NhanVienController.php`: web CRUD.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`: login/logout.
- `app/Http/Requests/ListNhanVienRequest.php`, `StoreNhanVienRequest.php`, `UpdateNhanVienRequest.php`, `Auth/LoginRequest.php`: validation.
- `app/Rules/Du18TuoiTaiNgayVaoLam.php`: tuổi tại ngày vào làm.
- `tests/Support/InteractsWithEmployeeModule.php`: bật module trong feature test và, từ Task 18, tạo actor/quyền tường minh.

### UI

- `resources/views/backend/nhanvien/index.blade.php`: list/filter/pagination thật.
- `resources/views/backend/nhanvien/create.blade.php`, `edit.blade.php`, `show.blade.php`: create/edit/detail.
- `resources/views/backend/nhanvien/partials/*.blade.php`: flash, personal/address/employment fields và action dialogs.
- `resources/css/nhanvien/nhanvien.css`: CSS scoped dưới `.employee-page`.
- `resources/js/frontend/nhanvien/nhanvien.js`: entry Vite.
- `resources/js/frontend/nhanvien/wizard.js`, `wizard-state.js`, `confirm-actions.js`: progressive enhancement và dialog focus.
- `resources/views/auth/login.blade.php`: login không có remember checkbox.

---

## Task 0 — Deliver this approved planning source before implementation

- [ ] Từ nhánh exact `feature/quanly-nhan-vien`, stage duy nhất `docs/superpowers/specs/2026-08-12-quan-ly-nhan-vien-design.md` và `docs/superpowers/plans/2026-08-12-quan-ly-nhan-vien.md`; xem full staged diff, chạy `git diff --cached --check`, và xác nhận `git diff --cached --name-only` chỉ có đúng hai path này.
- [ ] Commit `docs(employee): add executable employee implementation plan`, rồi `git push`; không merge/rebase/fetch/force-push/PR.
- [ ] Chạy `git ls-files --error-unmatch` cho hai path; lấy `$employeePlanCommit = git log -1 --format=%H -- docs/superpowers/plans/2026-08-12-quan-ly-nhan-vien.md`; `git merge-base --is-ancestor $employeePlanCommit origin/feature/quanly-nhan-vien`, `git rev-parse HEAD` và `git rev-parse '@{u}'` phải chứng minh delivery ở upstream.
- [ ] Worktree không stage `docs/CODEX_FRONTEND_HANDOFF.md` hay file ngoài task. Nếu delivery/gate trên fail thì dừng trước Task 1; không triển khai từ plan local-only.

## Phase 1 — Safety foundation

### Task 1: Disposable MariaDB test harness

**Files:**

- Create: `phpunit.mariadb.xml`
- Create: `app/Support/DisposableMariaDbGuard.php`
- Create: `tests/Support/SqlScriptRunner.php`
- Create: `tests/Support/CreatesDisposableMariaDb.php`
- Create: `tests/Support/invoke-employee-mariadb-tests.ps1`
- Create: `tests/Integration/MariaDb/MariaDbTestCase.php`
- Create: `tests/Fixtures/MariaDb/employee_legacy_schema.sql`
- Create: `tests/Unit/Support/DisposableMariaDbSafetyTest.php`
- Create: `tests/Integration/MariaDb/DisposableMariaDbSmokeTest.php`

**Interfaces:**

- Consumes: chỉ các biến `MARIADB_TEST_ENABLED`, `MARIADB_TEST_HOST`, `MARIADB_TEST_PORT`, `MARIADB_TEST_USERNAME`, `MARIADB_TEST_PASSWORD`; wrapper nhận switch bắt buộc `-EnableDisposableMariaDb` và `-Filter` tùy chọn.
- Produces: `App\Support\DisposableMariaDbGuard::assertSafeDatabaseName(string): void`, `DisposableMariaDbGuard::environment(): array`; `MariaDbTestCase::pdo(): PDO`, `runSql(string): void`, và connection `employee_test` trỏ DB disposable. Guard nằm trong autoload production vì command Task 17 cũng dùng; không có code `app/` phụ thuộc namespace `Tests\`.
- Trong `phpunit.mariadb.xml`, từng `<env>` sentinel bắt buộc có `force="true"` cho `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL=''`, `DB_SOCKET=''`, `SESSION_DRIVER=array`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`; không kế thừa database/store từ shell hoặc `.env` trước guard.

- [ ] **Step 1: Viết test guard thất bại trước**

```php
public function test_main_database_name_is_always_rejected(): void
{
    $this->expectException(RuntimeException::class);
    DisposableMariaDbGuard::assertSafeDatabaseName('quan_ly_nhan_su');
}

public function test_generated_name_is_accepted(): void
{
    DisposableMariaDbGuard::assertSafeDatabaseName(
        'quan_ly_nhan_su_employee_test_a1b2c3d4'
    );
    $this->addToAssertionCount(1);
}
```

`DisposableMariaDbSafetyTest` gọi class guard trực tiếp và parse XML để assert mọi sentinel trên có `force="true"`. `DisposableMariaDbSmokeTest` assert ngay sau `parent::setUp()` và trước trait switch rằng process env/config là SQLite `:memory:`, `DB_URL`/socket rỗng và không có MySQL connection nào được resolve; chỉ sau khi guard tạo tên/DB mới cấu hình `employee_test` và đổi default. Trait `CreatesDisposableMariaDb`, CLI dependency helper Task 13 và acceptance helper Task 19 đều compose cùng class này; không sao chép regex. Guard đọc `MARIADB_TEST_*` bằng `getenv()` và không fallback sang `DB_*`.

- [ ] **Step 2: Chạy test để xác nhận RED**

Run: `php artisan test tests/Unit/Support/DisposableMariaDbSafetyTest.php`

Expected: FAIL vì trait/class chưa tồn tại.

- [ ] **Step 3: Cài guard và SQL runner**

`DisposableMariaDbGuard::assertSafeDatabaseName()` chỉ chấp nhận regex chính xác và từ chối tên production. `environment()` yêu cầu `MARIADB_TEST_ENABLED === '1'` và username không rỗng. Trait tạo tên bằng `bin2hex(random_bytes(6))`, dùng admin PDO để `CREATE DATABASE`, sau đó cấu hình:

```php
public static function assertSafeDatabaseName(string $database): void
{
    if (preg_match('/\Aquan_ly_nhan_su_employee_test_[a-f0-9]+\z/', $database) !== 1) {
        throw new RuntimeException('Unsafe MariaDB test database name.');
    }
}

public static function environment(): array
{
    if (getenv('MARIADB_TEST_ENABLED') !== '1') {
        throw new RuntimeException('MARIADB_TEST_ENABLED=1 is required.');
    }
    $username = getenv('MARIADB_TEST_USERNAME');
    if (! is_string($username) || $username === '') {
        throw new RuntimeException('MARIADB_TEST_USERNAME is required.');
    }

    return [
        'host' => getenv('MARIADB_TEST_HOST') ?: '127.0.0.1',
        'port' => getenv('MARIADB_TEST_PORT') ?: '3306',
        'username' => $username,
        'password' => getenv('MARIADB_TEST_PASSWORD') ?: '',
    ];
}

$testEnv = DisposableMariaDbGuard::environment();

config()->set('database.connections.employee_test', [
    'driver' => 'mysql',
    'host' => $testEnv['host'],
    'port' => $testEnv['port'],
    'database' => $this->databaseName,
    'username' => $testEnv['username'],
    'password' => $testEnv['password'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'strict' => true,
    'timezone' => '+07:00',
]);
DB::purge('employee_test');
DB::setDefaultConnection('employee_test');
```

Trait lưu default connection trước khi đổi sang `employee_test`. Trong `tearDown()`, nó giải phóng mọi PDO/result set, gọi `DB::disconnect('employee_test')` rồi `DB::purge('employee_test')`; trong `finally` luôn khôi phục default connection cũ và dùng admin PDO độc lập để `DROP DATABASE` tên đã qua guard. Smoke suite có test thứ hai chứng minh default connection đã được khôi phục và database của test trước không còn, kể cả khi test trước ném lỗi. `SqlScriptRunner` đọc `DELIMITER`, gom đúng statement và gọi `$pdo->exec($statement)`; báo lỗi nếu còn buffer không rỗng cuối file.

`invoke-employee-mariadb-tests.ps1` fail nếu thiếu switch `-EnableDisposableMariaDb`, đặt `MARIADB_TEST_ENABLED=1` chỉ cho process con, dùng host/port từ env hoặc default local, và prompt username/password nếu process hiện tại chưa có hai biến đó. Password được đọc masked, chuyển sang process env chỉ trong `try`, không in hoặc ghi file; `finally` khôi phục/xóa toàn bộ giá trị env tạm. Script gọi `php vendor/bin/phpunit -c phpunit.mariadb.xml`, thêm `--filter` khi có `-Filter`, và trả nguyên exit code. Vì mỗi shell là process mới, mọi lệnh MariaDB sau đây đều gọi wrapper này; không dựa vào biến đã nhập ở command block trước. Khi chạy non-interactive, executor phải inject `MARIADB_TEST_*` cho chính tool call đó.

- [ ] **Step 4: Tạo legacy fixture và smoke test**

Fixture chỉ tạo 14 bảng/view/routine tối thiểu cần cho employee tests, không chứa `DROP DATABASE`, `CREATE DATABASE`, `USE` hoặc dữ liệu thật. Smoke test chạy fixture rồi assert:

```php
$this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
$table = $this->pdo()->query("SHOW TABLES LIKE 'nhan_vien'")->fetchColumn();
$this->assertSame('nhan_vien', $table);
```

- [ ] **Step 5: Chạy unit và MariaDB smoke suite**

```powershell
php artisan test tests/Unit/Support/DisposableMariaDbSafetyTest.php
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'DisposableMariaDbSmokeTest'
```

Expected: PASS; database disposable bị drop sau test kể cả khi assertion fail.

- [ ] **Step 6: Commit và push**

```powershell
git add -- phpunit.mariadb.xml app/Support/DisposableMariaDbGuard.php tests/Support/SqlScriptRunner.php tests/Support/CreatesDisposableMariaDb.php tests/Support/invoke-employee-mariadb-tests.ps1 tests/Integration/MariaDb/MariaDbTestCase.php tests/Fixtures/MariaDb/employee_legacy_schema.sql tests/Unit/Support/DisposableMariaDbSafetyTest.php tests/Integration/MariaDb/DisposableMariaDbSmokeTest.php
git diff --cached --check
git commit -m "test(employee): add disposable MariaDB harness"
git push
```

### Task 2: Business timezone and safe local defaults

**Files:**

- Modify: `config/app.php:68`
- Modify: `config/database.php:47-82`
- Modify: `.env.example:1-40`
- Create: `tests/Feature/Configuration/BusinessTimezoneTest.php`

**Interfaces:**

- Consumes: `APP_TIMEZONE`, `DB_TIMEZONE`.
- Produces: Laravel default `Asia/Ho_Chi_Minh`; mysql/mariadb session `+07:00`; example environment dùng mysql + file/session/cache + sync queue.

- [ ] **Step 1: Viết test cấu hình**

```php
public function test_business_timezone_defaults_are_consistent(): void
{
    $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
    $this->assertSame('+07:00', config('database.connections.mysql.timezone'));
    $this->assertSame('+07:00', config('database.connections.mariadb.timezone'));
}
```

- [ ] **Step 2: Xác nhận RED**

Run: `php artisan test tests/Feature/Configuration/BusinessTimezoneTest.php`

Expected: FAIL vì app đang là `UTC` và connection chưa có timezone.

- [ ] **Step 3: Sửa cấu hình tối thiểu**

```php
// config/app.php
'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),

// cả mysql và mariadb trong config/database.php
'timezone' => env('DB_TIMEZONE', '+07:00'),
```

`.env.example` đặt `APP_TIMEZONE=Asia/Ho_Chi_Minh`, `DB_CONNECTION=mysql`, `DB_DATABASE=quan_ly_nhan_su`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`; username/password để trống an toàn, không ghi credential local.

- [ ] **Step 4: Verify và commit**

```powershell
php artisan config:clear
php artisan test tests/Feature/Configuration/BusinessTimezoneTest.php
php artisan about --only=environment,drivers
git add -- config/app.php config/database.php .env.example tests/Feature/Configuration/BusinessTimezoneTest.php
git diff --cached --check
git commit -m "fix(config): align employee business timezone"
git push
```

### Task 3: Employee schema foundation and safe shared view

**Files:**

- Create: `database/sql/employee/README.md`
- Create: `database/sql/employee/2026_08_12_001_schema.sql`
- Modify: `quan_ly_nhan_su.session.sql:63-202`
- Create: `tests/Integration/MariaDb/EmployeeSchemaMigrationTest.php`
- Create: `tests/Integration/MariaDb/EmployeeViewCompatibilityTest.php`
- Create: `tests/Integration/MariaDb/CanonicalDumpReplayTest.php`

**Interfaces:**

- Consumes: legacy schema fixture.
- Produces: `trang_thai_lam_viec.ky_hieu`, nullable-unique `vai_tro.ky_hieu` với role zero-quyền `NHAN_VIEN_MAC_DINH`, `nhan_vien.anh_dai_dien`, `nhan_vien.ngay_nghi_viec`, unique email/CCCD, `dia_chi_nhan_vien`, `bo_dem_ma_nhan_vien`, view không hash.

- [ ] **Step 1: Viết integration tests cho schema và preflight**

Các test phải khóa các case: email/CCCD null-rỗng, email sai format, CCCD không đủ 12 chữ số, duplicate email sau lowercase, duplicate CCCD sau trim, mã ngoài `NV[0-9]{3}`, status thiếu/trùng/mapping mơ hồ, employee đã map `DA_NGHI` nhưng thiếu ngày nghỉ xác nhận, tên/ký hiệu role mặc định xung đột hoặc role candidate đã có quyền đều làm script dừng trước DDL. Sau mỗi lỗi, assert chưa có các cột employee/status/role mới và không có bảng counter/address; database sạch thì script pass, có đúng một `NHAN_VIEN_MAC_DINH` và zero mapping.

```php
public function test_safe_view_never_exposes_password_hash(): void
{
    $columns = $this->pdo()->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $this->assertNotContains('mat_khau', $columns);
    $this->assertContains('ky_hieu', $columns);
    $this->assertContains('anh_dai_dien', $columns);
}
```

- [ ] **Step 2: Xác nhận RED trên legacy fixture**

Run: `pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeSchemaMigrationTest|EmployeeViewCompatibilityTest'`

Expected: FAIL vì schema/cột/constraint chưa tồn tại và view còn `mat_khau`.

- [ ] **Step 3: Viết script 001 fail-closed**

Script chạy trên database đang được chọn; không có lệnh database-level. Thứ tự bắt buộc:

1. preflight mã/email/CCCD/status và role mặc định;
2. normalize `LOWER(TRIM(email))`, `TRIM(cccd)`;
3. thêm cột/unique employee/status và `vai_tro.ky_hieu VARCHAR(50) NULL UNIQUE`;
4. với tên normalize exact `nhân viên mặc định`: insert nếu chưa có; map ký hiệu nếu có đúng một row zero-quyền; fail nếu nhiều row/ký hiệu xung đột/candidate có mapping; rồi assert đúng một `NHAN_VIEN_MAC_DINH` zero mapping;
5. tạo `dia_chi_nhan_vien` với `ON DELETE CASCADE`;
6. tạo counter row `('NHAN_VIEN', suffix lớn nhất hoặc 0)` và không hạ counter;
7. recreate view với các cột an toàn đã khóa trong spec, gồm alias `ky_hieu_vai_tro`, không hash.

Mọi lỗi preflight dùng mã cụ thể trong whitelist `NV_MIGRATION_EMAIL_INVALID`, `NV_MIGRATION_CCCD_INVALID`, `NV_MIGRATION_STATUS_AMBIGUOUS`, `NV_MIGRATION_ROLE_AMBIGUOUS` hoặc `NV_MIGRATION_EXISTING_TERMINATION_DATE_REQUIRED`. MariaDB DDL implicit commit nên không bắt đầu DDL trước khi toàn bộ preflight sạch.

Với status, script xử lý duy nhất hai trạng thái xác định: bảng rỗng thì insert ba dòng `DANG_LAM`/`Đang làm việc`, `THU_VIEC`/`Thử việc`, `DA_NGHI`/`Đã nghỉ`; bảng đã có dữ liệu nhưng chưa có `ky_hieu` thì chỉ map ba tên sau `LOWER(TRIM(ten_tt))` khớp chính xác ba nhãn này, và fail preflight nếu thiếu, trùng hoặc có tên khác. Nhân viên map sang `DA_NGHI` mà chưa có ngày nghỉ đã xác nhận cũng làm script dừng, không dùng ngày chạy migration.

- [ ] **Step 4: Regression view consumers**

Trong `EmployeeViewCompatibilityTest`, tạo fixture tối thiểu rồi gọi `sp_cham_cong_nhan_vien_tim_kiem`, `sp_luong_tim_kiem`, `sp_luong_xem`; assert routine không yêu cầu `mat_khau` và cột an toàn cũ vẫn tồn tại.

- [ ] **Step 5: Đồng bộ canonical dump và verify**

`CanonicalDumpReplayTest` đọc dump như text và fail nếu không có đúng một `DROP DATABASE IF EXISTS quan_ly_nhan_su`, một `CREATE DATABASE quan_ly_nhan_su`, một `USE quan_ly_nhan_su`. Test tạo một tên guarded và chỉ replace identifier ở đúng ba statement database-level trong bản sao memory/temp file; sau đó dùng regex theo toàn bộ statement để từ chối identifier production còn đứng độc lập, không dùng phép tìm substring vì tên guarded cũng chứa tiền tố `quan_ly_nhan_su`. Tuyệt đối không chạy dump gốc. Trong `finally`, drop guarded DB và xóa đúng temp file. Assert clean replay có role mặc định zero quyền, cột/constraint/view/counter/address và exact routine signatures hiện có ở thời điểm Task 3; các Task 5/10/12/13/16 sửa expected signatures trong chính test này và chạy lại sau khi đồng bộ routine tương ứng, nên drift dump bị bắt ở từng commit.

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeSchemaMigrationTest|EmployeeViewCompatibilityTest|CanonicalDumpReplayTest'
rg -n "mat_khau" database/sql/employee/2026_08_12_001_schema.sql quan_ly_nhan_su.session.sql
git diff --check
```

Expected: PASS; `mat_khau` chỉ còn ở bảng/auth/write contracts, không nằm trong view/list/detail.

- [ ] **Step 6: Commit và push**

```powershell
git add -- database/sql/employee/README.md database/sql/employee/2026_08_12_001_schema.sql quan_ly_nhan_su.session.sql tests/Integration/MariaDb/EmployeeSchemaMigrationTest.php tests/Integration/MariaDb/EmployeeViewCompatibilityTest.php tests/Integration/MariaDb/CanonicalDumpReplayTest.php
git diff --cached --check
git commit -m "feat(employee-db): add safe employee schema foundation"
git push
```

## Checkpoint A — Database safety

- [ ] MariaDB harness từ chối database chính và tự cleanup.
- [ ] Schema script pass trên legacy fixture sạch, fail trước DDL với dữ liệu mơ hồ.
- [ ] Shared view không có `mat_khau`; chấm công/lương regression pass.
- [ ] Local/live database chưa bị mutation.

---

## Phase 2 — Domain and read path

### Task 4: Employee model, domain errors, and removal enum

**Files:**

- Modify: `app/Models/NhanVien.php`
- Create: `app/Exceptions/NhanVienDomainException.php`
- Create: `app/Support/NhanVienProcedureExceptionMapper.php`
- Create: `app/Enums/NhanVienRemovalAction.php`
- Create: `tests/Unit/Models/NhanVienTest.php`
- Create: `tests/Unit/Support/NhanVienProcedureExceptionMapperTest.php`

**Interfaces:**

- Produces: `NhanVien` implements `AuthenticatableContract`; `NhanVien::fromAuthProcedureRow(object): self`; `NhanVienDomainException::$domainCode` và `$field`; `NhanVienProcedureExceptionMapper::map(QueryException): NhanVienDomainException`; enum string `NhanVienRemovalAction::Deleted = 'DELETED'`, `NhanVienRemovalAction::Terminated = 'TERMINATED'`.

- [ ] **Step 1: Viết unit tests RED**

```php
public function test_employee_auth_mapping_uses_legacy_columns(): void
{
    $employee = NhanVien::fromAuthProcedureRow((object) [
        'ma_nv' => 'NV001',
        'ho_ten' => 'Nguyễn An',
        'email' => 'an@example.test',
        'mat_khau' => 'hash',
        'ma_vt' => 1,
        'ky_hieu' => 'DANG_LAM',
    ]);
    $this->assertSame('nhan_vien', $employee->getTable());
    $this->assertSame('ma_nv', $employee->getKeyName());
    $this->assertSame('NV001', $employee->getKey());
    $this->assertSame('mat_khau', $employee->getAuthPasswordName());
    $this->assertSame('hash', $employee->getAuthPassword());
    $this->assertNull($employee->getRememberTokenName());
    $this->assertContains('mat_khau', $employee->getHidden());
}
```

Mapper tests tạo `QueryException` có message `NV_EMAIL_DUPLICATE`, `NV_CCCD_DUPLICATE`, `NV_DEFAULT_ROLE_INVALID`, `NV_PRIVILEGED_TARGET`, `NV_AUTH_HASH_STALE`, constraint `uq_nhan_vien_email`, và unknown SQL error; unknown luôn thành thông báo chung, không chứa SQL. HTTP target guard dùng `AuthorizationException`/403, còn procedure mapper biến race-time `NV_PRIVILEGED_TARGET` thành cùng thông báo authorization an toàn; auth stale hash thành lỗi xác thực chung, không lộ hash.

- [ ] **Step 2: Xác nhận RED**

Run: `php artisan test tests/Unit/Models/NhanVienTest.php tests/Unit/Support/NhanVienProcedureExceptionMapperTest.php`

- [ ] **Step 3: Cài model và mapper**

```php
final class NhanVien extends Model implements AuthenticatableContract
{
    use \Illuminate\Auth\Authenticatable;

    protected $table = 'nhan_vien';
    protected $primaryKey = 'ma_nv';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $hidden = ['mat_khau'];

    public function getAuthPasswordName(): string
    {
        return 'mat_khau';
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public static function fromAuthProcedureRow(object $row): self
    {
        $employee = (new self())->forceFill([
            'ma_nv' => $row->ma_nv,
            'ho_ten' => $row->ho_ten,
            'email' => $row->email,
            'mat_khau' => $row->mat_khau,
            'ma_vt' => $row->ma_vt,
            'ky_hieu' => $row->ky_hieu,
        ]);
        $employee->exists = true;

        return $employee;
    }
}
```

Không redeclare `$authPasswordName`/`$rememberTokenName` vì trait Laravel 12 đã sở hữu hai property; override getter như trên, token getter trả null và setter là no-op. `NhanVienTest` bắt buộc load class, kiểm tra `class_implements()` và getter để bắt trait-property fatal. Không mở `$guarded`/mass assignment toàn bộ. Repository auth bắt buộc hydrate qua `fromAuthProcedureRow()`. Mapper chỉ whitelist mã `NV_*` trong spec và hai tên unique constraint; trả thông điệp tiếng Việt cố định. `NhanVienRepository` định nghĩa helper private `databaseOperation(Closure $operation): mixed` bắt `QueryException`, gọi mapper rồi ném `NhanVienDomainException`; toàn bộ chuỗi DB trong mỗi public method—gồm `SET`, `CALL`, consume result sets và `SELECT @out`—phải nằm trong một closure này. Helper `call()` chỉ thực hiện `selectResultSets()` và chỉ được gọi bên trong `databaseOperation()`. Không chuyển raw `$exception->getMessage()` lên controller.

- [ ] **Step 4: Verify, commit và push**

```powershell
php artisan test tests/Unit/Models/NhanVienTest.php tests/Unit/Support/NhanVienProcedureExceptionMapperTest.php
php -l app/Models/NhanVien.php
php -l app/Support/NhanVienProcedureExceptionMapper.php
git add -- app/Models/NhanVien.php app/Exceptions/NhanVienDomainException.php app/Support/NhanVienProcedureExceptionMapper.php app/Enums/NhanVienRemovalAction.php tests/Unit/Models/NhanVienTest.php tests/Unit/Support/NhanVienProcedureExceptionMapperTest.php
git diff --cached --check
git commit -m "feat(employee): define employee domain contracts"
git push
```

### Task 5: Read procedures and repository contract

**Files:**

- Create: `database/sql/employee/2026_08_12_002_read_routines.sql`
- Modify: `database/sql/employee/README.md`
- Modify: `quan_ly_nhan_su.session.sql:742-1030`
- Modify: `quan_ly_nhan_su.session.sql:1031-1160`
- Create: `app/Contracts/NhanVienRepositoryContract.php`
- Create: `app/Repositories/NhanVienRepository.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `tests/Integration/MariaDb/CanonicalDumpReplayTest.php`
- Create: `tests/Integration/MariaDb/EmployeeReadProcedureTest.php`
- Create: `tests/Integration/MariaDb/NhanVienRepositoryReadTest.php`

**Interfaces:**

- `paginate(array $filters): LengthAwarePaginator`
- `paginateAttendance(array $filters): LengthAwarePaginator`
- `find(string $maNv): ?object`
- `lookups(): array{phong_ban: array, chuc_vu: array, trang_thai: array}`
- `NhanVienRepositoryContract` khai báo toàn bộ method dần được thêm ở Task 5/10/12/13/16; service, auth provider và permission service chỉ type-hint contract. Concrete `final class NhanVienRepository implements NhanVienRepositoryContract` inject `DatabaseManager`/mapper, dùng connection mặc định và không mở PDO/connection thứ hai bên trong transaction. `AppServiceProvider::register()` bind contract → concrete. Tests mock contract, không mock final concrete.

- [ ] **Step 1: Viết integration tests khóa result shape**

Seed hai nhân viên, role mặc định và master data tối thiểu. Test list phải bao phủ tìm theo mã/tên/email/CCCD/phòng ban/chức vụ, ba filter, thứ tự `ma_nv ASC`, trang ngoài phạm vi, `p_so_dong` ngoài `1..100`, và OUT total. Test detail phải khóa đúng các cột trong đặc tả, gồm `ky_hieu_vai_tro`, địa chỉ nullable cho dữ liệu legacy và không có `mat_khau`. Tạo thêm chấm công ở hai tháng rồi test `sp_cham_cong_nhan_vien_phan_trang` theo keyword/phòng ban/tháng/năm, trang ngoài phạm vi, validation tháng `1..12`/năm `2000..2100`/page-size và OUT total. Aggregate tests dùng giờ nguyên vì schema legacy là `SMALLINT`: `3→0`, `4→0.5`, `7→0.5`, `8→1`, `9→1`; đếm từng flag vào muộn/về sớm đúng một lần, bỏ row tháng khác và trả ba số `0` cho employee không có attendance. Đây là test MariaDB thật cho contract đang thiếu, không dùng mock success.

```php
$this->pdo()->exec('SET @tong_so = 0');
$statement = $this->pdo()->prepare(
    'CALL sp_nhan_vien_danh_sach_phan_trang(?, ?, ?, ?, ?, ?, @tong_so)'
);
$statement->execute([null, null, null, null, 1, 20]);
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
$statement->closeCursor();

$total = (int) $this->pdo()
    ->query('SELECT @tong_so')
    ->fetchColumn();

$this->assertSame(2, $total);
$this->assertArrayNotHasKey('mat_khau', $rows[0]);
```

- [ ] **Step 2: Xác nhận RED**

Run: `pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeReadProcedureTest|NhanVienRepositoryReadTest'`

Expected: FAIL vì procedure phân trang và repository chưa tồn tại.

- [ ] **Step 3: Viết script 002 với SELECT tường minh**

Script phải inventory lại caller bằng:

```powershell
rg -n "sp_nhan_vien_(danh_sach|tim_kiem|chi_tiet)|vw_danh_sach_nhan_vien_chi_tiet|sp_(phong_ban|chuc_vu|vai_tro|trang_thai_lam_viec)_danh_sach" app resources routes tests docs quan_ly_nhan_su.session.sql
```

Sau đó drop hai contract legacy không còn caller `sp_nhan_vien_danh_sach`, `sp_nhan_vien_tim_kiem`; create `sp_nhan_vien_danh_sach_phan_trang`; replace `sp_nhan_vien_chi_tiet`; và create contract còn thiếu `sp_cham_cong_nhan_vien_phan_trang(p_tu_khoa, p_ma_pb, p_thang, p_nam, p_trang, p_so_dong, OUT p_tong_so)`. Procedure chấm công left-join aggregate theo exact tháng/năm, trả tường minh `ma_nv, ho_ten, gioi_tinh, sdt, email, ma_pb, ten_pb, ma_cv, ten_cv, so_lan_vao_muon, so_lan_ve_som, so_ngay_cham_cong`, thứ tự `ma_nv ASC`, pagination ổn định và không hash. Công thức là `>=8 → 1`, `>=4 → 0.5`, còn lại `0`; không gọi `fn_tinh_so_ngay_cong` vì function legacy dùng exact `=8/=4` khác contract UI, và không đổi `SMALLINT` trong slice employee. Giữ shape tương thích của bốn lookup shared: phòng ban vẫn trả `ma_pb, ten_pb, so_nhan_vien`; chức vụ trả `ma_cv, ten_cv, he_so_phu_cap`; vai trò trả `ma_vt, ten_vt, mo_ta`; trạng thái mở rộng thành `ma_tt, ky_hieu, ten_tt`. Thêm regression gọi `PhongBanController` với procedure phòng ban. Trạng thái `DA_NGHI` vẫn có trong dữ liệu đọc nhưng create/edit lọc theo rule giao diện. Không dùng compatibility wrapper employee, `SELECT *`, dynamic SQL hoặc trả hash.

- [ ] **Step 4: Cài repository và tiêu thụ mọi result set**

```php
final class NhanVienRepository implements NhanVienRepositoryContract
{
    public function __construct(
        private DatabaseManager $database,
        private NhanVienProcedureExceptionMapper $exceptions,
    ) {}

    private function connection(): Connection
    {
        return $this->database->connection();
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->databaseOperation(function () use ($filters): LengthAwarePaginator {
            $connection = $this->connection();
            $connection->statement('SET @nv_tong_so = 0');
            $sets = $this->call(
                'CALL sp_nhan_vien_danh_sach_phan_trang(?, ?, ?, ?, ?, ?, @nv_tong_so)',
                [
                    $filters['tu_khoa'], $filters['ma_pb'], $filters['ma_cv'],
                    $filters['ma_tt'], $filters['page'], $filters['so_dong'],
                ],
            );
            $total = (int) $connection
                ->selectOne('SELECT @nv_tong_so AS total', [], false)->total;

            return new LengthAwarePaginator(
                collect($sets[0] ?? []),
                $total,
                $filters['so_dong'],
                $filters['page'],
                ['pageName' => 'page'],
            );
        });
    }
}
```

`call()` thực hiện `selectResultSets($sql, $bindings, false)`; list/detail/lookups gọi nó bên trong `databaseOperation()`. `paginate()` và `paginateAttendance()` đều đặt `SET @...`, `CALL`, consume cursors và `SELECT @...` trong cùng một `databaseOperation()` closure. Mọi `selectOne` đọc OUT truyền `false`; mọi statement dùng cùng `$connection`/write PDO. `find()` trả `null` khi result đầu rỗng. `lookups()` chỉ gọi phòng ban/chức vụ/trạng thái và trả ba key cố định; role mặc định được procedure create resolve phía server, không gửi lookup role cho form. `NhanVienRepositoryReadTest` cấu hình read/write PDO khác nhau để chứng minh OUT/session vẫn dùng write connection, rồi giả lập `QueryException` riêng tại `SET`, `CALL` và `SELECT @out`; cả ba phải qua mapper và không lộ SQL.

- [ ] **Step 5: Verify cả SQL và repository**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeReadProcedureTest|NhanVienRepositoryReadTest|CanonicalDumpReplayTest'
php artisan test tests/Unit/Support/NhanVienProcedureExceptionMapperTest.php
php -l app/Repositories/NhanVienRepository.php
rg -n "SELECT \*|mat_khau" database/sql/employee/2026_08_12_002_read_routines.sql
```

Expected: targeted tests PASS; grep không tìm thấy `SELECT *` hoặc `mat_khau` trong read script.

- [ ] **Step 6: Commit và push**

```powershell
git add -- database/sql/employee/2026_08_12_002_read_routines.sql database/sql/employee/README.md quan_ly_nhan_su.session.sql app/Contracts/NhanVienRepositoryContract.php app/Repositories/NhanVienRepository.php app/Providers/AppServiceProvider.php tests/Integration/MariaDb/CanonicalDumpReplayTest.php tests/Integration/MariaDb/EmployeeReadProcedureTest.php tests/Integration/MariaDb/NhanVienRepositoryReadTest.php
git diff --cached --check
git commit -m "feat(employee-db): add employee read contracts"
git push
```

### Task 6: Canonical server-rendered employee list

**Files:**

- Create: `app/Http/Requests/ListNhanVienRequest.php`
- Create: `app/Contracts/NhanVienServiceContract.php`
- Create: `app/Services/NhanVienService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `app/Http/Middleware/EnsureNhanVienModuleEnabled.php`
- Modify: `app/Http/Controllers/Backend/NhanVienController.php`
- Create: `config/nhanvien.php`
- Modify: `routes/web.php:53-65`
- Modify: `routes/api.php:64-78`
- Rewrite: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/layouts/sidebar.blade.php`
- Create: `tests/Support/InteractsWithEmployeeModule.php`
- Create: `tests/Feature/Backend/NhanVien/NhanVienIndexTest.php`

**Interfaces:**

- Query canonical: `tu_khoa`, `ma_pb`, `ma_cv`, `ma_tt`, `page`, `so_dong`.
- `NhanVienServiceContract::paginate(array $filters): LengthAwarePaginator` và `lookups(): array`; controller type-hint contract, concrete service implement contract. Task 10/12/14 mở rộng contract atomically với mutation tương ứng. `AppServiceProvider::register()` bind contract → concrete; feature tests mock contract, không mock final concrete.
- Legacy GET `/admin/nhan-vien/danh-sach-nhan-vien` redirect `301` sang `backend.nhanvien.index` và giữ query string.
- Trong checkpoint này chỉ còn canonical GET index và legacy GET redirect; xóa các route prototype create/store/edit/`PUT → show`/destroy để tên route không trùng và không trỏ action sai. Các task 9–14 thêm lại từng route canonical cùng implementation/test của nó.
- `config('nhanvien.enabled')` mặc định `false` cho tới Task 18; mọi route employee web, ba endpoint employee đang nằm trong nhóm nghỉ phép API và GET lookup employee của chấm công, kể cả legacy redirect, qua `EnsureNhanVienModuleEnabled` và trả `404` khi tắt. Feature tests dùng trait để bật tường minh.

- [ ] **Step 1: Viết feature tests với service mock**

```php
$this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
    $mock->shouldReceive('paginate')->once()->with([
        'tu_khoa' => 'NV001',
        'ma_pb' => null,
        'ma_cv' => null,
        'ma_tt' => null,
        'page' => 1,
        'so_dong' => 20,
    ])->andReturn($this->employeePaginator());
    $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
});

$this->get('/admin/nhan-vien?tu_khoa=NV001')
    ->assertOk()
    ->assertViewIs('backend.nhanvien.index')
    ->assertSee('NV001')
    ->assertDontSee('mat_khau');
```

Các test còn lại khóa: module mặc định tắt khiến web index/legacy và GET/POST/PUT employee API đều trả `404` mà không gọi controller/service; trait bật module thì validation query/filter/pagination/empty/server-error/legacy redirect hoạt động. Test inventory route assert `backend.nhanvien.index` là tên duy nhất ở checkpoint này, không còn `backend.nhanvien.show` gắn `PUT`, và các URI mutation web prototype không còn được dispatch.

- [ ] **Step 2: Xác nhận RED**

Run: `php artisan test tests/Feature/Backend/NhanVien/NhanVienIndexTest.php`

- [ ] **Step 3: Cài request, service và route canonical**

`EnsureNhanVienModuleEnabled` chỉ gọi `abort_unless(config('nhanvien.enabled') === true, 404)` trước controller. `config/nhanvien.php` bắt đầu chỉ với `'enabled' => false`; Task 10 thêm avatar prefix khi file flow xuất hiện. Chưa đọc biến enable từ `.env` để một checkout ở commit trung gian không vô tình mở PII/mutation. `InteractsWithEmployeeModule::enableEmployeeModule()` chỉ set config trong test. Gắn middleware này cho GET/POST/PUT employee API prototype của nghỉ phép và GET employee lookup của chấm công ngay ở checkpoint này; Task 10 sẽ xóa hai mutation contract trùng. `ListNhanVienRequest` chuẩn hóa chuỗi rỗng thành `null` và dùng rules. Trong cùng thay đổi web route, xóa năm definition prototype `/them-nhan-vien`, POST collection, GET edit, `PUT /{id}` gọi `show`, DELETE `/{id}`; giữ đúng legacy list redirect rồi khai báo canonical index:

```php
return [
    'tu_khoa' => ['nullable', 'string', 'max:100'],
    'ma_pb' => ['nullable', 'integer', 'min:1'],
    'ma_cv' => ['nullable', 'integer', 'min:1'],
    'ma_tt' => ['nullable', 'integer', 'min:1'],
    'page' => ['sometimes', 'integer', 'min:1'],
    'so_dong' => ['sometimes', 'integer', Rule::in([5, 10, 20, 50, 100])],
];
```

Khai báo route tĩnh trước route động. `index()` truyền `employees`, `lookups`, `filters`; trước khi render gọi `$employees->withPath(route('backend.nhanvien.index'))->appends($filters)` để pagination không trỏ về `/`. Controller bắt `NhanVienDomainException` để hiện thông báo an toàn, không catch rồi in raw exception.

- [ ] **Step 4: Rebuild list Blade bằng dữ liệu thật**

Giữ `@extends('backend.layouts.app')`. Bảng chỉ có avatar, mã, họ tên, liên hệ, phòng ban, chức vụ, trạng thái và actions; xóa cột lương/dữ liệu mẫu. Form filter dùng GET, pagination dùng `appends($filters)`. Page phải có:

- empty database và empty filtered riêng;
- flash success/domain error;
- semantic table/caption, label, focus-visible;
- filter submit có disabled/`aria-busy` progressive state; JS nối state này khi entry được tạo ở Task 11, còn empty/server-error state được feature-test deterministically ở task này;
- chưa render action per-row cho tới khi route show canonical được tạo ở Task 9; tránh gọi một route chưa tồn tại trong commit list độc lập.

Sidebar trỏ đúng `route('backend.nhanvien.index')`, không đổi shell/layout global.

Cho tới Task 11 tạo entry, index dùng markup/CSS runtime sẵn có và không gọi `@vite` tới asset chưa tồn tại. Task 11 sửa lại index/show để push entry đúng một lần cùng create; Task 12 làm tương tự cho edit.

- [ ] **Step 5: Verify route và feature behavior**

```powershell
php artisan test tests/Feature/Backend/NhanVien/NhanVienIndexTest.php
php artisan route:list --path=admin/nhan-vien --except-vendor
rg -n "John Doe|Nguyễn Văn A|salary|luong" resources/views/backend/nhanvien/index.blade.php
```

Expected: tests PASS; canonical GET và redirect cũ đúng; không còn row nhân viên giả/cột lương.

- [ ] **Step 6: Commit và push**

```powershell
git add -- app/Http/Requests/ListNhanVienRequest.php app/Contracts/NhanVienServiceContract.php app/Services/NhanVienService.php app/Providers/AppServiceProvider.php app/Http/Middleware/EnsureNhanVienModuleEnabled.php app/Http/Controllers/Backend/NhanVienController.php config/nhanvien.php routes/web.php routes/api.php resources/views/backend/nhanvien/index.blade.php resources/views/backend/layouts/sidebar.blade.php tests/Support/InteractsWithEmployeeModule.php tests/Feature/Backend/NhanVien/NhanVienIndexTest.php
git diff --cached --check
git commit -m "feat(employee): render canonical employee list"
git push
```

### Task 7: Cross-module employee-code compatibility

**Files:**

- Modify: `app/Http/Controllers/Backend/NghiPhepController.php:91-231`
- Modify: `app/Http/Controllers/Backend/ChamCongController.php:22-160`
- Modify: `app/Contracts/NhanVienServiceContract.php`
- Modify: `app/Services/NhanVienService.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Requests/StoreChamCongRequest.php`
- Modify: `app/Http/Requests/UpdateChamCongRequest.php`
- Modify: `app/Http/Requests/StoreNghiPhepRequest.php`
- Modify: `app/Http/Requests/UpdateNghiPhepRequest.php`
- Modify: `app/Http/Requests/StoreLuongRequest.php`
- Modify: `app/Http/Requests/UpdateLuongRequest.php`
- Modify: `resources/js/frontend/nghiphep/nghiphep.js`
- Create: `resources/js/frontend/nghiphep/employee-response.js`
- Create: `tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php`
- Create: `tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php`
- Create: `tests/Feature/Compatibility/CanonicalEmployeeCodeValidationTest.php`
- Create: `tests/Frontend/nghiphep/employee-response.test.js`

**Interfaces:**

- GET `/api/v1/nghi-phep/nhan-vien` giữ response `{success: true, data: paginator}` và query cũ `per_page`, nhưng data đến từ `NhanVienService`/procedure canonical; trước Task 18 nó vẫn qua rollout middleware fail-closed và chỉ trả dữ liệu khi test bật module tường minh.
- GET `/api/v1/cham-cong/nhan-vien` giữ response chấm công hiện hữu, nhưng gọi `NhanVienServiceContract::paginateForAttendance()` → repository/procedure thật đã được MariaDB-test ở Task 5; controller không gọi `DB::select`. Endpoint qua rollout guard ngay; raw throwable message được thay bằng message cố định. Task 18 mới thêm session auth + XEM cho endpoint này cùng endpoint nghỉ phép.
- Mọi request liên module coi `ma_nv` là chuỗi `NV` cộng đúng ba chữ số.

- [ ] **Step 1: Viết compatibility tests RED**

Mock `NhanVienServiceContract` và assert module mặc định tắt làm cả lookup nghỉ phép/chấm công trả `404` trước controller; sau `enableEmployeeModule()`, GET nghỉ phép map `per_page=15` thành `so_dong=15`, giữ filter cũ, trả mỗi nhân viên đúng một lần và không có hash. `ChamCongEmployeeLookupSecurityTest` khóa mapping `tu_khoa, ma_pb, thang, nam, page, per_page`, response paginator/aggregate compatible khi `paginateForAttendance()` thành công và lỗi bất kỳ chỉ trả `Không thể tải danh sách nhân viên.`, không SQL/exception text. Test này không được dùng mock để thay bằng chứng procedure: `EmployeeReadProcedureTest` Task 5 phải xanh trên MariaDB trong cùng checkpoint. Test dùng SQLite schema tối thiểu tạo inline trong `setUp()` (`nhan_vien.ma_nv` primary string) để `Rule::exists` chạy độc lập; helper schema chung chỉ được trích ở Task 8. Data provider cho sáu FormRequest phải chấp nhận `NV001`, từ chối `1`, `001`, `NV01`, `NV0001`.

```php
'ma_nv' => [
    'required',
    'string',
    'regex:/\ANV[0-9]{3}\z/',
    'max:5',
    Rule::exists('nhan_vien', 'ma_nv'),
],
```

- [ ] **Step 2: Xác nhận RED**

Run: `php artisan test tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php tests/Feature/Compatibility/CanonicalEmployeeCodeValidationTest.php`

- [ ] **Step 3: Chuyển GET caller và bỏ raw SQL/error**

Inject `NhanVienServiceContract` bên cạnh `NghiPhepService`; cả hai `employees()` chỉ validate/map query và gọi service. Mở rộng contract/concrete bằng `paginateForAttendance(array $filters): LengthAwarePaginator`, delegate sang `NhanVienRepositoryContract::paginateAttendance()`. Khi lỗi, trả message cố định `Không thể tải danh sách nhân viên.` với status `500`, không trả exception message. Gắn `EnsureNhanVienModuleEnabled` trực tiếp cho cả GET lookup nghỉ phép và chấm công; xóa raw `DB::select`/CALL ở `ChamCongController::employees()`. Chưa thêm session/auth cho API cho tới Task 18. Chưa xóa POST/PUT employee duplicate ở task này; chúng được xóa atomically với việc replace procedure create/update trong Task 10.

Extract/export pure `normalizeEmployee()` và `extractData()` khỏi `nghiphep.js` sang `resources/js/frontend/nghiphep/employee-response.js`. Vì result list canonical đã duyệt không có `gioi_tinh`, adapter đặt field thiếu thành `null` và `genderLabel(null)` thành `—` thay vì lỗi/hiển thị `undefined`; các field mã, tên, phòng ban, chức vụ, trạng thái vẫn giữ nguyên. Node test đưa paginator `{data:{data:[...]}}` có `NV001` theo đúng shape canonical và assert row nghỉ phép render an toàn. `nghiphep.js` import helper; không đổi UI nghiệp vụ khác.

- [ ] **Step 4: Đồng nhất sáu request**

Thay rule integer/drift bằng contract chuỗi trên, không thay nghiệp vụ khác của chấm công/nghỉ phép/lương. Các request update lấy `ma_nv` từ body theo contract hiện hữu; task này không đổi URI hoặc response của ba module.

- [ ] **Step 5: Verify và commit**

```powershell
php artisan test tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php tests/Feature/Compatibility/CanonicalEmployeeCodeValidationTest.php
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeReadProcedureTest|CanonicalDumpReplayTest'
node --test tests/Frontend/nghiphep/employee-response.test.js
npm run build
php -l app/Http/Controllers/Backend/NghiPhepController.php
rg -n 'vw_danh_sach_nhan_vien_chi_tiet|GROUP BY ma_nv|->getMessage\(' app/Http/Controllers/Backend/NghiPhepController.php app/Http/Controllers/Backend/ChamCongController.php
git add -- app/Contracts/NhanVienServiceContract.php app/Services/NhanVienService.php app/Http/Controllers/Backend/NghiPhepController.php app/Http/Controllers/Backend/ChamCongController.php routes/api.php app/Http/Requests/StoreChamCongRequest.php app/Http/Requests/UpdateChamCongRequest.php app/Http/Requests/StoreNghiPhepRequest.php app/Http/Requests/UpdateNghiPhepRequest.php app/Http/Requests/StoreLuongRequest.php app/Http/Requests/UpdateLuongRequest.php resources/js/frontend/nghiphep/nghiphep.js resources/js/frontend/nghiphep/employee-response.js tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php tests/Feature/Compatibility/CanonicalEmployeeCodeValidationTest.php tests/Frontend/nghiphep/employee-response.test.js
git diff --cached --check
git commit -m "fix(employee): align cross-module employee identifiers"
git push
```

### Task 8: Create and update validation contracts

**Files:**

- Create: `app/Rules/Du18TuoiTaiNgayVaoLam.php`
- Create: `app/Http/Requests/StoreNhanVienRequest.php`
- Create: `app/Http/Requests/UpdateNhanVienRequest.php`
- Create: `tests/Support/CreatesEmployeeFeatureSchema.php`
- Create: `tests/Unit/Rules/Du18TuoiTaiNgayVaoLamTest.php`
- Create: `tests/Feature/Backend/NhanVien/NhanVienValidationTest.php`

**Interfaces:**

- Validated payload dùng đúng tên cột/procedure trong design spec; không nhận `ma_nv`, `ma_vt`, `mat_khau`, `mat_khau_hash` hoặc `ngay_nghi_viec` từ client.
- `anh_dai_dien` là `UploadedFile|null`; email/CCCD/các chuỗi được normalize trước validation.

- [ ] **Step 1: Viết rule và request tests RED**

Khóa boundary đúng 18 tuổi tại ngày vào làm, leap day, invalid date, duplicate email không phân biệt hoa thường, duplicate CCCD sau trim, lookup thiếu, số điện thoại, avatar MIME/size, bốn thành phần địa chỉ và prohibited fields.

```php
$valid = [
    'ho_ten' => 'Nguyễn An',
    'ngay_sinh' => '2000-08-12',
    'gioi_tinh' => 1,
    'sdt' => '0901234567',
    'email' => 'nhanvien@example.test',
    'ngay_vao_lam' => '2026-08-12',
    'ma_pb' => 1,
    'ma_cv' => 1,
    'dan_toc' => 'Kinh',
    'cccd' => '001200000001',
    'noi_cap_cccd' => 'Cục CSQLHC',
    'hoc_van' => 'Đại học',
    'ma_tt' => 1,
    'dia_chi_cu_the' => '1 Nguyễn Trãi',
    'phuong_xa' => 'Bến Thành',
    'quan_huyen' => 'Quận 1',
    'tinh_thanh' => 'TP Hồ Chí Minh',
];
```

- [ ] **Step 2: Xác nhận RED**

Run: `php artisan test tests/Unit/Rules/Du18TuoiTaiNgayVaoLamTest.php tests/Feature/Backend/NhanVien/NhanVienValidationTest.php`

- [ ] **Step 3: Cài normalize và rules**

`prepareForValidation()` trim mọi chuỗi, lowercase email và cast các ID/gender hợp lệ. Không normalize/drop âm thầm field hệ thống: `ma_vt`, `ma_nv`, password/hash và ngày nghỉ phải còn hiện diện đủ để rule `prohibited` bắt crafted payload. Rules chính xác:

- `ho_ten` max 50; `dan_toc`, `noi_cap_cccd`, `hoc_van` max 50;
- `email` RFC-compatible, max 100, unique; update ignore route `ma_nv`;
- `cccd` regex 12 chữ số, unique; `sdt` regex `\A0[0-9]{9}\z`;
- `ngay_sinh`, `ngay_vao_lam` ISO date và custom age rule;
- `gioi_tinh` trong `0,1`; ba foreign key phòng ban/chức vụ/trạng thái `exists` đúng bảng;
- bốn địa chỉ required, max lần lượt `255,100,100,100`;
- avatar `image`, MIME `jpeg,png,webp`, max `2048` KiB;
- các field hệ thống dùng `prohibited`.

Store dùng `Rule::exists('trang_thai_lam_viec', 'ma_tt')->whereNot('ky_hieu', 'DA_NGHI')`; test `DANG_LAM`/`THU_VIEC` pass và `DA_NGHI` fail. Update cho `ma_tt` tồn tại bất kỳ rồi trong `after()` nạp hồ sơ hiện tại qua `NhanVienRepositoryContract::find()` được inject/resolve từ container: employee active không được gửi status `DA_NGHI`, employee đã nghỉ chỉ được giữ nguyên status `DA_NGHI`; procedure là tuyến cuối lặp lại invariant. Update nhận thêm `xoa_anh_dai_dien` dạng `sometimes|boolean`, Store đánh field này `prohibited`, và Update cấm đồng thời upload avatar mới với yêu cầu xóa avatar.

- [ ] **Step 4: Verify và commit**

```powershell
php artisan test tests/Unit/Rules/Du18TuoiTaiNgayVaoLamTest.php tests/Feature/Backend/NhanVien/NhanVienValidationTest.php
php -l app/Http/Requests/StoreNhanVienRequest.php
php -l app/Http/Requests/UpdateNhanVienRequest.php
git add -- app/Rules/Du18TuoiTaiNgayVaoLam.php app/Http/Requests/StoreNhanVienRequest.php app/Http/Requests/UpdateNhanVienRequest.php tests/Support/CreatesEmployeeFeatureSchema.php tests/Unit/Rules/Du18TuoiTaiNgayVaoLamTest.php tests/Feature/Backend/NhanVien/NhanVienValidationTest.php
git diff --cached --check
git commit -m "feat(employee): validate employee profile input"
git push
```

## Checkpoint B — Read path and compatibility

- [ ] List/detail/lookup procedures có result shape cố định và không lộ hash.
- [ ] `/admin/nhan-vien` hiển thị data thật; legacy GET redirect có chủ đích.
- [ ] GET employee của nghỉ phép dùng chung repository, không raw SQL/view.
- [ ] Sáu request liên module thống nhất `ma_nv` dạng `NVxxx`.
- [ ] Chưa replace procedure mutation cũ trước khi caller POST/PUT trùng lặp được xử lý.

---

## Phase 3 — Create, detail, and update

### Task 9: Employee detail page and canonical show route

**Files:**

- Modify: `app/Http/Controllers/Backend/NhanVienController.php`
- Modify: `app/Contracts/NhanVienServiceContract.php`
- Modify: `app/Services/NhanVienService.php`
- Modify: `routes/web.php`
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Create: `resources/views/backend/nhanvien/show.blade.php`
- Create: `tests/Feature/Backend/NhanVien/NhanVienShowTest.php`

**Interfaces:**

- `NhanVienService::findOrFail(string $maNv): object` dùng repository; not-found thành HTTP `404` an toàn.
- `GET /admin/nhan-vien/{ma_nv}` tên `backend.nhanvien.show`, constraint `NV[0-9]{3}`; route đứng sau route tĩnh `/create`.

- [ ] **Step 1: Viết feature tests RED**

Test mã hợp lệ trả detail đầy đủ + address/avatar/status; không tồn tại trả 404; mã sai format không match route; response không chứa hash. Task này thêm action Xem canonical vào list. Link từ list gắn sáu query key đã whitelist `tu_khoa`, `ma_pb`, `ma_cv`, `ma_tt`, `page`, `so_dong`; trang detail dựng link quay lại bằng `route('backend.nhanvien.index', request()->only([...]))`, không nhận URL redirect tùy ý.

- [ ] **Step 2: Xác nhận RED**

Run: `php artisan test tests/Feature/Backend/NhanVien/NhanVienShowTest.php`

- [ ] **Step 3: Cài service/controller/route/view**

View dùng `<dl>` theo nhóm cá nhân, liên hệ, địa chỉ, công việc/tài khoản; avatar có alt theo tên nhân viên và fallback initials. Không hiển thị password/hash. Action edit/delete/reset chưa có route thì chưa render; task sở hữu từng route sẽ thêm action sau. Commit này chỉ dùng markup và asset runtime hiện có; chưa gọi entry Vite nhân viên chưa được tạo. Task 11 sẽ thêm entry và directive cho `index/create/show` trong cùng commit.

- [ ] **Step 4: Verify, commit và push**

```powershell
php artisan test tests/Feature/Backend/NhanVien/NhanVienShowTest.php tests/Feature/Backend/NhanVien/NhanVienIndexTest.php
php artisan route:list --path=admin/nhan-vien --except-vendor
rg -n "mat_khau" resources/views/backend/nhanvien/show.blade.php
git add -- app/Http/Controllers/Backend/NhanVienController.php app/Contracts/NhanVienServiceContract.php app/Services/NhanVienService.php routes/web.php resources/views/backend/nhanvien/index.blade.php resources/views/backend/nhanvien/show.blade.php tests/Feature/Backend/NhanVien/NhanVienShowTest.php
git diff --cached --check
git commit -m "feat(employee): show employee profile details"
git push
```

### Task 10: Atomic employee creation and legacy mutation removal

**Files:**

- Create: `database/sql/employee/2026_08_12_003_create_routines.sql`
- Modify: `database/sql/employee/README.md`
- Modify: `quan_ly_nhan_su.session.sql:1031-1210`
- Modify: `app/Contracts/NhanVienRepositoryContract.php`
- Modify: `app/Repositories/NhanVienRepository.php`
- Modify: `app/Contracts/NhanVienServiceContract.php`
- Modify: `app/Services/NhanVienService.php`
- Modify: `app/Http/Controllers/Backend/NhanVienController.php`
- Modify: `app/Http/Controllers/Backend/NghiPhepController.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php:64-78`
- Modify: `config/nhanvien.php`
- Create: `app/Support/NhanVienAvatarPath.php`
- Create: `tests/Support/MariaDbEmployeeCreateWorker.php`
- Create: `tests/Integration/MariaDb/EmployeeCreateProcedureTest.php`
- Create: `tests/Integration/MariaDb/EmployeeCreateConcurrencyTest.php`
- Create: `tests/Unit/Services/NhanVienServiceCreateTest.php`
- Create: `tests/Unit/Support/NhanVienAvatarPathTest.php`
- Create: `tests/Unit/Services/NhanVienServiceBoundaryTest.php`
- Create: `tests/Feature/Backend/NhanVien/NhanVienStoreTest.php`
- Modify: `tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php`
- Modify: `tests/Integration/MariaDb/CanonicalDumpReplayTest.php`

**Interfaces:**

- `NhanVienRepository::create(array $profile, string $passwordHash, ?string $avatarPath): string`
- `NhanVienRepository::upsertAddress(string $maNv, array $address): void`
- Concrete `NhanVienService` inject `DatabaseManager $database`, `NhanVienRepositoryContract $employees`, `FilesystemManager $files`, `Hasher $hasher`; mọi transaction dùng `$this->database->connection()` là cùng default connection repository đang dùng.
- `NhanVienService::create(array $validated): string` trả duy nhất `ma_nv`; plaintext/hash không đi qua return value/flash/view data.
- `config('nhanvien.avatar_prefix')` mặc định `nhan-vien/avatars`; prefix phải là relative path gồm các segment an toàn, không rỗng/absolute/`..`.
- `NhanVienAvatarPath::newPath(string $extension): string`, `newTemporaryPath(string $extension): string`, `assertOwnedFile(?string $path): ?string`, `assertOwnedTemporaryFile(?string $path): ?string`; mọi filesystem delete chỉ dùng path đã qua đúng helper.
- Procedure `sp_nhan_vien_them` có đúng 15 IN + 1 OUT theo đặc tả, không nhận `ma_vt`; `sp_dia_chi_nhan_vien_luu` có 5 IN.

- [ ] **Step 1: Viết procedure tests RED**

`EmployeeCreateProcedureTest` khóa các hành vi:

- cấp `NV001`, `NV002`; rollback giao dịch chứa `NV003` rồi lần gọi sau vẫn nhận `NV003`;
- counter `999` trả `NV_CODE_EXHAUSTED` và không insert;
- email/CCCD normalize và unique; lookup thiếu, dưới 18 tuổi, status `DA_NGHI` đều fail;
- role `NHAN_VIEN_MAC_DINH` thiếu/mơ hồ/có bất kỳ permission mapping nào đều fail closed; row tạo thành công luôn mang đúng role đó;
- password lưu đúng chuỗi hash đã truyền, procedure không tự hash;
- address upsert insert rồi update đúng một row;
- procedure không chứa `START TRANSACTION`, `COMMIT` hoặc `ROLLBACK`.

Concurrency test dùng hai `Symfony\Component\Process\Process` chạy `tests/Support/MariaDbEmployeeCreateWorker.php`. Parent truyền `MARIADB_TEST_ENABLED=1`, credential và `MARIADB_TEST_DATABASE=$this->databaseName` riêng vào từng child. Worker bắt buộc đủ biến, gọi `DisposableMariaDbGuard::assertSafeDatabaseName()` trước khi mở PDO và tuyệt đối không đọc/fallback `DB_*`; test riêng truyền production/missing target và assert worker dừng trước connection. Hai worker chờ cùng barrier file, mở transaction riêng và gọi procedure cùng lúc. Assert hai mã khác nhau/liên tiếp. Chạy hai race độc lập: cùng email khác hoa/thường và cùng CCCD có khoảng trắng; ở mỗi race đúng một worker commit, worker còn lại nhận lỗi field duplicate an toàn và không tồn tại hai giá trị normalize giống nhau.

- [ ] **Step 2: Xác nhận RED**

Run: `pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeCreateProcedureTest|EmployeeCreateConcurrencyTest'`

Expected: FAIL vì mutation procedure canonical chưa tồn tại.

- [ ] **Step 3: Viết script 003 và đồng bộ dump**

Trước khi drop procedure cũ, chạy lại inventory:

```powershell
rg -n "sp_nhan_vien_(them|sua)\b|storeEmployee|updateEmployee" app routes resources tests docs quan_ly_nhan_su.session.sql
```

`sp_nhan_vien_them` phải `SELECT ... FOR UPDATE` row `NHAN_VIEN`, kiểm tra `so_da_cap < 999`, tính `CONCAT('NV', LPAD(..., 3, '0'))`, normalize, kiểm tra invariant/reference/status, rồi `SELECT ma_vt ... WHERE ky_hieu='NHAN_VIEN_MAC_DINH' FOR UPDATE` và assert đúng một role/zero mapping trước insert. Procedure không nhận role client, chỉ nhận hash Laravel. `sp_dia_chi_nhan_vien_luu` trim bốn thành phần và dùng `INSERT ... ON DUPLICATE KEY UPDATE`. Cả hai dùng mã lỗi `NV_*`, không điều khiển transaction. Task 16 bắt mọi procedure thêm mapping phải khóa cùng role row trước khi từ chối baseline, nên invariant không có race khi rollout được bật.

- [ ] **Step 4: Cài repository và service transaction/file compensation**

Repository dùng cùng `Connection` cho `SET @nv_ma = NULL`, `CALL` 15 IN không role, `SELECT @nv_ma`; nếu OUT không khớp `NV[0-9]{3}` thì ném lỗi miền. Service whitelist profile, không chuyển `ma_vt` xuống repository, và tạo mật khẩu theo clock/timezone đã chốt:

```php
$plainPassword = 'nhom3@'.now(config('app.timezone'))->year;
$passwordHash = $this->hasher->make($plainPassword);

return $this->database->connection()->transaction(function () use (
    $profile, $address, $passwordHash, $finalAvatarPath
): string {
    $maNv = $this->employees->create(
        $profile,
        $passwordHash,
        $finalAvatarPath,
    );
    $this->employees->upsertAddress($maNv, $address);

    return $maNv;
});
```

`$plainPassword` chỉ tồn tại trong scope method đủ để hash rồi `unset($plainPassword)` trước transaction; không đưa vào array/DTO/log. `config/nhanvien.php` đọc `EMPLOYEE_AVATAR_PREFIX` với default `nhan-vien/avatars`. `NhanVienAvatarPath` chuẩn hóa và từ chối prefix rỗng, absolute, có `.`/`..`, backslash hoặc segment ngoài `[A-Za-z0-9_-]+`; final file chỉ hợp lệ trực tiếp dưới exact prefix, temp file chỉ hợp lệ trực tiếp dưới exact `{prefix}/tmp`, cả hai bắt buộc basename UUID + extension allowlist `jpg|jpeg|png|webp`. Avatar dùng `newTemporaryPath()` rồi move tới `newPath()`. Unit tests khóa prefix/path traversal, absolute, wrong-prefix, nested ngoài đúng `tmp`, symlink-shaped, invalid UUID/extension và cleanup temp khi DB/move fail. Blade tạo URL qua `Storage::disk('public')->url($relativePath)`, không nối filesystem path. Mọi exception sau upload chỉ xóa temp qua `assertOwnedTemporaryFile()` và final qua `assertOwnedFile()`; không log payload/hash/plaintext.

- [ ] **Step 5: Nối web store và loại bỏ mutation endpoint trùng**

Khai báo/khóa `POST /admin/nhan-vien` tên `backend.nhanvien.store` tới đúng action. `NhanVienController::store(StoreNhanVienRequest $request)` gọi service, redirect tới route show đã có từ Task 9 và flash đúng câu `Đã tạo nhân viên; có thể bổ sung hợp đồng sau.` kèm mã vừa cấp + câu tĩnh `Tài khoản dùng quy ước mật khẩu demo nhom3@{năm tạo}.`; không có CTA hợp đồng và không flash/serialize giá trị plaintext do service trả. Feature test assert nguyên văn câu handoff. Domain error quay lại form với old input và field error đã map.

Trong cùng commit, xóa `NghiPhepController::storeEmployee`, `updateEmployee` và POST/PUT/PATCH `/api/v1/nghi-phep/nhan-vien`; giữ GET employee lookup. Compatibility test assert POST cùng URI trả `405`, PUT/PATCH `.../NV001` trả `404`, GET vẫn `200`. Không để wrapper chữ ký cũ.

`NhanVienServiceBoundaryTest` dùng reflection/source inventory để assert constructor của controller/service/repository không phụ thuộc class namespace hợp đồng, create không nhận contract payload, và test container vẫn resolve service khi không bind controller/service/repository hợp đồng.

- [ ] **Step 6: Verify SQL, service, route và rollback**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeCreateProcedureTest|EmployeeCreateConcurrencyTest|CanonicalDumpReplayTest'
php artisan test tests/Unit/Services/NhanVienServiceCreateTest.php tests/Unit/Services/NhanVienServiceBoundaryTest.php tests/Feature/Backend/NhanVien/NhanVienStoreTest.php tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php
php artisan route:list --path=api/v1/nghi-phep/nhan-vien --except-vendor
rg -n "sp_nhan_vien_(them|sua)\b|storeEmployee|updateEmployee" app routes resources tests
```

Expected: targeted tests PASS; inventory chỉ còn repository create call và test evidence, không còn caller chữ ký 16 tham số cũ.

- [ ] **Step 7: Commit và push**

```powershell
git add -- database/sql/employee/2026_08_12_003_create_routines.sql database/sql/employee/README.md quan_ly_nhan_su.session.sql config/nhanvien.php app/Support/NhanVienAvatarPath.php app/Contracts/NhanVienRepositoryContract.php app/Repositories/NhanVienRepository.php app/Contracts/NhanVienServiceContract.php app/Services/NhanVienService.php app/Http/Controllers/Backend/NhanVienController.php app/Http/Controllers/Backend/NghiPhepController.php routes/web.php routes/api.php tests/Support/MariaDbEmployeeCreateWorker.php tests/Integration/MariaDb/CanonicalDumpReplayTest.php tests/Integration/MariaDb/EmployeeCreateProcedureTest.php tests/Integration/MariaDb/EmployeeCreateConcurrencyTest.php tests/Unit/Support/NhanVienAvatarPathTest.php tests/Unit/Services/NhanVienServiceCreateTest.php tests/Unit/Services/NhanVienServiceBoundaryTest.php tests/Feature/Backend/NhanVien/NhanVienStoreTest.php tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php
git diff --cached --check
git commit -m "feat(employee): create employee atomically with account"
git push
```

### Task 11: Accessible three-step create wizard

**Files:**

- Modify: `app/Http/Controllers/Backend/NhanVienController.php`
- Modify: `app/Services/NhanVienService.php`
- Modify: `routes/web.php`
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/nhanvien/show.blade.php`
- Rewrite: `resources/views/backend/nhanvien/create.blade.php`
- Create: `resources/views/backend/nhanvien/partials/flash.blade.php`
- Create: `resources/views/backend/nhanvien/partials/personal-fields.blade.php`
- Create: `resources/views/backend/nhanvien/partials/address-fields.blade.php`
- Create: `resources/views/backend/nhanvien/partials/employment-fields.blade.php`
- Create: `resources/css/nhanvien/nhanvien.css`
- Create: `resources/js/frontend/nhanvien/nhanvien.js`
- Create: `resources/js/frontend/nhanvien/wizard-state.js`
- Create: `resources/js/frontend/nhanvien/wizard.js`
- Modify: `vite.config.js:6-15`
- Modify: `package.json:5-9`
- Create: `tests/Frontend/nhanvien/wizard-state.test.js`
- Create: `tests/Feature/Backend/NhanVien/NhanVienCreatePageTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienIndexTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienShowTest.php`

**Interfaces:**

- Form `POST` tới `backend.nhanvien.store`, `multipart/form-data`, có CSRF, không có ô `ma_nv` hoặc password.
- `GET /admin/nhan-vien/create` tên `backend.nhanvien.create`; legacy GET `/admin/nhan-vien/them-nhan-vien` redirect `301` và không nhận mã như route động.
- `wizard-state.js` export pure functions `firstInvalidStep(fieldNames)`, `nextStep(current)`, `previousStep(current)` cho Node test.
- Vite entry canonical: `resources/js/frontend/nhanvien/nhanvien.js`; CSS import từ entry, không thêm asset strategy khác.

- [ ] **Step 1: Viết feature và Node tests RED**

Feature test mock `lookups()` rồi assert page có ba bước, đúng field, thông báo mã tự cấp/mật khẩu `nhom3@2026`/role mặc định, missing-master warning, submit disabled khi lookup bắt buộc rỗng, và tuyệt đối không có field password/`ma_vt`/contract/salary.

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { firstInvalidStep } from '../../../resources/js/frontend/nhanvien/wizard-state.js';

test('opens the step containing the first invalid field', () => {
    assert.equal(firstInvalidStep(['email', 'ma_pb']), 1);
    assert.equal(firstInvalidStep(['ma_pb']), 2);
    assert.equal(firstInvalidStep([]), 3);
});
```

- [ ] **Step 2: Xác nhận RED**

```powershell
php artisan test tests/Feature/Backend/NhanVien/NhanVienCreatePageTest.php
node --test tests/Frontend/nhanvien/wizard-state.test.js
```

- [ ] **Step 3: Cài canonical create GET và lookup state**

Route `/create` được khai báo trước `/{ma_nv}`. `NhanVienController::create()` gọi `NhanVienService::lookups()`, tính chính xác `$missingLookups` từ ba key phòng ban/chức vụ/trạng thái và `$firstErrorStep`, rồi truyền view; lookup read failure render thông báo an toàn và khóa submit. Role mặc định không phải lookup client: nếu invariant này hỏng, submit gọi create procedure sẽ fail closed, giữ old input và hiện lỗi server an toàn như Task 10 đã test. Legacy GET redirect có route test. Không query trực tiếp master table trong controller.

- [ ] **Step 4: Rebuild Blade và partials**

Giữ layout runtime `backend.layouts.app`. Step 1 chứa hồ sơ/liên hệ/địa chỉ/ảnh; step 2 chứa ngày vào làm, ba lookup và thông báo role mặc định zero quyền; step 3 là review bằng `<dl>`. Không render/select/hidden `ma_vt`. `DA_NGHI` bị loại khỏi create select. Khi có validation error, server render `data-initial-step` theo field đầu tiên và `<div role="alert">`; old input được giữ. Khi thiếu từng lookup, liệt kê tên master data bị thiếu và disable save.

- [ ] **Step 5: Cài progressive enhancement và chống double submit**

JS chỉ ẩn/hiện step sau khi gắn class enhanced; khi JS tắt, toàn bộ field vẫn đọc/gửi được. Next validate field hiện tại bằng Constraint Validation API. Submit đầu tiên đặt `aria-busy=true`, disable nút và đổi text; submit thứ hai bị chặn. Back/forward giữ focus ở heading/field lỗi. CSS chỉ nằm dưới `.employee-page`, có focus ring, reduced motion và breakpoint mobile/tablet.

Mỗi page đã tồn tại ở checkpoint này (`index/create/show`) có đúng một directive ở cuối view:

```blade
@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
```

Entry tự import `resources/css/nhanvien/nhanvien.css`; không push CSS lần hai. Feature tests index/create/show cấu hình fake manifest và assert asset nhân viên xuất hiện đúng một lần. Task 12 sẽ thêm directive/test tương ứng khi tạo `edit`. Thêm vào Vite input và package scripts:

```json
"test:frontend": "node --test tests/Frontend/nghiphep/employee-response.test.js tests/Frontend/nhanvien/wizard-state.test.js"
```

Ở Task 11, `confirm-actions.test.js` chưa tồn tại nên package script ban đầu liệt kê hai file hiện hữu `employee-response.test.js` và `wizard-state.test.js`; Task 14 sửa script để thêm file thứ ba trong cùng commit. Không dùng glob `**` vì Node 24 trên Windows có thể exit `0` với `0 tests`. Mỗi verify assert reporter có số test lớn hơn `0` và đúng tên suite dự kiến; zero-test là failure.

- [ ] **Step 6: Verify UI source, tests và build**

```powershell
php artisan test tests/Feature/Backend/NhanVien/NhanVienCreatePageTest.php tests/Feature/Backend/NhanVien/NhanVienStoreTest.php tests/Feature/Backend/NhanVien/NhanVienIndexTest.php tests/Feature/Backend/NhanVien/NhanVienShowTest.php
npm run test:frontend
npm run build
rg -n "ma_nv|mat_khau|hop_dong|luong_co_ban" resources/views/backend/nhanvien/create.blade.php resources/views/backend/nhanvien/partials
```

Expected: tests/build PASS; grep chỉ thấy nội dung giải thích mã/mật khẩu mặc định, không có input hệ thống/hợp đồng/lương.

- [ ] **Step 7: Commit và push**

```powershell
git add -- app/Http/Controllers/Backend/NhanVienController.php app/Services/NhanVienService.php routes/web.php resources/views/backend/nhanvien/index.blade.php resources/views/backend/nhanvien/show.blade.php resources/views/backend/nhanvien/create.blade.php resources/views/backend/nhanvien/partials/flash.blade.php resources/views/backend/nhanvien/partials/personal-fields.blade.php resources/views/backend/nhanvien/partials/address-fields.blade.php resources/views/backend/nhanvien/partials/employment-fields.blade.php resources/css/nhanvien/nhanvien.css resources/js/frontend/nhanvien/nhanvien.js resources/js/frontend/nhanvien/wizard-state.js resources/js/frontend/nhanvien/wizard.js vite.config.js package.json tests/Frontend/nhanvien/wizard-state.test.js tests/Feature/Backend/NhanVien/NhanVienCreatePageTest.php tests/Feature/Backend/NhanVien/NhanVienIndexTest.php tests/Feature/Backend/NhanVien/NhanVienShowTest.php
git diff --cached --check
git commit -m "feat(employee-ui): add accessible employee wizard"
git push
```

### Task 12: Atomic profile, address, and avatar update

**Files:**

- Create: `database/sql/employee/2026_08_12_004_update_routines.sql`
- Modify: `database/sql/employee/README.md`
- Modify: `quan_ly_nhan_su.session.sql:1031-1260`
- Modify: `app/Contracts/NhanVienRepositoryContract.php`
- Modify: `app/Repositories/NhanVienRepository.php`
- Modify: `app/Contracts/NhanVienServiceContract.php`
- Modify: `app/Services/NhanVienService.php`
- Create: `app/Support/NhanVienTargetGuard.php`
- Modify: `app/Http/Controllers/Backend/NhanVienController.php`
- Modify: `routes/web.php`
- Create: `resources/views/backend/nhanvien/edit.blade.php`
- Modify: `tests/Integration/MariaDb/CanonicalDumpReplayTest.php`
- Create: `tests/Integration/MariaDb/EmployeeUpdateProcedureTest.php`
- Create: `tests/Unit/Services/NhanVienServiceAvatarTest.php`
- Create: `tests/Unit/Support/NhanVienTargetGuardTest.php`
- Create: `tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php`

**Interfaces:**

- `NhanVienRepository::update(string $maNv, array $profile): void`
- `NhanVienRepository::replaceAvatarPath(string $maNv, ?string $newPath): ?string`
- `NhanVienService::update(string $maNv, array $validated): object`
- `NhanVienTargetGuard::assertManageable(object $employee): void` chỉ chấp nhận `ky_hieu_vai_tro === 'NHAN_VIEN_MAC_DINH'`, ngược lại ném `AuthorizationException` không kèm dữ liệu target.
- `PUT|PATCH /admin/nhan-vien/{ma_nv}` tên `backend.nhanvien.update`; `GET .../{ma_nv}/edit` tên `backend.nhanvien.edit`.

- [ ] **Step 1: Viết procedure và service tests RED**

Procedure tests khóa: sửa mọi field được phép không đổi `ma_nv`/`ma_vt`/`mat_khau`; crafted `ma_vt` bị FormRequest từ chối; target role khác `NHAN_VIEN_MAC_DINH` trả `NV_PRIVILEGED_TARGET` trước mutation; giữ avatar nếu không gọi procedure avatar; address upsert; not-found; duplicate email/CCCD; cấm active → `DA_NGHI`; cấm `DA_NGHI` → active; nhân viên mặc định đã nghỉ giữ status/ngày nghỉ cũ khi sửa hồ sơ. Guard unit/feature test khóa edit/update privileged target trả `403`, không gọi mutation service và không lộ role/email. Two-procedure concurrency `update ↔ lifecycle` được đặt ở Task 13, sau khi lifecycle procedure canonical tồn tại.

Avatar service tests dùng `Storage::fake('public')`:

- DB fail sau upload: file mới bị xóa, file cũ còn;
- commit thành công: file mới còn, file cũ bị xóa sau commit;
- không upload: không gọi replace path;
- xóa avatar có chủ đích: DB path thành null, file cũ xóa sau commit;
- path cũ absolute/traversal/wrong-prefix/không UUID: DB vẫn cập nhật nhưng không xóa file đó; chỉ log event code/`ma_nv`/reason code an toàn;
- không log filename gốc/payload/hash.

- [ ] **Step 2: Xác nhận RED**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeUpdateProcedureTest|CanonicalDumpReplayTest'
php artisan test tests/Unit/Services/NhanVienServiceAvatarTest.php tests/Unit/Support/NhanVienTargetGuardTest.php tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php
```

- [ ] **Step 3: Viết script 004 và repository calls**

Replace `sp_nhan_vien_sua` bằng 14 IN đúng thứ tự đặc tả, không `ma_vt`; câu đầu khóa employee và đọc `ma_tt, ngay_nghi_viec, ma_vt ... FOR UPDATE`, join/verify role symbol đúng `NHAN_VIEN_MAC_DINH`, sau đó mới validate chuyển trạng thái/update; không nhận/đụng role, hash, avatar, mã hoặc ngày nghỉ. Tạo `sp_nhan_vien_cap_nhat_anh(p_ma_nv, p_anh_moi, OUT p_anh_cu)`, khóa row `FOR UPDATE`, chỉ chấp nhận target role mặc định, trả path cũ, cập nhật đúng một cột. Không procedure nào tự điều khiển transaction.

- [ ] **Step 4: Cài service bù trừ và edit UI**

Flow có avatar mới:

1. lưu temp/move path mới;
2. transaction `update` → `upsertAddress` → `replaceAvatarPath`;
3. rollback: xóa path mới, giữ path cũ;
4. commit: chỉ xóa path cũ nếu khác path mới **và** `NhanVienAvatarPath::assertOwnedFile()` xác nhận thuộc exact configured prefix + UUID/extension; path legacy/compromised không hợp lệ không được đưa vào filesystem delete.

Edit page tái sử dụng partials, không có mã/password/`ma_vt`/ngày nghỉ input. Role chỉ hiển thị read-only; nếu target không phải `NHAN_VIEN_MAC_DINH`, controller không render form mà trả `403` an toàn. Với nhân viên active, không render `DA_NGHI`; với nhân viên đã nghỉ, status là read-only hidden canonical value để giữ nguyên và giải thích không thể tái kích hoạt trong scope. Có checkbox `xoa_anh_dai_dien`; chọn file mới sẽ bỏ checkbox và ngược lại. Validation error mở đúng bước và giữ preview avatar cũ.

`edit` push đúng một lần entry Vite nhân viên đã được tạo ở Task 11. `NhanVienUpdateTest` cấu hình fake manifest và assert directive render đúng một asset entry, không push CSS riêng.

- [ ] **Step 5: Verify SQL, feature behavior, filesystem and build**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeUpdateProcedureTest|CanonicalDumpReplayTest'
php artisan test tests/Unit/Services/NhanVienServiceAvatarTest.php tests/Unit/Support/NhanVienTargetGuardTest.php tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php
npm run test:frontend
npm run build
rg -n "mat_khau|START TRANSACTION|COMMIT|ROLLBACK" database/sql/employee/2026_08_12_004_update_routines.sql resources/views/backend/nhanvien/edit.blade.php
```

Expected: targeted tests/build PASS; update script không điều khiển transaction/đụng password.

- [ ] **Step 6: Commit và push**

```powershell
git add -- database/sql/employee/2026_08_12_004_update_routines.sql database/sql/employee/README.md quan_ly_nhan_su.session.sql app/Contracts/NhanVienRepositoryContract.php app/Repositories/NhanVienRepository.php app/Contracts/NhanVienServiceContract.php app/Services/NhanVienService.php app/Support/NhanVienTargetGuard.php app/Http/Controllers/Backend/NhanVienController.php routes/web.php resources/views/backend/nhanvien/edit.blade.php tests/Integration/MariaDb/CanonicalDumpReplayTest.php tests/Integration/MariaDb/EmployeeUpdateProcedureTest.php tests/Unit/Services/NhanVienServiceAvatarTest.php tests/Unit/Support/NhanVienTargetGuardTest.php tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php
git diff --cached --check
git commit -m "feat(employee): update employee profile atomically"
git push
```

## Checkpoint C — Core profile CRUD

- [ ] Hai create đồng thời không trùng mã; duplicate race chỉ có một commit.
- [ ] Mã rollback được dùng lại; mã đã commit không bị lùi.
- [ ] Create lưu account hash, địa chỉ và avatar trong flow bù trừ có test.
- [ ] Endpoint mutation nhân viên trùng trong nghỉ phép đã bị loại bỏ; GET vẫn tương thích.
- [ ] Wizard/detail/edit không có contract hoặc lương, không có password input/hash output.
- [ ] Update không reset hash và không đi vòng lifecycle `DA_NGHI`.

---

## Phase 4 — Lifecycle, authentication, and authorization

### Task 13: Lifecycle and authentication database contracts

**Files:**

- Create: `database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql`
- Modify: `database/sql/employee/README.md`
- Modify: `quan_ly_nhan_su.session.sql:1031-1320`
- Modify: `app/Contracts/NhanVienRepositoryContract.php`
- Modify: `app/Repositories/NhanVienRepository.php`
- Create: `tests/Support/MariaDbEmployeeLifecycleWorker.php`
- Create: `tests/Support/EmployeeDependencyFixture.php`
- Create: `tests/Support/PrepareEmployeeAcceptanceDependency.php`
- Modify: `tests/Integration/MariaDb/CanonicalDumpReplayTest.php`
- Create: `tests/Integration/MariaDb/EmployeeLifecycleProcedureTest.php`
- Create: `tests/Integration/MariaDb/EmployeeLifecycleConcurrencyTest.php`
- Create: `tests/Integration/MariaDb/EmployeeAuthProcedureTest.php`

**Interfaces:**

- `NhanVienRepository::removeOrTerminate(string $maNv, CarbonImmutable $date): array{action: NhanVienRemovalAction, avatar_path: ?string}`
- `NhanVienRepository::resetPasswordHash(string $maNv, string $hash): void` gọi guarded web-reset procedure.
- `NhanVienRepository::rehashAuthenticatedPassword(string $maNv, string $currentHash, string $newHash): void` gọi internal compare-and-swap auth procedure cho mọi role.
- `NhanVienRepository::findAccountByIdentifier(string $identifier): ?NhanVien`

- [ ] **Step 1: Viết lifecycle/auth procedure tests RED**

`EmployeeLifecycleProcedureTest` dùng `EmployeeDependencyFixture` để tạo riêng từng dependency `hop_dong`, `cham_cong`, `nghi_phep`, `luong`, `lich_su_he_so_luong` và khóa:

- không dependency → `DELETED`, row/address mất, avatar OUT đúng;
- mỗi loại dependency → `TERMINATED`, status tìm bằng `DA_NGHI`, ngày nghỉ đúng input;
- gọi lại sau khi đã nghỉ và dọn dependency vẫn `TERMINATED`, giữ ngày nghỉ đầu, không hard-delete;
- hard-delete `NV001` rồi create tiếp nhận `NV002`, không tái dùng mã đã commit;
- not-found, missing `DA_NGHI`, date trước ngày vào làm đều thành mã lỗi miền;
- target role khác `NHAN_VIEN_MAC_DINH` làm lifecycle/reset trả `NV_PRIVILEGED_TARGET` trước mutation;
- reset trên target mặc định chỉ đổi `mat_khau` thành hash đã truyền.

`EmployeeAuthProcedureTest` còn khóa `sp_nhan_vien_cap_nhat_hash_xac_thuc` trên cả baseline/admin role: current hash đúng thì đổi đúng một row; current hash stale/not-found thì fail không mutation; procedure không trả hash/plaintext và rollback hoạt động.

`EmployeeLifecycleConcurrencyTest` dùng hai process/connection qua `MariaDbEmployeeLifecycleWorker.php`. Như create worker, mỗi child bắt buộc `MARIADB_TEST_ENABLED=1`, credential và `MARIADB_TEST_DATABASE`, guard target trước PDO, không fallback `DB_*`; test khóa missing/production target fail trước connection. Update giữ row lock thì lifecycle phải chờ rồi commit thành `DA_NGHI`; lifecycle giữ lock trước thì update chờ rồi fail invariant. Final state không bao giờ bị tái kích hoạt hoặc mất ngày nghỉ.

`EmployeeAuthProcedureTest` khóa lookup chính xác bằng `NV001`, `nv001`, email có khoảng trắng/chữ hoa, identifier không tồn tại, và result gồm đúng sáu cột server-only. Procedure vẫn trả status `DA_NGHI`; custom provider ở Task 15 là tầng từ chối đăng nhập/session.

Trước RED/GREEN, inventory `rg -n "sp_nhan_vien_(xoa|dang_nhap|xoa_hoac_nghi_viec|lay_tai_khoan_dang_nhap)" app routes resources tests docs quan_ly_nhan_su.session.sql`. Sau script, test `information_schema.ROUTINES` assert `sp_nhan_vien_xoa` và `sp_nhan_vien_dang_nhap` không còn; chỉ hai contract canonical tồn tại.

`PrepareEmployeeAcceptanceDependency.php` là CLI test-only dùng lại fixture. Nó đọc credential từ `MARIADB_TEST_*`, bắt buộc target qua biến riêng `MARIADB_TEST_DATABASE`, và đọc `EMPLOYEE_ACCEPTANCE_MA_NV`, `EMPLOYEE_ACCEPTANCE_DEPENDENCY`; target luôn qua guard Task 1 và tuyệt đối không fallback sang `DB_DATABASE`. Unit/integration test assert script từ chối thiếu target, tên DB chính, mã sai format và dependency ngoài whitelist.

- [ ] **Step 2: Xác nhận RED**

Run: `pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeLifecycleProcedureTest|EmployeeLifecycleConcurrencyTest|EmployeeAuthProcedureTest'`

- [ ] **Step 3: Viết script 005**

`sp_nhan_vien_xoa_hoac_nghi_viec` khóa nhân viên, kiểm tra target role exact `NHAN_VIEN_MAC_DINH`, kiểm tra đủ năm bảng phụ thuộc bằng `EXISTS`, resolve `DA_NGHI` qua `ky_hieu`, trả hai OUT `p_hanh_dong`, `p_anh_cu`, và không tự transaction. Nhân viên mặc định đã nghỉ được xử lý trước nhánh dependency/hard-delete để bảo đảm idempotence.

Script drop dứt điểm `sp_nhan_vien_xoa`, `sp_nhan_vien_dang_nhap` cũ rồi create canonical routines. `sp_nhan_vien_dat_lai_mat_khau` nhận `ma_nv` + hash, khóa row, từ chối target không phải role mặc định rồi update đúng một row. Tách `sp_nhan_vien_cap_nhat_hash_xac_thuc(ma_nv, hash_hien_tai, hash_moi)` làm compare-and-swap: khóa mọi role, update chỉ khi hash hiện tại exact, stale/not-found trả mã lỗi chung; không expose HTTP. `sp_nhan_vien_lay_tai_khoan_dang_nhap` normalize identifier, select tường minh `ma_nv, ho_ten, email, mat_khau, ma_vt, ky_hieu`, tối đa một row. Không routine nào log/return plaintext.

- [ ] **Step 4: Cài bốn repository method qua helper `call()`**

`removeOrTerminate()` dùng một `databaseOperation()` bao cùng write connection cho `SET @nv_hanh_dong`, `SET @nv_anh_cu`, `CALL`, rồi hai OUT reads với `useReadPdo=false`; validate action bằng `NhanVienRemovalAction::tryFrom()` và map action lạ thành domain error chung. Test inject lỗi riêng ở SET/CALL/OUT và assert đều qua mapper. `resetPasswordHash()`, `rehashAuthenticatedPassword()` và `findAccountByIdentifier()` cũng bọc toàn bộ DB sequence; auth lookup hydrate duy nhất qua `NhanVien::fromAuthProcedureRow()` và trả `null` nếu result rỗng. Source-boundary test assert compare-and-swap method/procedure chỉ được provider/repository/test gọi, không controller/route/service web.

- [ ] **Step 5: Verify procedure, repository và clean dump**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeLifecycleProcedureTest|EmployeeLifecycleConcurrencyTest|EmployeeAuthProcedureTest'
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'CanonicalDumpReplayTest'
php -l app/Repositories/NhanVienRepository.php
rg -n "sp_nhan_vien_(xoa|dang_nhap)\b" app routes resources tests docs database/sql/employee quan_ly_nhan_su.session.sql
```

Expected: tests PASS; grep chỉ có assertion chứng minh routine legacy không tồn tại, không có runtime caller/definition legacy.

- [ ] **Step 6: Commit và push**

```powershell
git add -- database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql database/sql/employee/README.md quan_ly_nhan_su.session.sql app/Contracts/NhanVienRepositoryContract.php app/Repositories/NhanVienRepository.php tests/Support/MariaDbEmployeeLifecycleWorker.php tests/Support/EmployeeDependencyFixture.php tests/Support/PrepareEmployeeAcceptanceDependency.php tests/Integration/MariaDb/CanonicalDumpReplayTest.php tests/Integration/MariaDb/EmployeeLifecycleProcedureTest.php tests/Integration/MariaDb/EmployeeLifecycleConcurrencyTest.php tests/Integration/MariaDb/EmployeeAuthProcedureTest.php
git diff --cached --check
git commit -m "feat(employee-db): add lifecycle and auth contracts"
git push
```

### Task 14: Lifecycle service, routes, and action dialogs

**Files:**

- Modify: `app/Contracts/NhanVienServiceContract.php`
- Modify: `app/Services/NhanVienService.php`
- Modify: `app/Http/Controllers/Backend/NhanVienController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/nhanvien/show.blade.php`
- Modify: `resources/views/backend/nhanvien/edit.blade.php`
- Create: `resources/views/backend/nhanvien/partials/action-dialogs.blade.php`
- Create: `resources/js/frontend/nhanvien/confirm-actions.js`
- Modify: `resources/js/frontend/nhanvien/nhanvien.js`
- Modify: `package.json`
- Create: `tests/Unit/Services/NhanVienServiceLifecycleTest.php`
- Create: `tests/Frontend/nhanvien/confirm-actions.test.js`
- Create: `tests/Feature/Backend/NhanVien/NhanVienLifecycleTest.php`

**Interfaces:**

- `NhanVienService::removeOrTerminate(string $maNv): NhanVienRemovalAction`
- `NhanVienService::resetPassword(string $maNv): void`; controller chỉ flash thành công + quy ước tĩnh theo năm thao tác.
- DELETE/PATCH route dùng `ma_nv` constraint và CSRF, không có JSON employee API.

- [ ] **Step 1: Viết service, feature và Node tests RED**

Service tests khóa transaction, `DELETED` chỉ xóa avatar sau commit nếu old path qua `NhanVienAvatarPath::assertOwnedFile()`, `TERMINATED` giữ avatar, DB rollback giữ file, file-delete fail/path cũ absolute-traversal-wrong-prefix chỉ log event code/`ma_nv`/exception hoặc reason class mà không log path, và reset gửi hash mới nhưng không trả plaintext. Feature tests khóa route/method/CSRF/not-found, flash khác nhau cho `DELETED`/`TERMINATED`, reset flash quy ước và form xuất hiện ở index/show/edit; controller gọi `NhanVienTargetGuard` trước lifecycle/reset nên target đặc quyền hoặc self đặc quyền trả `403`, không gọi mutation service. Node tests khóa focus open/Escape/cancel/restore và double submit. Cập nhật `test:frontend` để liệt kê tường minh cả ba file test hiện có, gồm `confirm-actions.test.js`; không glob và zero-test không được tính pass.

- [ ] **Step 2: Xác nhận RED**

```powershell
php artisan test tests/Unit/Services/NhanVienServiceLifecycleTest.php tests/Feature/Backend/NhanVien/NhanVienLifecycleTest.php
node --test tests/Frontend/nhanvien/confirm-actions.test.js
```

- [ ] **Step 3: Cài service và controller actions**

Controller nạp target qua service và gọi `NhanVienTargetGuard` trước `removeOrTerminate`/`resetPassword`; procedure lặp lại sau row lock để đóng race. Service gọi procedure trong transaction qua `$this->database->connection()`. Với `DELETED`, sau transaction chỉ xóa avatar trên disk `public` nếu shared avatar guard xác nhận exact ownership; path không hợp lệ được bỏ qua và log an toàn. Với `TERMINATED`, giữ avatar. Reset tạo `nhom3@{năm reset}`, hash bằng hasher, `unset` plaintext rồi gọi repository; controller chỉ nhận `void`. Controller map domain error an toàn và flash đúng action.

- [ ] **Step 4: Cài route và dialog accessible**

```php
Route::patch('nhan-vien/{ma_nv}/dat-lai-mat-khau', [NhanVienController::class, 'resetPassword'])
    ->where('ma_nv', 'NV[0-9]{3}')
    ->name('nhanvien.reset-password');
Route::delete('nhan-vien/{ma_nv}', [NhanVienController::class, 'destroy'])
    ->where('ma_nv', 'NV[0-9]{3}')
    ->name('nhanvien.destroy');
```

Include partial trong index/show/edit, không include ở create. Form có `_method`, CSRF và accessible name; copy nói rõ xóa cứng hoặc kết thúc theo lịch sử. JS quản lý focus/Escape/cancel/double-submit và fallback `window.confirm`.

- [ ] **Step 5: Verify, commit và push**

```powershell
php artisan test tests/Unit/Services/NhanVienServiceLifecycleTest.php tests/Feature/Backend/NhanVien/NhanVienLifecycleTest.php
npm run test:frontend
npm run build
php artisan route:list --path=admin/nhan-vien --except-vendor
git add -- app/Contracts/NhanVienServiceContract.php app/Services/NhanVienService.php app/Http/Controllers/Backend/NhanVienController.php routes/web.php resources/views/backend/nhanvien/index.blade.php resources/views/backend/nhanvien/show.blade.php resources/views/backend/nhanvien/edit.blade.php resources/views/backend/nhanvien/partials/action-dialogs.blade.php resources/js/frontend/nhanvien/confirm-actions.js resources/js/frontend/nhanvien/nhanvien.js package.json tests/Unit/Services/NhanVienServiceLifecycleTest.php tests/Frontend/nhanvien/confirm-actions.test.js tests/Feature/Backend/NhanVien/NhanVienLifecycleTest.php
git diff --cached --check
git commit -m "feat(employee): enforce employee lifecycle actions"
git push
```

### Task 15: Custom employee authentication

**Files:**

- Create: `app/Auth/NhanVienUserProvider.php`
- Create: `app/Http/Requests/Auth/LoginRequest.php`
- Create: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/auth.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `resources/views/auth/login.blade.php`
- Modify: `resources/views/backend/layouts/topbar.blade.php`
- Create: `tests/Unit/Auth/NhanVienUserProviderTest.php`
- Create: `tests/Feature/Auth/EmployeeAuthenticationTest.php`

**Interfaces:**

- Provider driver `nhan-vien`, guard `web`, model `NhanVien`; session identifier là `ma_nv`.
- Login field `dinh_danh` nhận mã hoặc email, password field `mat_khau`; không có remember token/cookie.
- Route public: `GET /dang-nhap` tên `login`, `POST /dang-nhap` tên `login.store`; `POST /dang-xuat` tên `logout`.

- [ ] **Step 1: Viết provider/auth tests RED**

Unit tests mock repository và hasher để khóa đủ Laravel 12 `UserProvider` contract:

```php
public function retrieveById($identifier): ?Authenticatable;
public function retrieveByToken($identifier, $token): ?Authenticatable;
public function updateRememberToken(Authenticatable $user, $token): void;
public function retrieveByCredentials(array $credentials): ?Authenticatable;
public function validateCredentials(Authenticatable $user, array $credentials): bool;
public function rehashPasswordIfRequired(
    Authenticatable $user,
    array $credentials,
    bool $force = false,
): void;
```

Các tham số untyped ở ba method đầu khớp chính xác Laravel 12 `UserProvider`; test dùng `class_implements()` và resolve provider qua guard để bắt signature fatal. Khóa `retrieveById` và credentials đều gọi repository, repository hydrate bằng `NhanVien::fromAuthProcedureRow()`; account `DA_NGHI` trả null; `validateCredentials` dùng hasher. `rehashPasswordIfRequired()` chỉ chạy sau credential validation, giữ hash hiện tại từ model, tạo hash mới rồi gọi `rehashAuthenticatedPassword(ma_nv, currentHash, newHash)` compare-and-swap; test `needsRehash=true` và `force=true` trên cả baseline lẫn admin role. Nếu mapper trả exact `NV_AUTH_HASH_STALE`, provider log duy nhất event code + `ma_nv` (không hash/credential/SQL), bỏ rehash và return để valid attempt vẫn login; vì reset đồng thời đã thắng nên không retry với plaintext. Mọi DB/domain lỗi khác được auth controller bắt thành generic login error và fail closed, không raw `500`; tests khóa cả hai nhánh. Token retrieval luôn null, update remember là no-op, không tạo column/cookie.

Feature tests khóa login bằng mã/email, session regenerate, intended redirect, và login trực tiếp không có intended URL phải về route tồn tại `backend.bangdieukhien.index` (không dùng `/admin`, vì baseline route đó render view lỗi). Các case khác khóa generic error chung cho sai identifier/password/đã nghỉ, throttle theo identifier + IP, logout invalidate/regenerate token; privileged admin có hash cần rehash vẫn login được và repository auth-CAS được gọi. CAS stale vẫn login và ghi log an toàn; connection/unknown failure không tạo session, quay lại form với cùng generic error và không raw exception. Test đăng ký route test-only `/_test/employee-authenticated` với middleware `web,auth`, tạo session rồi đổi repository mock sang `DA_NGHI`; request kế tiếp phải redirect login, nên không phụ thuộc Task 18 mới bọc `/admin`.

- [ ] **Step 2: Xác nhận RED**

```powershell
php artisan test tests/Unit/Auth/NhanVienUserProviderTest.php
php artisan test tests/Feature/Auth/EmployeeAuthenticationTest.php
```

- [ ] **Step 3: Đăng ký provider và cấu hình auth**

Trong `AppServiceProvider::boot()`:

```php
Auth::provider('nhan-vien', function (Application $app, array $config): NhanVienUserProvider {
    return new NhanVienUserProvider(
        $app->make(NhanVienRepositoryContract::class),
        $app->make(Hasher::class),
    );
});
```

`config/auth.php` bỏ import `App\Models\User`, đặt provider `users.driver = nhan-vien`, không cấu hình password broker/token table chưa dùng. `bootstrap/app.php` đặt guest redirect tới route `login` sau khi route tồn tại.

- [ ] **Step 4: Cài login request/controller/view**

`LoginRequest` trim identifier, lowercase nếu chứa `@`, rate-limit key bằng `hash('sha256', Str::lower($identifier).'|'.$ip)`, tối đa 5 lần/phút. Field HTTP là `mat_khau`, nhưng request gọi `Auth::attempt(['dinh_danh' => $identifier, 'password' => $this->string('mat_khau')->toString()])` để khớp contract `SessionGuard`; mọi failure dùng một câu `Thông tin đăng nhập không hợp lệ.` và không ghi credential. Success clear limiter + regenerate rồi `redirect()->intended(route('backend.bangdieukhien.index'))`; logout gọi `Auth::logout()`, invalidate session, regenerate token.

Login page có label/autocomplete đúng (`username`, `current-password`), focus error, disabled/submitting, không có remember và dùng layout auth độc lập tối thiểu; không thay admin shell. Topbar dùng `@auth` trước khi đọc `auth()->user()->ho_ten`/`auth()->id()` và render form POST `logout` có CSRF; nhánh `@guest` hiển thị link login, nên commit vẫn không null/500 trước Task 18. Giữ nguyên cấu trúc shell, search/dropdown/layout và không thêm role query ngoài auth contract.

- [ ] **Step 5: Verify provider, session và no-remember contract**

```powershell
php artisan test tests/Unit/Auth/NhanVienUserProviderTest.php tests/Feature/Auth/EmployeeAuthenticationTest.php
php -l app/Auth/NhanVienUserProvider.php
php artisan route:list --path=dang --except-vendor
rg -n "remember|App\\Models\\User|password_reset_tokens" app config/auth.php resources/views/auth resources/views/backend/layouts/topbar.blade.php routes
```

Expected: tests PASS; các hit `remember` duy nhất là hai method bắt buộc của `UserProvider`/model null-token contract, không có checkbox/cookie/column; không còn missing `User` model hoặc password-reset table reference.

- [ ] **Step 6: Commit và push**

```powershell
git add -- app/Auth/NhanVienUserProvider.php app/Http/Requests/Auth/LoginRequest.php app/Http/Controllers/Auth/AuthenticatedSessionController.php app/Providers/AppServiceProvider.php config/auth.php bootstrap/app.php routes/web.php resources/views/auth/login.blade.php resources/views/backend/layouts/topbar.blade.php tests/Unit/Auth/NhanVienUserProviderTest.php tests/Feature/Auth/EmployeeAuthenticationTest.php
git diff --cached --check
git commit -m "feat(auth): authenticate employee accounts by procedure"
git push
```

### Task 16: RBAC schema, permission procedures, and safe role deletion

**Files:**

- Create: `database/sql/employee/2026_08_12_006_rbac.sql`
- Modify: `database/sql/employee/README.md`
- Modify: `quan_ly_nhan_su.session.sql:40-58`
- Modify: `quan_ly_nhan_su.session.sql:835-1028`
- Modify: `app/Contracts/NhanVienRepositoryContract.php`
- Modify: `app/Repositories/NhanVienRepository.php`
- Modify: `tests/Integration/MariaDb/CanonicalDumpReplayTest.php`
- Create: `tests/Integration/MariaDb/EmployeeRbacProcedureTest.php`

**Interfaces:**

- `NhanVienRepository::permissionSymbols(string $maNv): array<string>` gọi `sp_quyen_lay_theo_ma_nhan_vien`.
- `NhanVienRepository::assignRoleForBootstrap(string $maNv, int $maVt): void` gọi `sp_nhan_vien_gan_vai_tro_noi_bo`; method không xuất hiện ở service/controller/web contract.

- [ ] **Step 1: Viết RBAC migration/guard tests RED**

Integration tests khóa:

- script preflight fail trước DDL nếu `ma_quyen <= 0`, symbol rỗng/trùng sau trim-uppercase;
- preflight xác nhận đúng một FK `vai_tro_quyen(ma_quyen) -> quyen(ma_quyen)`, không orphan và tên/behavior hiện hữu có thể recreate;
- preflight fail trước DDL nếu `NHAN_VIEN_MAC_DINH` thiếu/mơ hồ hoặc đã có bất kỳ mapping; không tự xóa quyền khỏi role hệ thống để che drift;
- dữ liệu quyền hợp lệ cũ và foreign key role-permission được giữ;
- `ma_quyen` trở thành `AUTO_INCREMENT`, `ky_hieu_quyen` unique;
- năm permission được seed idempotent theo symbol, không dựa numeric ID;
- permission lookup trả symbol distinct;
- role `NHAN_VIEN_MAC_DINH` tồn tại đúng một, luôn zero mapping; `sp_vai_tro_quyen_them` và `sp_vai_tro_xoa` đều từ chối role này;
- `sp_nhan_vien_gan_vai_tro_noi_bo` chỉ đổi employee đang ở role mặc định sang role tồn tại khác, giữ mọi field khác, khóa row và không tự transaction;
- `sp_vai_tro_xoa` gặp role đang có nhân viên thì báo `VT_DANG_DUOC_SU_DUNG` và không xóa bất kỳ nhân viên nào;
- role không dùng được xóa cùng mapping quyền, không ảnh hưởng employee khác.

- [ ] **Step 2: Xác nhận RED**

Run: `pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeRbacProcedureTest'`

- [ ] **Step 3: Viết script 006 fail-closed**

Toàn bộ ambiguity/orphan/FK/baseline-role preflight chạy trước DDL vì MariaDB implicit commit; baseline có mapping là blocker dữ liệu rõ ràng, không được tự dọn. Sau preflight, normalize symbol, drop chính xác FK `fk_vai_tro_quyen_quyen`, alter parent `quyen.ma_quyen` thành auto-increment, thêm unique, rồi recreate cùng FK behavior và verify `information_schema.REFERENTIAL_CONSTRAINTS`; không drop FK role khác. Seed đúng các tuple sau bằng `INSERT ... ON DUPLICATE KEY UPDATE`, không dựa numeric ID: `NHAN_VIEN_XEM/Xem nhân viên`, `NHAN_VIEN_TAO/Tạo nhân viên`, `NHAN_VIEN_SUA/Sửa nhân viên`, `NHAN_VIEN_XOA/Xóa hoặc kết thúc làm việc`, `NHAN_VIEN_DAT_LAI_MAT_KHAU/Đặt lại mật khẩu nhân viên`; cả năm có `module = 'NHAN_VIEN'`. Recreate `sp_quyen_them`, `sp_quyen_danh_sach`, `sp_quyen_lay_theo_ma_nhan_vien`, `sp_vai_tro_quyen_them`, `sp_vai_tro_xoa` và `sp_nhan_vien_gan_vai_tro_noi_bo`. Hai procedure role từ chối `NHAN_VIEN_MAC_DINH`; procedure xóa role tuyệt đối không `DELETE FROM nhan_vien`; procedure internal assignment không được gọi từ HTTP path.

- [ ] **Step 4: Cài repository permission lookup**

Repository gọi `sp_quyen_lay_theo_ma_nhan_vien` qua `call()`/write PDO, lấy `ky_hieu_quyen`, trim-uppercase, loại duplicate và từ chối symbol rỗng. `assignRoleForBootstrap()` bọc đúng một CALL qua `databaseOperation()` trên write PDO và mang PHPDoc `@internal`/tên rõ; source-boundary test assert không controller, FormRequest, route hoặc `NhanVienServiceContract` nào tham chiếu method/procedure này. Integration test assert employee role mặc định trả `[]`, role mapping đủ trả chính xác năm symbol và internal assignment rollback cùng outer transaction.

- [ ] **Step 5: Verify, commit và push**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeRbacProcedureTest|CanonicalDumpReplayTest'
php -l app/Repositories/NhanVienRepository.php
rg -n "DELETE FROM nhan_vien" database/sql/employee/2026_08_12_006_rbac.sql quan_ly_nhan_su.session.sql
git add -- database/sql/employee/2026_08_12_006_rbac.sql database/sql/employee/README.md quan_ly_nhan_su.session.sql app/Contracts/NhanVienRepositoryContract.php app/Repositories/NhanVienRepository.php tests/Integration/MariaDb/CanonicalDumpReplayTest.php tests/Integration/MariaDb/EmployeeRbacProcedureTest.php
git diff --cached --check
git commit -m "feat(employee-db): add safe employee RBAC contracts"
git push
```

### Task 17: Guarded demo bootstrap command

**Files:**

- Create: `app/Console/Commands/BootstrapNhanVienDemo.php`
- Create: `tests/Integration/MariaDb/BootstrapNhanVienDemoTest.php`

**Interfaces:**

- Command `employee:bootstrap-demo`; không tự chạy khi deploy/start/test. Switch `--require-disposable` bắt buộc trong acceptance/automated mutation.
- Consumes procedures master data/RBAC Task 16, `NhanVienServiceContract::create()` Task 10 và repository method internal `assignRoleForBootstrap()`; web create vẫn không nhận role.

- [ ] **Step 1: Viết integration tests RED**

`BootstrapNhanVienDemoTest` chạy trên MariaDB disposable và khóa: abort ngoài `local|testing`, thiếu option/`--yes`, và `--require-disposable` fail trước mutation nếu thiếu `MARIADB_TEST_ENABLED=1`, thiếu/mismatch `MARIADB_TEST_DATABASE`, resolved default connection không phải mysql hoặc target không qua guard. Các case khác khóa duplicate email/CCCD, admin role normalize trùng `Nhân viên mặc định`, và data mơ hồ trước mutation; không hard-code PII; create/reuse đúng master/role khác baseline; gán năm quyền; tạo admin qua service contract ở role mặc định rồi gọi internal repository assignment trong cùng outer transaction; output mã + câu quy ước không hash/plaintext. Với rollback test, bind decorator contract gọi `NhanVienService` thật để tạo employee/address/counter trong nested transaction, repository gán role rồi mới ném domain exception; assert outer transaction rollback employee, address, counter increment, master/role/mapping/assignment mới, còn row reused trước đó vẫn còn.

- [ ] **Step 2: Xác nhận RED**

Run: `pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'BootstrapNhanVienDemoTest'`

- [ ] **Step 3: Cài command với options tường minh**

Command nhận bắt buộc:

```text
--department --position --position-allowance --role
--admin-name --admin-email --admin-phone --admin-cccd
--birth-date --start-date --gender --education --ethnicity --cccd-place
--address-line --ward --district --province --yes
--require-disposable
```

Command import `App\Support\DisposableMariaDbGuard`, inject `NhanVienServiceContract` + `NhanVienRepositoryContract`, không phụ thuộc autoload-dev/namespace `Tests\`. Command pin database target trước mutation, chỉ chạy `local|testing`, và yêu cầu `--yes`. Khi có `--require-disposable`, nó so sánh target resolved với `MARIADB_TEST_DATABASE` đã qua guard; mismatch dừng trước mọi query ghi. Trước mutation, nó validate toàn bộ options, precheck exact normalized email/CCCD/status/permission symbols và abort duplicate/mơ hồ. Sau đó một outer `$this->database->connection()->transaction()` bao create/reuse master data, admin role, mapping, `service->create()` ở baseline role và `repository->assignRoleForBootstrap($maNv, $adminRoleId)`; nested transaction dùng cùng default connection nên failure rollback toàn bộ row mới. Nó gọi procedure master data hiện hữu, resolve exact normalized name, lấy status `DANG_LAM`, resolve ID từ `sp_quyen_danh_sach`, đọc mapping hiện có và chỉ gọi `sp_vai_tro_quyen_them` cho quyền còn thiếu. Không có tên/email/CCCD mặc định, không nhận `ma_vt` từ payload create và không chạm `hop_dong`.

Chỉ chạy command tự động trong integration test với database disposable. Không chạy command vào database cấu hình `quan_ly_nhan_su` nếu chưa có một yêu cầu mutation riêng của người dùng.

- [ ] **Step 4: Verify, commit và push**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'BootstrapNhanVienDemoTest'
php artisan help employee:bootstrap-demo
rg -n "HopDong|hop_dong|luong_co_ban|loai_hop_dong" app/Console/Commands/BootstrapNhanVienDemo.php app/Services/NhanVienService.php
git add -- app/Console/Commands/BootstrapNhanVienDemo.php tests/Integration/MariaDb/BootstrapNhanVienDemoTest.php
git diff --cached --check
git commit -m "feat(employee): add guarded demo bootstrap"
git push
```

### Task 18: Employee Gates and protected routes

**Files:**

- Create: `app/Enums/NhanVienPermission.php`
- Create: `app/Services/NhanVienPermissionService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/nhanvien.php`
- Modify: `.env.example`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/nhanvien/show.blade.php`
- Modify: `resources/views/backend/nhanvien/edit.blade.php`
- Modify: `resources/views/backend/nhanvien/create.blade.php`
- Create: `tests/Unit/Services/NhanVienPermissionServiceTest.php`
- Modify: `tests/Support/InteractsWithEmployeeModule.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienIndexTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienValidationTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienShowTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienStoreTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienCreatePageTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php`
- Modify: `tests/Feature/Backend/NhanVien/NhanVienLifecycleTest.php`
- Create: `tests/Feature/Backend/NhanVien/NhanVienAuthorizationTest.php`
- Modify: `tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php`
- Modify: `tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php`

**Interfaces:**

- Enum có đúng năm symbol đã duyệt.
- `NhanVienPermissionService::allows(NhanVien, NhanVienPermission): bool`, inject `NhanVienRepositoryContract`, scoped/cache theo `ma_nv` trong một request.
- Sau khi auth/Gates đã cùng tồn tại, `config/nhanvien.php` đổi rollout default thành `env('NHAN_VIEN_MODULE_ENABLED', true)`; `.env.example` ghi `NHAN_VIEN_MODULE_ENABLED=true`.
- Action Gate kiểm tra quyền actor; `NhanVienTargetGuard` kiểm tra target của edit/update/destroy/reset là role mặc định, và procedure kiểm tra lại sau lock. Không có Gate/route phân vai.

- [ ] **Step 1: Viết unit/feature tests RED**

Permission service test assert repository chỉ gọi một lần/request. Feature data provider khóa guest redirect, thiếu quyền `403`, mỗi quyền chỉ mở đúng action và Blade không render action thiếu quyền. Security cases riêng khóa: TAO với crafted `ma_vt` fail validation; create thành công chỉ baseline zero quyền; SUA/XOA/DAT_LAI_MAT_KHAU nhắm actor hoặc bất kỳ target non-baseline đều `403` trước mutation; endpoint/service/controller không expose internal role assignment. Mở rộng `InteractsWithEmployeeModule` bằng `actingAsEmployeeWithPermissions(array $symbols)`: bật module, tạo `NhanVien` auth fixture qua factory method an toàn, `actingAs`, rồi mock permission service theo đúng enum symbols. Cập nhật toàn bộ feature test route-facing đã tạo ở Task 6–14 để gọi helper với quyền tối thiểu cần thiết; các validation/unit-like case không dispatch route chỉ bật module. Cập nhật cả hai lookup test nghỉ phép/chấm công: request JSON chưa đăng nhập nhận `401`, user đã đăng nhập nhưng thiếu XEM nhận `403`, user có XEM nhận `200` với nguyên response compatibility đã khóa; request browser không có header JSON redirect login.

- [ ] **Step 2: Xác nhận RED**

Run: `php artisan test tests/Unit/Services/NhanVienPermissionServiceTest.php tests/Feature/Backend/NhanVien/NhanVienAuthorizationTest.php`

- [ ] **Step 3: Cài scoped service và Gates**

```php
$this->app->scoped(NhanVienPermissionService::class);
foreach (NhanVienPermission::cases() as $permission) {
    Gate::define(
        $permission->value,
        fn (NhanVien $employee): bool => app(NhanVienPermissionService::class)
            ->allows($employee, $permission),
    );
}
```

- [ ] **Step 4: Bảo vệ server và đồng bộ Blade**

Bọc `/admin` bằng `auth`; employee index/show dùng XEM, create/store TAO, edit/update SUA, destroy XOA, reset DAT_LAI_MAT_KHAU. Với ba mutation có target và edit page, controller luôn chạy `NhanVienTargetGuard`; role baseline không có quyền nên actor đặc quyền không thể tự sửa/xóa/reset bằng flow này. Module admin khác chỉ có auth trong scope. Employee routes vẫn qua rollout middleware, nhưng config mặc định chỉ được bật trong commit này sau khi auth/Gates đã có. Riêng hai GET `/api/v1/nghi-phep/nhan-vien` và `/api/v1/cham-cong/nhan-vien` thêm `web`, `auth`, `can:NHAN_VIEN_XEM`; không thêm session cho API khác. Blade dùng cùng `@can` symbols; không render field/action phân vai.

- [ ] **Step 5: Verify, commit và push**

```powershell
php artisan test tests/Unit/Services/NhanVienPermissionServiceTest.php tests/Feature/Backend/NhanVien tests/Feature/Auth/EmployeeAuthenticationTest.php tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php
php artisan route:list --path=admin --except-vendor
git add -- app/Enums/NhanVienPermission.php app/Services/NhanVienPermissionService.php app/Providers/AppServiceProvider.php config/nhanvien.php .env.example routes/web.php routes/api.php resources/views/backend/nhanvien/index.blade.php resources/views/backend/nhanvien/show.blade.php resources/views/backend/nhanvien/edit.blade.php resources/views/backend/nhanvien/create.blade.php tests/Unit/Services/NhanVienPermissionServiceTest.php tests/Support/InteractsWithEmployeeModule.php tests/Feature/Backend/NhanVien/NhanVienIndexTest.php tests/Feature/Backend/NhanVien/NhanVienValidationTest.php tests/Feature/Backend/NhanVien/NhanVienShowTest.php tests/Feature/Backend/NhanVien/NhanVienStoreTest.php tests/Feature/Backend/NhanVien/NhanVienCreatePageTest.php tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php tests/Feature/Backend/NhanVien/NhanVienLifecycleTest.php tests/Feature/Backend/NhanVien/NhanVienAuthorizationTest.php tests/Feature/Compatibility/NghiPhepEmployeeLookupTest.php tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php
git diff --cached --check
git commit -m "feat(employee-authz): enforce employee permissions"
git push
```

## Checkpoint D — Security boundary

- [ ] Login code/email và session restore đều đi qua procedure/custom provider.
- [ ] `DA_NGHI` bị từ chối cả login mới lẫn session cũ.
- [ ] Không có remember token; rehash cập nhật bằng procedure.
- [ ] Route `/admin` có auth; năm employee action có Gates và feature tests.
- [ ] Xóa role không còn xóa nhân viên.
- [ ] Bootstrap chỉ được chứng minh trên MariaDB disposable; configured live DB chưa bị mutation.
- [ ] Từ checkpoint này CRUD có thể gọi là `verified hẹp`; chưa gọi hoàn thành trước browser acceptance.

---

## Phase 5 — Integrated verification and handoff

### Task 19: Fail-closed browser acceptance harness

**Files:**

- Create: `tests/Support/EmployeeAcceptanceEnvironment.php`
- Create: `tests/Support/employee-acceptance.ps1`
- Create: `tests/Support/employee-acceptance-router.php`
- Create: `tests/Unit/Support/EmployeeAcceptanceEnvironmentSafetyTest.php`
- Create: `tests/Integration/MariaDb/EmployeeAcceptanceEnvironmentTest.php`

**Interfaces:**

- Acceptance chạy trên database mới có tên khớp guard employee-test; server process nhận database đó qua environment. Không đổi/import configured database `quan_ly_nhan_su`.
- CLI helper có đúng sáu action `create`, `verify-runtime`, `seed-roles`, `assign-role`, `cleanup-uploads`, `drop`. Năm action sau bắt buộc `MARIADB_TEST_DATABASE` và guard lại tên; không action nào fallback sang `DB_DATABASE`.
- State file bắt buộc là exact regular file dưới `storage/framework/testing`, do caller truyền tường minh và không được tồn tại trước `Start`; các lần gọi sau dùng lại chính literal path, không phụ thuộc biến PowerShell của process trước. State chỉ lưu `run_id` bằng suffix hex của DB, không lưu/trust absolute temp/log path; mọi path được tái tính từ run ID đã guard.
- Router test-only là exact file `tests/Support/employee-acceptance-router.php`; không dựa vào `server.php` ở repo root hoặc đường dẫn vendor thay đổi ngầm. Port `8012` phải trống trước Start, rồi listener phải thuộc đúng PID PHP child trước khi health được chấp nhận.

- [ ] **Step 1: Viết và kiểm tra acceptance harness fail-closed**

`EmployeeAcceptanceEnvironment.php` là CLI test-only. `create` đọc credential `MARIADB_TEST_*`, tạo tên qua guard Task 1, chạy fixture legacy + scripts `001..006` bằng `SqlScriptRunner`, rồi in đúng JSON `{database}`; nếu fixture/script fail trước khi trả JSON, chính helper disconnect/purge/drop tên vừa tạo trong `catch/finally`. `verify-runtime` boot Laravel dưới child env nhưng không business mutation, assert default connection là `mysql`, resolved database/host/port/username khớp chính xác `MARIADB_TEST_*`, socket null/rỗng, config/route cache không active, app URL là `http://127.0.0.1:8012`, module enabled và avatar prefix đúng suffix guarded; mismatch dừng trước bootstrap/server.

`seed-roles` gọi procedure RBAC idempotently: role `Chỉ xem nhân viên` có **chính xác** một mapping `NHAN_VIEN_XEM`, xóa mọi mapping employee permission khác khỏi role fixture đó; role `Không có quyền nhân viên` có đúng zero mapping. Nó không đụng role ngoài hai exact names. `assign-role` chỉ nhận mã `NV[0-9]{3}` và alias whitelist `view-only|no-permission`, resolve đúng một trong hai fixture rồi gọi repository internal/procedure `sp_nhan_vien_gan_vai_tro_noi_bo`; không nhận ID/tên tùy ý và chỉ chạy database guarded. Trước khi resolve repository, helper dựng connection `employee_acceptance` chỉ từ `MARIADB_TEST_*`, purge/set làm default và assert resolved database/host/port/user; `.env`/`DB_URL` không thể chọn target. `cleanup-uploads` không dựa vào row DB: từ suffix hex của database guarded, nó tính exact prefix `storage/app/public/nhan-vien/acceptance/{suffix}/avatars`, resolve và xác nhận nằm dưới `storage/app/public/nhan-vien/acceptance`; từ chối symlink/reparse point/path traversal, xóa từng file chính xác rồi thư mục rỗng từ dưới lên, không wildcard hoặc xóa parent. Vì mọi upload của run dùng prefix này, file mồ côi sau replace/hard-delete cũng được dọn. `drop` disconnect/purge rồi xóa DB guarded. Unit test chỉ khóa pure action/name/state/path/process-identity guards, không mở DB. Integration test MariaDB khóa create/drop, cleanup khi script giữa chuỗi fail, runtime override fail-closed, exact role mappings/idempotence, guarded role assignment, orphan upload cleanup và target ngoài guard.

`employee-acceptance.ps1` dùng PowerShell parameter sets: `Start` bắt buộc `-StateFile -EnableDisposableMariaDb`, tùy chọn `-SmokeTest`; `AddDependency` bắt buộc thêm `-Employee -Dependency`; `AssignRole` bắt buộc `-Employee -RoleAlias`; `Stop` chỉ cần state + switch. Mọi action từ chối parameter ngoài set của nó. Mỗi invocation lấy credential từ `MARIADB_TEST_*` hoặc prompt username/password masked nếu thiếu, chỉ giữ trong process và khôi phục trong `finally`; vì Stop/AddDependency/AssignRole là process khác, chúng cũng tự resolve credential, không đọc từ state. Trước mọi mở/ghi/đọc/xóa, StateFile phải có basename khớp `employee-acceptance(?:-[a-f0-9]+)?\.json`, parent canonical exact `storage/framework/testing`, và không state path/parent/ancestor bên dưới root nào là symlink/junction/reparse. `Start` dùng exclusive `CreateNew` cho placeholder ownership rồi atomic write-to-sibling + replace; fail nếu path tồn tại hoặc đổi identity giữa checks. Stop/AddDependency/AssignRole yêu cầu regular non-reparse state cùng ownership/run marker; tests bao phủ symlink/junction, parent reparse, tampered state và swap-race simulated. Chỉ sau guard này Start mới gọi helper `create`.

Sau create, script dựng exact temp root `storage/framework/testing/employee-acceptance/{run_id}` và ownership marker như contract dưới, tạo APP_KEY base64 ngẫu nhiên chỉ sống trong child env và map đầy đủ: `APP_ENV=testing`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:8012`, `APP_TIMEZONE=Asia/Ho_Chi_Minh`, `APP_CONFIG_CACHE={root}/config.php`, `APP_ROUTES_CACHE={root}/routes.php`, `DB_CONNECTION=mysql`, `DB_URL={DSN guarded dựng từ credential đã URI-encode}`, `DB_SOCKET=''`, `DB_HOST/PORT/USERNAME/PASSWORD`, `DB_DATABASE={database guarded}`, `DB_TIMEZONE=+07:00`, `MARIADB_TEST_ENABLED=1`, `MARIADB_TEST_DATABASE={database guarded}`, `NHAN_VIEN_MODULE_ENABLED=true`, `EMPLOYEE_AVATAR_PREFIX=nhan-vien/acceptance/{run_id}/avatars`, `EMPLOYEE_ACCEPTANCE_RUN_ID={run_id}`, `SESSION_DRIVER=cookie`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=stderr`. Guarded DSN chỉ nằm trong child env, không được in/state/log; nó ngăn `.env` DB_URL ghi đè, còn `verify-runtime` bắt mọi URL/socket/cache drift.

Script gọi `verify-runtime` trước business mutation, rồi gọi `employee:bootstrap-demo --yes --require-disposable` với exact synthetic fixture: department `PB Acceptance {run_id}`, position `CV Acceptance {run_id}`, allowance `0`, role `Quản trị acceptance {run_id}`, name `Quản trị Acceptance`, email `admin-{run_id}@example.test`, phone `0901234567`, CCCD là chuỗi 12 chữ số deterministic từ 12 hex ký tự của run ID sau phép đổi modulo 10, birth `1990-01-01`, start `2026-08-12`, gender `1`, education `Đại học`, ethnicity `Kinh`, CCCD place `Cục CSQLHC`, address `1 Đường Kiểm Thử`, ward `Phường Test`, district `Quận Test`, province `TP Hồ Chí Minh`. Generator test khóa email/phone/CCCD hợp lệ và không trùng giữa hai run ID. Command output phải cho đúng `NV001`; script verify rồi seed role fixtures và gọi lại `verify-runtime` trước start server. State/stdout có thể lưu `admin_ma_nv=NV001` và synthetic `admin_email` để browser dùng; chúng không phải secret/PII thật. Không lưu mật khẩu/hash.

Router test-only kiểm tra URI `/_employee_acceptance_health/{run_id}` và chỉ trả JSON `{run_id}` khi env marker khớp; static file tồn tại dưới `public` trả `false`, còn request Laravel require exact `public/index.php`. Trước `Start-Process`, script gọi `Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 8012 -State Listen` và abort trước tạo server nếu có listener. Server dùng `Start-Process -WindowStyle Hidden -PassThru` chạy PHP built-in server với resolved executable, `-S 127.0.0.1:8012`, absolute public path và exact absolute router test-only, không shell-built command. Script fail ngay nếu child exit; poll tối đa 10 giây rồi yêu cầu listener `OwningProcess` đúng child PID, process start time/executable/command tokens đúng, health URL trả exact run marker và login page chứa marker ứng dụng dự kiến. Health của process khác không được tính pass. Sau đó state JSON lưu database guarded, `run_id`, `admin_ma_nv`, `admin_email`, PID, process start time UTC, resolved executable, exact non-secret command identity tokens, port và cờ storage link; không lưu credential/key/hash/upload/temp/log absolute path. Temp root luôn được tái tính thành `storage/framework/testing/employee-acceptance/{run_id}` và chứa marker regular file `.owned-by-employee-acceptance` có run ID + database exact. Trước mọi đọc/xóa, script canonicalize root/marker/log, từ chối symlink/reparse point/traversal và yêu cầu chúng nằm trực tiếp dưới exact root; state tampered hoặc marker mismatch thì không xóa path đó. Trước `Stop-Process`, Stop đọc listener/process + CIM command line và chỉ dừng khi PID, listener ownership, start time, executable, router/public path và port đều khớp state; mismatch không kill process nào nhưng vẫn cleanup các resource độc lập đã guard rồi trả lỗi identity. Nếu `public/storage` chưa tồn tại, Start chạy `php artisan storage:link`, xác minh link resolve đúng `storage/app/public` và ghi cờ; nếu đã tồn tại nhưng resolve sai thì abort. `Start` ghi log vào exact guarded root và đúng một JSON `{state_file,url,admin_ma_nv,admin_email}` ở stdout dòng cuối. `-SmokeTest` tự Start/health-check/Stop trong một process và trả JSON không secret.

`AddDependency` đọc DB guarded từ state, đặt tạm `MARIADB_TEST_DATABASE` cùng `EMPLOYEE_ACCEPTANCE_*` chỉ cho child `PrepareEmployeeAcceptanceDependency.php`, rồi khôi phục/xóa biến trong `finally`; helper không bao giờ đọc `DB_DATABASE`. `AssignRole` cũng đọc/guard state, truyền employee + whitelist alias cho helper action `assign-role` và không nhận role ID/name tùy ý. `Stop` xác minh/dừng process như trên, gọi `cleanup-uploads` rồi `drop` trong `finally`, và chỉ xóa storage link khi chính harness tạo sau khi resolve lại đúng target. Nếu cleanup file từ chối entry, không xóa entry đó nhưng vẫn drop DB và trả lỗi rõ ràng. Mọi nhánh Start fail sau khi tạo DB cũng dừng process chỉ khi identity khớp, cleanup exact avatar prefix, drop DB, xóa temp/link/state do harness tạo.

```powershell
php artisan test tests/Unit/Support/EmployeeAcceptanceEnvironmentSafetyTest.php
```

- [ ] **Step 2: Smoke test Start/Stop và cleanup khi bootstrap fail**

`EmployeeAcceptanceEnvironmentTest` chạy qua suite MariaDB riêng. Nó giả lập process env có `DB_URL` trỏ database không guarded và cached config path cũ, rồi chứng minh isolated child/`verify-runtime` vẫn resolve đúng target guarded trước mutation. Test role exact mapping, cleanup cả avatar còn tham chiếu lẫn orphan, và mọi fail path. Pure unit test khóa state bị sửa run ID/database, basename sai, absolute/outside path, state/parent symlink-reparse, marker mismatch và simulated path swap đều không đọc/xóa file ngoài root. Smoke script assert state không secret/absolute temp path, PID/listener identity match trước Stop, health marker đúng, DB/link/temp/prefix avatar được cleanup; case PID identity mismatch không kill process mồi. Decoy-port test start một listener mồi ở `127.0.0.1:8012`, assert harness abort trước PHP child/health và tuyệt đối không kill decoy. Bind bootstrap/service fail và assert Start tự cleanup trước exit non-zero.

```powershell
php artisan test tests/Unit/Support/EmployeeAcceptanceEnvironmentSafetyTest.php
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb -Filter 'EmployeeAcceptanceEnvironmentTest'
pwsh -NoProfile -File tests/Support/employee-acceptance.ps1 -Action Start -StateFile storage/framework/testing/employee-acceptance-a11ce.json -EnableDisposableMariaDb -SmokeTest
```

- [ ] **Step 3: Commit và push harness**

```powershell
git add -- tests/Support/EmployeeAcceptanceEnvironment.php tests/Support/employee-acceptance.ps1 tests/Support/employee-acceptance-router.php tests/Unit/Support/EmployeeAcceptanceEnvironmentSafetyTest.php tests/Integration/MariaDb/EmployeeAcceptanceEnvironmentTest.php
git diff --cached --check
git commit -m "test(employee): add disposable browser harness"
git push
```

### Task 20: Browser acceptance, documentation, and delivery audit

**Files:**

- Modify: `docs/PROJECT_STATUS.md`
- Modify: `docs/DATABASE.md`
- Modify: `docs/CODEX_NEXT_HANDOFF.md`
- Modify: `docs/FRONTEND_GUIDE.md`
- Modify: `docs/superpowers/specs/2026-08-12-quan-ly-nhan-vien-design.md`
- Modify: `docs/superpowers/plans/2026-08-12-quan-ly-nhan-vien.md`

**Interfaces:**

- Consumes harness Task 19; không mutation configured live DB.
- Trạng thái tài liệu chỉ dùng `verified hẹp`, `prototype`, `blocked`, `planned`; chỉ dùng “hoàn thành” khi đủ mọi acceptance criterion.

- [x] **Step 1: Re-run automated gates**

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
php artisan test tests/Unit tests/Feature/Backend/NhanVien tests/Feature/Auth tests/Feature/Compatibility
npm run test:frontend
npm run build
composer validate --no-check-publish
php artisan route:list --except-vendor
git diff --check
```

Chạy thêm `php artisan test` đầy đủ và ghi riêng kết quả. Nếu baseline `/` vẫn fail `404`, giữ assertion và báo đó là blocker cũ ngoài scope; không mô tả full suite xanh.

- [x] **Step 2: Tạo acceptance database disposable và account demo**

```powershell
pwsh -NoProfile -File tests/Support/employee-acceptance.ps1 -Action Start -StateFile storage/framework/testing/employee-acceptance.json -EnableDisposableMariaDb
```

Harness fail nếu state literal đã tồn tại; tạo database guarded, chạy scripts, verify runtime target, bootstrap admin `NV001`, seed role acceptance, rồi start `http://127.0.0.1:8012` với APP_URL đúng origin, APP_KEY/cache paths ephemeral, guarded DB URL, socket rỗng, avatar prefix riêng và DB disposable. Password suy ra từ công thức đã duyệt, không đọc/in hash. JSON stdout cho URL, nhưng mọi bước sau dùng literal state path nên không phụ thuộc biến shell sống qua browser/tool turns. Nếu bất kỳ bước Start nào fail, harness chỉ dừng process có identity khớp và cleanup DB/file/link/state trong `finally` trước khi trả lỗi.

- [ ] **Step 3: Dùng browser skill chạy acceptance thật**

Trạng thái 2026-08-21: phần login/session, CRUD, lifecycle/RBAC, stale/filter/
flash/edit mapping, console và responsive đã pass hẹp; riêng avatar
upload/replacement bị Chrome extension chặn file URL access nên Step 3 cố ý giữ
chưa hoàn tất.

Đọc và dùng skill `browser-testing-with-devtools` tại thời điểm triển khai. Kiểm tra qua UI thật:

1. login bằng mã và email;
2. browser kiểm tra empty-search, success và filter submitting (`aria-busy`/disabled); empty-database riêng được feature-test ở Task 6 vì bootstrap bắt buộc tạo admin;
3. tạo lần lượt `NV002` và `NV003` qua UI, không có field/request `ma_vt`; cả hai nhận baseline role zero quyền, thấy mã hệ thống, nguyên văn flash `Đã tạo nhân viên; có thể bổ sung hợp đồng sau.` và password convention;
4. detail và edit `NV002`; avatar mới/replace; mỗi ảnh phải tải `200` từ đúng origin `http://127.0.0.1:8012/storage/...`, không phải `http://localhost`;
5. filter/pagination và query được giữ;
6. reset password `NV002`, đăng nhập tài khoản đó ở browser context riêng bằng `nhom3@{năm reset}`, và xác nhận baseline user bị `403` ở trang cần XEM; Network/UI không lộ hash;
7. giữ form edit `NV002` đã tải ở tab/context A, hard-delete `NV002` chưa có dependency ở admin context B, rồi submit form stale ở A; UI phải hiện 404/domain error an toàn, không SQL/stack/raw exception. Đây là browser check lỗi thật trên database disposable, không fault-injection route và không được thay bằng feature test;
8. đăng nhập `NV003` ở context riêng trước, sau đó chạy `pwsh -NoProfile -File tests/Support/employee-acceptance.ps1 -Action AddDependency -StateFile storage/framework/testing/employee-acceptance.json -Employee NV003 -Dependency hop_dong -EnableDisposableMariaDb`; admin action chuyển `NV003` sang `DA_NGHI`, thông báo đúng và request tiếp theo của session NV003 bị auth từ chối;
9. admin tạo `NV004` và `NV005` qua UI baseline; chạy `pwsh -NoProfile -File tests/Support/employee-acceptance.ps1 -Action AssignRole -StateFile storage/framework/testing/employee-acceptance.json -Employee NV004 -RoleAlias view-only -EnableDisposableMariaDb` rồi lặp lại exact command với `-Employee NV005 -RoleAlias no-permission`. `NV005` non-baseline zero-quyền bị `403` ở XEM; `NV004` chỉ-XEM thấy list nhưng không thấy/không gọi được TAO/SUA/XOA/DAT_LAI_MAT_KHAU. Admin gọi trực tiếp edit/update/delete/reset nhắm `NV001`, `NV004` hoặc `NV005` đều nhận `403`, chứng minh target-role boundary dựa exact baseline symbol chứ không chỉ dựa tập quyền;
10. keyboard/focus/dialog/double-submit; console không error; Network không có hash. Nếu stale-error hoặc bất kỳ browser step nào không chạy được, ghi criterion đó `blocked` và không claim browser acceptance/feature hoàn thành.

Chụp/ghi bằng chứng ở width `320`, `375`, `768`, `1024`, `1440`. Browser automation bị policy chặn phải được ghi là `blocked`, tách khỏi test/build; không suy diễn browser pass.

- [x] **Step 4: Cleanup acceptance environment fail-safe**

Ngay cả khi browser/tool step lỗi, bắt buộc chạy `pwsh -NoProfile -File tests/Support/employee-acceptance.ps1 -Action Stop -StateFile storage/framework/testing/employee-acceptance.json -EnableDisposableMariaDb`. Xác nhận process chỉ bị dừng khi toàn bộ identity khớp; exact run-specific avatar prefix (kể cả orphan) đã được dọn, database guarded không còn, state/temp/log đã xóa và link acceptance-created đã gỡ. Nếu process identity mismatch, không kill process đó; vẫn cleanup các resource còn lại và báo lỗi. Không xóa directory rộng hoặc dùng wildcard.

- [x] **Step 5: Security/boundary audit**

```powershell
rg -n 'mat_khau|password|SELECT \*|->getMessage\(|DB::(select|statement).*nhan_vien|sp_nhan_vien_(danh_sach|tim_kiem|xoa|dang_nhap)\b' app routes resources database/sql/employee
rg -n 'ma_vt|assignRoleForBootstrap|sp_nhan_vien_gan_vai_tro_noi_bo' app/Http app/Services app/Contracts/NhanVienServiceContract.php routes resources/views/backend/nhanvien
rg -n 'HopDong|hop_dong|luong_co_ban|loai_hop_dong' app/Http/Controllers/Backend/NhanVienController.php app/Services/NhanVienService.php app/Repositories/NhanVienRepository.php resources/views/backend/nhanvien database/sql/employee
git status --short --branch
```

Phân loại từng hit hợp lệ (hash chỉ trong auth/write path; `hop_dong` chỉ trong lifecycle dependency check) hoặc sửa trước khi bàn giao. Xác nhận `docs/CODEX_FRONTEND_HANDOFF.md`, `.env`, `public/build`, upload test và credential không tracked/staged.

- [x] **Step 6: Cập nhật tài liệu bằng bằng chứng thực tế**

Ghi procedure signatures/order/result shape và cách chạy MariaDB suite vào `DATABASE.md`; trạng thái module, test counts, browser matrix và giới hạn vào `PROJECT_STATUS.md`; quyết định tiếp theo/commit/upstream vào handoff; asset/page conventions, `php artisan storage:link` cho local và `Storage::url()` vào frontend guide. Đổi status của design spec theo đúng bằng chứng. Đánh dấu toàn bộ checkbox plan đã thực sự hoàn tất; checkbox browser giữ trống nếu bị policy chặn.

- [ ] **Step 7: Final verification, documentation commit, and push**

Đọc và dùng `superpowers:verification-before-completion` trước claim cuối. Chạy lại:

```powershell
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
git diff --check
git status --short --branch
```

Stage đúng bốn tài liệu project, spec và plan; xem full staged diff, rồi:

```powershell
git add -- docs/PROJECT_STATUS.md docs/DATABASE.md docs/CODEX_NEXT_HANDOFF.md docs/FRONTEND_GUIDE.md docs/superpowers/specs/2026-08-12-quan-ly-nhan-vien-design.md docs/superpowers/plans/2026-08-12-quan-ly-nhan-vien.md
git diff --cached --check
git diff --cached --stat
git commit -m "docs(employee): record integrated acceptance evidence"
git push
```

- [ ] **Step 8: Prove remote handoff without merging**

```powershell
git rev-parse HEAD
git rev-parse '@{u}'
git status --short --branch
```

Expected: local/upstream SHA giống nhau sau push, worktree sạch, nhánh vẫn là `feature/quanly-nhan-vien`, `main` chưa merge. Không fetch, merge, rebase hoặc tạo PR.

## Final Acceptance Checklist

- [x] Tất cả contract procedure/schema pass trên MariaDB disposable, gồm concurrency và rollback.
- [x] List/create/detail/edit/delete-or-terminate/reset dùng data thật qua repository procedure.
- [x] Password mặc định/reset là `nhom3@{năm}`, hash Laravel, không forced-change flag, không lộ hash.
- [x] Auth/RBAC đủ năm quyền, CRUD không expose `ma_vt`, target non-baseline bị chặn hai tầng và `DA_NGHI` fail closed.
- [ ] UI có loading/empty/success/validation/server/submitting, accessible và responsive.
- [x] Employee module hoạt động độc lập khi chưa có workflow quản trị hợp đồng; lifecycle chỉ đọc dependency hiện có.
- [x] Caller cũ và mã `ma_nv` chéo module đã regression-test; lookup chấm công chạy procedure thật với aggregate/pagination.
- [x] Automated gates và browser acceptance được báo đúng bằng chứng/giới hạn.
- [ ] Mỗi vertical slice đã commit + push; không merge/rebase/force-push/PR.
- [x] Configured live database và local-only frontend handoff không bị mutation/stage.
