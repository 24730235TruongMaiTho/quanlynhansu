# Hướng dẫn module Nhân viên

> Tài liệu authoritative cho người phát triển, reviewer và người chạy demo local.
> Snapshot: 2026-08-21 (Asia/Saigon).

## 1. Đối tượng, trạng thái và thứ tự đọc

Tài liệu này dành cho thành viên nhóm và AI agent tiếp tục module Nhân viên trên Laravel hiện tại. Module đã **verified hẹp** trong main qua merge `aa77419`: list/filter/pagination, tạo, chi tiết, sửa hồ sơ/địa chỉ/avatar, xóa hoặc chuyển nghỉ việc, reset mật khẩu, đăng nhập/session và RBAC năm quyền đã có test tự động; browser avatar upload vẫn blocked/unverified. Đây không phải claim production-ready, không phải approval rollout database thật và không claim tương thích MySQL 8. Khi bắt đầu task mới, revalidate HEAD, upstream, route, test và build thay vì tin snapshot commit.

Đọc theo thứ tự trước khi sửa:

1. [AGENTS.md](../AGENTS.md) và [README.md](../README.md).
2. [PROJECT_STATUS.md](PROJECT_STATUS.md) và [CODEX_NEXT_HANDOFF.md](CODEX_NEXT_HANDOFF.md).
3. [DATABASE.md](DATABASE.md), sau đó [quan_ly_nhan_su.session.sql](../quan_ly_nhan_su.session.sql) nếu task chạm DB.
4. [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) và [FRONTEND_GUIDE.md](FRONTEND_GUIDE.md).
5. Route, Request, controller, service/repository, Blade/JavaScript, SQL và test đúng vertical slice.

Code, route, test và DB đang kiểm tra live có ưu tiên cao hơn snapshot tài liệu.

## 2. Setup local an toàn

### Điều kiện và môi trường

- PHP 8.2+, Composer, Node.js/npm và MariaDB 10.4.x; runtime đã kiểm chứng là MariaDB 10.4.32.
- Tạo .env từ .env.example, chạy php artisan key:generate, composer install và npm install. Không commit .env.
- Dùng credential local được phép dùng; không dùng hoặc ghi credential thật vào source, log, fixture hay tài liệu.
- Giữ APP_TIMEZONE=Asia/Ho_Chi_Minh và DB_TIMEZONE=+07:00 đồng bộ.
- Đặt NHAN_VIEN_MODULE_ENABLED=true để bật module. Đặt false là rollout kill switch fail-closed (404), không thay cho auth/Gate.

Ví dụ phần DB local:

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=quan_ly_nhan_su
    DB_USERNAME=<tai-khoan-local>
    DB_PASSWORD=<mat-khau-local>
    DB_TIMEZONE=+07:00
    NHAN_VIEN_MODULE_ENABLED=true
    SESSION_DRIVER=file
    CACHE_STORE=file
    QUEUE_CONNECTION=sync

### Khởi động ứng dụng

Chạy hai terminal từ repository root:

    php artisan serve
    npm run dev

Trang đăng nhập là [/dang-nhap](http://127.0.0.1:8000/dang-nhap). Sau khi đăng nhập, màn hình chính của module là [/admin/nhan-vien](http://127.0.0.1:8000/admin/nhan-vien). Các URL chính khác:

- /admin/nhan-vien/create — tạo hồ sơ.
- /admin/nhan-vien/{ma_nv} — chi tiết.
- /admin/nhan-vien/{ma_nv}/edit — sửa hồ sơ.
- /admin/cham-cong — màn hình chấm công hiện hành.

Các URL tương thích /admin/nhan-vien/danh-sach-nhan-vien và /admin/nhan-vien/them-nhan-vien redirect về named route canonical.

Chỉ chạy php artisan storage:link khi cần public avatar/storage link và chỉ khi link hiện tại chưa tồn tại hoặc đã được kiểm tra đúng đích storage/app/public. Không thay hoặc xóa link dùng chung một cách tùy tiện.

### Demo local và acceptance disposable

Đường khuyến nghị cho thành viên mới là acceptance harness: nó provision một
database disposable có tên guarded, chạy schema/routine cần thiết, tạo admin,
khởi động server ở `http://127.0.0.1:8012` và trả JSON gồm `url`,
`admin_ma_nv`, `admin_email`. Mật khẩu bootstrap dùng quy ước
`nhom3@{năm thao tác}`; không ghi credential thật vào repository.

```powershell
$runId = ([Guid]::NewGuid().ToString('N')).Substring(0, 12).ToLowerInvariant()
$stateFile = "storage/framework/testing/employee-acceptance-$runId.json"
if (Test-Path -LiteralPath $stateFile) {
    throw "Generated acceptance StateFile already exists: $stateFile"
}
$stateOwned = $false
$mysqlPwdWasPresent = Test-Path Env:MYSQL_PWD
$mysqlPwdPrevious = if ($mysqlPwdWasPresent) { $env:MYSQL_PWD } else { $null }
try {
    try {
        $startOutput = pwsh -NoProfile -File tests/Support/employee-acceptance.ps1 `
            -Action Start -StateFile $stateFile -EnableDisposableMariaDb
        if ($LASTEXITCODE -ne 0) { throw 'Acceptance Start failed.' }
        $stateOwned = $true
        $startOutput
    }
    finally {
        # Start can leave an owned state during a partial failure; the path was
        # verified absent above, so only this invocation can own it.
        if (-not $stateOwned -and (Test-Path -LiteralPath $stateFile)) {
            $stateOwned = $true
        }
    }
    # Dùng URL/identity trong JSON và tạo thêm nhân viên qua UI/browser.
}
finally {
    try {
        if ($stateOwned -and (Test-Path -LiteralPath $stateFile)) {
            pwsh -NoProfile -File tests/Support/employee-acceptance.ps1 `
                -Action Stop -StateFile $stateFile -EnableDisposableMariaDb
        }
    }
    finally {
        if ($mysqlPwdWasPresent) {
            Set-Item -Path Env:MYSQL_PWD -Value $mysqlPwdPrevious
        }
        else {
            Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
        }
    }
}
```

StateFile dùng suffix 12 hex nên mỗi invocation có namespace riêng; lệnh `Stop`
chỉ chạy khi Start thành công hoặc invocation đã để lại đúng file state của nó,
không thể dừng phiên người khác nếu Start bị từ chối vì state pre-existing.
Nếu harness phải prompt `MARIADB_TEST_*` credential, nó snapshot/khôi phục các
biến đó trong process harness; không đưa credential vào StateFile hoặc tài liệu.
Luôn giữ cleanup trong `finally` hoặc chạy Stop thủ công với đúng StateFile sở
hữu kể cả khi browser bị lỗi. Acceptance Start/Stop là đường browser disposable;
không dùng nó để suy ra production readiness.

Trong môi trường local đã được kiểm chứng, bộ seed hiện tạo các mã NV006–NV010: admin thường là NV006, bốn tài khoản còn lại là nhân viên thường. Các mã này **không ổn định** sau cleanup/reseed vì bộ đếm mã không giảm và không tái sử dụng mã cũ.

Identity admin demo:

    email: demo.admin@employee.example.test
    password: nhom3@2026

Credential này chỉ dành cho local/demo; phải đổi hoặc xóa trước khi chia sẻ, deploy hoặc dùng môi trường thật. Bốn tài khoản thường không có quyền employee. Tên, email example.test, phone và CCCD trong seed là synthetic, không phải PII thật. Không suy ra production identity từ bộ demo.

## 3. Cách sử dụng và quyền

Từ /admin/nhan-vien, người có quyền XEM có thể tìm theo mã/tên/email, lọc, đổi trang, mở chi tiết và đi tới form sửa. Người có quyền TAO dùng nút tạo mới; form wizard gồm hồ sơ, địa chỉ, mật khẩu do server tạo và avatar tùy chọn.

Ở chi tiết/sửa:

- Sửa hồ sơ và địa chỉ theo validation server; mã nhân viên, role, hash và ngày nghỉ việc không nhận từ client.
- Upload/xóa/thay avatar theo prefix an toàn. File mới chỉ được giữ sau commit; file cũ xóa sau commit, file mới được bù trừ khi transaction lỗi.
- Xóa cứng khi không có dependency; khi có dependency, action chuyển đúng trạng thái DA_NGHI và giữ ngày nghỉ đầu tiên.
- Reset mật khẩu chỉ tạo hash ở Laravel/repository boundary; plaintext không được flash, log hoặc trả về JSON.

Năm permission symbol được Gate sử dụng:

| Symbol | Mục đích |
| --- | --- |
| NHAN_VIEN_XEM | xem danh sách/chi tiết và lookup dùng chung |
| NHAN_VIEN_TAO | mở và submit tạo mới |
| NHAN_VIEN_SUA | sửa hồ sơ/địa chỉ/avatar |
| NHAN_VIEN_XOA | xóa hoặc chuyển nghỉ việc |
| NHAN_VIEN_DAT_LAI_MAT_KHAU | reset mật khẩu |

Rollout flag NHAN_VIEN_MODULE_ENABLED phải bật trước; sau đó vẫn bắt buộc auth và Gate đúng hành động. Role NHAN_VIEN_MAC_DINH là role baseline và có zero employee permission. Target không thuộc exact role baseline và actor tự nhắm mình bị chặn trước mutation; đây là boundary bảo mật, không bỏ qua khi debug bằng cách cấp quyền rộng hơn.

## 4. Quyết định database và scripts

| Tình huống | Đường dẫn đúng | Điều kiện/bảo vệ |
| --- | --- | --- |
| Database mới, disposable/local trống | quan_ly_nhan_su.session.sql | Canonical dump có DROP DATABASE IF EXISTS; chỉ dùng khi dữ liệu có thể bị xóa |
| Database đã tồn tại cần giữ dữ liệu | database/sql/employee/2026_08_12_001_schema.sql → 002 → 003 → 004 → 005 → 006 | Backup đầy đủ bằng công cụ MariaDB/MySQL, preflight whitelist, approval và dừng ngay khi một file lỗi |
| Muốn có dữ liệu demo qua browser | `tests/Support/employee-acceptance.ps1 -Action Start -StateFile storage/framework/testing/employee-acceptance.json -EnableDisposableMariaDb` | Đường khuyến nghị; dùng UI tạo thêm nhân viên, URL/identity lấy từ JSON stdout; luôn Stop đúng StateFile |
| Đã có disposable DB guarded và cần bộ 5-row synthetic | `database/sql/employee/invoke-demo.ps1 -Action seed -EnableDisposableMariaDb` | Chỉ khi `MARIADB_TEST_ENABLED=1` và `MARIADB_TEST_DATABASE` khớp allowlist; helper không nhận canonical `quan_ly_nhan_su` |
| Dọn bộ 5-row synthetic guarded | `database/sql/employee/invoke-demo.ps1 -Action cleanup -EnableDisposableMariaDb` | Chỉ xóa identity/master/role synthetic trên disposable DB đã provision; không giảm hoặc tái sử dụng counter |

Không dùng canonical dump trên database cần giữ dữ liệu. Scripts 001–006 chạy trên database đã chọn và không tự DROP DATABASE, CREATE DATABASE hay USE; phải xác nhận target, version MariaDB, read-only state, row counts và backup trước DDL. Demo SQL không được SOURCE trực tiếp: seed/cleanup fail-closed nếu thiếu marker phiên `employee_demo_guard`, token random và database khớp. Không chạy php artisan db:seed theo quán tính: seeder mặc định còn tham chiếu model User không phải identity của module.

### Kết quả rollout local đã kiểm chứng

Đây là bằng chứng **environment-specific**, không phải production claim: local MariaDB có 16 bảng, 1 view, 8 function, 10 trigger và 69 stored procedure sau canonical/employee rollout; demo seed có 5 employee và 5 address; role admin demo có đúng 5 employee permission; bốn normal demo có zero employee permission. Bộ 5-row trên canonical local là evidence của môi trường chủ repo, không phải lệnh peer nên chạy; người mới dùng acceptance harness và UI. Database disposable/guarded và backup của lần rollout được quản lý ngoài Git; không đưa backup, hash hoặc credential vào index.

### Hai file legacy trên main

database/tao_bang.sql và database/du_lieu_mau.sql là artefact legacy được giữ nguyên để truy nguyên, không phải đường dẫn hiện tại. Chúng thiếu hoặc không khớp ky_hieu, địa chỉ một-một, bộ đếm mã, avatar, ngày nghỉ việc, unique email/CCCD và RBAC; seed password legacy có thể trống/không tương thích auth hiện tại. Chúng cũng không thể thay thế các guard, procedure contract và role mặc định của employee module. Phần đầu mỗi file đã đánh dấu LEGACY / NOT AUTHORITATIVE / DO NOT RUN. Dùng canonical dump cho database disposable hoặc 001–006 và demo seed nói trên.

## 5. Bản đồ backend để tiếp tục phát triển

Luồng chuẩn là:

    routes/web.php hoặc routes/api.php
      → Form Request/validation
      → Controller
      → NhanVienServiceContract/NhanVienService
      → NhanVienRepositoryContract/NhanVienRepository
      → database/sql/employee/001..006 hoặc canonical dump
      → Feature/Unit/MariaDB integration tests

Các điểm vào chính:

- Web routes và middleware: routes/web.php; auth/session ở app/Http/Controllers/Auth/AuthenticatedSessionController.php và app/Auth/NhanVienUserProvider.php.
- Employee lifecycle: app/Http/Controllers/Backend/NhanVienController.php, app/Http/Requests/ListNhanVienRequest.php, StoreNhanVienRequest.php, UpdateNhanVienRequest.php, app/Services/NhanVienService.php, app/Repositories/NhanVienRepository.php.
- Permission/rollout/target guard: app/Enums/NhanVienPermission.php, config/nhanvien.php, app/Http/Middleware/EnsureNhanVienModuleEnabled.php, app/Services/NhanVienPermissionService.php và app/Support/NhanVienTargetGuard.php.
- SQL contract: database/sql/employee/2026_08_12_001_schema.sql đến 2026_08_12_006_rbac.sql; test shape/error/mutation nằm ở tests/Integration/MariaDb/.
- Attendance compatibility: /api/v1/cham-cong/nhan-vien đi qua ChamCongController::employees và NhanVienServiceContract; chi tiết /api/v1/cham-cong dùng Query Builder trên cột canonical cham_cong.ngay_lam.

Các constraint không được phá vỡ:

- Transaction hồ sơ + địa chỉ + avatar thuộc service và cùng write connection.
- Repository giữ SET → CALL → SELECT OUT trên cùng connection; không đoán procedure hoặc đổi thứ tự placeholder.
- Hash được tạo ở Laravel, không nhận role/mã/hash/ngày nghỉ từ request và không trả mat_khau ra view/API.
- Avatar cleanup chỉ sau commit hoặc bù trừ rollback; prefix path phải qua NhanVienAvatarPath.
- Lỗi validation là 422 chuẩn; lỗi DB/generic trả thông báo ổn định, không lộ SQLSTATE, SQL query, stack, credential, hash hay filesystem path.

## 6. Bản đồ frontend và acceptance

Runtime trên main hiện dùng resources/views/backend/layouts/app.blade.php, child page @extends('backend.layouts.app'), sidebar/topbar Bootstrap và các asset public/backend. Shell mục tiêu backend.layout.app của branch frontend chưa được tích hợp; không port shell đó trong task employee này.

Employee UI nằm ở:

- Blade: resources/views/backend/nhanvien/index.blade.php, create.blade.php, show.blade.php, edit.blade.php và partials/.
- JavaScript: resources/js/frontend/nhanvien/nhanvien.js, employee-page.js, filter-submit.js, wizard.js, wizard-state.js, confirm-actions.js.
- CSS/Vite: resources/css/nhanvien/nhanvien.css và vite.config.js.

Mỗi page dữ liệu phải duy trì loading, empty, success, validation error, server/network error và disabled/submitting; action phá hủy cần confirm. Input phải có label, button icon có accessible name, dynamic text được escape, aria-live dùng cho flash/error, focus/keyboard và contrast phải được kiểm tra. Layout bảng phải responsive, không overflow tài liệu; browser acceptance phải kiểm tra desktop/tablet/mobile, console và network. Automated DOM/test/build không thay thế browser acceptance. Hiện browser employee đã có bằng chứng hẹp ở 320/375/768/1024/1440; avatar file chooser upload/replacement còn blocked/unverified do policy Chrome.

## 7. Lệnh kiểm tra và mức bằng chứng

    php artisan route:list --except-vendor
    php artisan test tests/Feature/Auth/EmployeeAuthenticationTest.php tests/Feature/Backend/NhanVien tests/Unit/Auth/NhanVienUserProviderTest.php tests/Unit/Models/NhanVienTest.php tests/Unit/Services/NhanVienPermissionServiceTest.php
    php artisan test tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php
    npm run test:frontend
    npm run build
    composer validate --no-check-publish
    git diff --check
    git status --short

Evidence hiện tại trên main: route inventory 49; scoped employee/auth
`119 tests, 1141 assertions`; attendance compatibility `16 tests, 61
assertions`; full Laravel `234 pass, 1 fail, 1815 assertions` với lỗi duy nhất
`GET /` 404 trong `ExampleTest`. Trước vòng authorization/guard, baseline full
suite là `224/1789`. Đây là kết quả SQLite/feature hiện tại; không thay thế
MariaDB procedure gate.

Integration stored procedure phải dùng wrapper guarded và switch bắt buộc, không trỏ vào database live. Demo 5-row chỉ dùng trên disposable DB đã provision và phải dùng helper; không copy lại lệnh `SOURCE` trực tiếp:

    pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
    pwsh -NoProfile -File database/sql/employee/invoke-demo.ps1 -Action seed -EnableDisposableMariaDb

Có thể thêm -Filter EmployeeReadProcedureTest|CanonicalDumpReplayTest cho vòng focused. Wrapper yêu cầu MARIADB_TEST_*, chỉ cho phép disposable target, khôi phục process environment và trả exit code test. phpunit.xml mặc định dùng SQLite in-memory nên suite Laravel xanh không chứng minh stored procedure, trigger hay MariaDB behavior; MariaDB wrapper mới là bằng chứng DB hẹp. Browser acceptance dùng tests/Support/employee-acceptance.ps1 với state disposable và luôn phải chạy action Stop; không báo browser pass nếu chưa chạy browser thật.

## 8. Checklist cộng tác

- Đọc AGENTS.md, handoff, status, database và docs liên quan trước khi sửa.
- Xác nhận branch/HEAD/status live; làm trên task branch và chỉ một writer/worktree cho mỗi file scope.
- Khóa contract route/request/controller/data trước khi code; dùng vertical slice nhỏ và commit atomic có message rõ mục đích.
- Không fetch, push, force-push, rebase, merge hoặc tạo worktree nếu chưa được giao rõ; không stage .env, secrets, vendor, node_modules, public/build, backup ignored hoặc docs/CODEX_FRONTEND_HANDOFF.md.
- Cập nhật guide/status/handoff khi trạng thái thay đổi; giữ lịch sử Task 20 là historical nếu số liệu không được revalidate.
- Báo riêng verified hẹp, prototype, blocked, planned và unverified; không suy từ response 200, route list, test SQLite, Vite build hoặc seed thành nghiệp vụ production hoàn thành.
- Trước handoff, review staged diff/secret scan, link Markdown nội bộ, test focused rồi broader suite phù hợp; ghi rõ command, exit/result, giới hạn và bước tiếp theo. Không push thay cho reviewer/lead.
