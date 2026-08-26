# Hướng dẫn module Nhân viên

> Tài liệu authoritative cho người phát triển, reviewer và người chạy demo local.
> Snapshot: 2026-08-24 (Asia/Saigon).

> **Hợp đồng DB hiện hành:** Dựng fresh bằng `database/tao_bang.sql` rồi
> `database/du_lieu_mau.sql`; tổng cộng đúng 15 bảng, không cần routine/view/
> trigger. `nhan_vien` chứa trực tiếp `dia_chi_cu_the`, `phuong_xa`,
> `quan_huyen`, `tinh_thanh`, `anh_dai_dien`, `ngay_nghi_viec`; không còn
> `dia_chi_nhan_vien`. Role dùng `ma_vt` (1..5), status dùng `ma_tt` (1..4),
> quyền module Nhân viên dùng `ma_quyen` 101..105. Bộ đếm `NHAN_VIEN` cấp
> tuần tự `NV001..NV999` dưới row lock và fail closed khi thiếu/drift/hết số.
> Các row seed local/demo dùng convention bcrypt `nhom3@2026`;
> plaintext không nằm trong source.

## 1. Đối tượng, trạng thái và thứ tự đọc

Tài liệu này dành cho thành viên nhóm và AI agent tiếp tục module Nhân viên trên Laravel hiện tại. Module đã **verified hẹp** trong main qua merge `aa77419`: list/filter/pagination, tạo, chi tiết, sửa hồ sơ/địa chỉ/avatar, xóa hoặc chuyển nghỉ việc, reset mật khẩu, đăng nhập/session và RBAC năm quyền đã có test tự động; browser avatar upload vẫn blocked/unverified. Đây không phải claim production-ready, không phải approval rollout database thật và không claim tương thích MySQL 8. Khi bắt đầu task mới, revalidate HEAD, upstream, route, test và build thay vì tin snapshot commit.

Đọc theo thứ tự trước khi sửa:

1. [AGENTS.md](../AGENTS.md) và [README.md](../README.md).
2. [PROJECT_STATUS.md](PROJECT_STATUS.md) và [CODEX_NEXT_HANDOFF.md](CODEX_NEXT_HANDOFF.md).
3. [DATABASE.md](DATABASE.md), sau đó cặp fresh SQL `../database/tao_bang.sql`
   và `../database/du_lieu_mau.sql`; chỉ đọc
   [quan_ly_nhan_su.session.sql](../quan_ly_nhan_su.session.sql) khi cần đối chiếu legacy.
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

Trang đăng nhập là [/dang-nhap](http://127.0.0.1:8000/dang-nhap). Bạn cũng có thể mở base URL `/`: guest sẽ được đưa vào luồng đăng nhập, còn user đã xác thực đi thẳng tới dashboard `/admin/bang-dieu-khien`. Sau khi đăng nhập, màn hình chính của module là [/admin/nhan-vien](http://127.0.0.1:8000/admin/nhan-vien). Các URL chính khác:

- /admin/nhan-vien/create — tạo hồ sơ.
- /admin/nhan-vien/{ma_nv} — chi tiết.
- /admin/nhan-vien/{ma_nv}/edit — sửa hồ sơ.
- /admin/cham-cong — màn hình chấm công hiện hành.

Các URL tương thích /admin/nhan-vien/danh-sach-nhan-vien và /admin/nhan-vien/them-nhan-vien redirect về named route canonical.

Chỉ chạy php artisan storage:link khi cần public avatar/storage link và chỉ khi link hiện tại chưa tồn tại hoặc đã được kiểm tra đúng đích storage/app/public. Không thay hoặc xóa link dùng chung một cách tùy tiện.

### Demo local và acceptance disposable

Fresh DB acceptance hiện hành là guarded `phpunit.mariadb.xml`, dựng đúng hai
file SQL active và test direct repository. Browser `employee-acceptance.ps1`
và `EmployeeAcceptanceEnvironment` vẫn là Task 20 historical harness cho
schema/routine cũ; không dùng chúng để claim fresh 15-table/browser pass cho
đến khi được viết lại. Mật khẩu bootstrap dùng quy ước
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

StateFile dùng suffix 12 hex nên mỗi invocation có namespace riêng trong
historical harness; lệnh `Stop`
chỉ chạy khi Start thành công hoặc invocation đã để lại đúng file state của nó,
không thể dừng phiên người khác nếu Start bị từ chối vì state pre-existing.
Nếu harness phải prompt `MARIADB_TEST_*` credential, nó snapshot/khôi phục các
biến đó trong process harness; không đưa credential vào StateFile hoặc tài liệu.
Luôn giữ cleanup trong `finally` hoặc chạy Stop thủ công với đúng StateFile sở
hữu kể cả khi browser bị lỗi. Acceptance Start/Stop là đường browser legacy;
không dùng nó để claim fresh 15-table/browser pass hoặc production readiness.

Fresh seed hiện tạo đúng 30 mã `NV001..NV030`. `NV001` là `Nguyễn Văn An`,
email `an.nguyen@company.com`, role `ma_vt = 1` và status `ma_tt = 2`; các
role/status còn lại được phân bổ theo dữ liệu seed hiện hành, không mặc định
tất cả là role Nhân viên. Cả 30 row dùng bcrypt theo convention local/demo
`nhom3@2026`. Bộ đếm bắt đầu ở 30 và không tái sử dụng mã đã cấp.

Identity demo login (`NV001`):

    ma_nv: NV001
    ho_ten: Nguyễn Văn An
    email: an.nguyen@company.com
    password: nhom3@2026

Credential này chỉ dành cho local/demo; phải đổi hoặc xóa trước khi chia sẻ,
deploy hoặc dùng môi trường thật. Đây là bộ dữ liệu mẫu tiếng Việt đang được
version trong Git của dự án; không suy ra production identity từ seed.

## 3. Cách sử dụng và quyền

Từ /admin/nhan-vien, người có quyền XEM có thể tìm theo mã/tên/email, lọc, đổi trang, mở chi tiết và đi tới form sửa. Người có quyền TAO dùng nút tạo mới; form wizard gồm hồ sơ, địa chỉ, mật khẩu do server tạo và avatar tùy chọn.

Ở chi tiết/sửa:

- Sửa hồ sơ và địa chỉ theo validation server; mã nhân viên, role, hash và ngày nghỉ việc không nhận từ client.
- Upload/xóa/thay avatar theo prefix an toàn. File mới chỉ được giữ sau commit; file cũ xóa sau commit, file mới được bù trừ khi transaction lỗi.
- Xóa cứng khi không có dependency; khi có dependency, action chuyển đúng trạng thái DA_NGHI và giữ ngày nghỉ đầu tiên.
- Reset mật khẩu chỉ tạo hash ở Laravel/repository boundary; plaintext không được flash, log hoặc trả về JSON.

Seed RBAC giữ least privilege theo mapping cố định: role 1 có toàn bộ catalog;
role 2 (Quản trị Nhân sự) chỉ có `101–105, 201–204, 301–304, 401–404`; role 3,
4 và 5 giữ các quyền theo mô tả module hiện có. Migration 16→15 chỉ bổ sung
mapping role 2 còn thiếu và không thu hồi mapping module khác đã tồn tại.

Gate dùng registry tĩnh tại `config/permissions.php` cho các module đã tích hợp;
thêm enum module mới ở registration point này, không sửa provider/service. Mỗi lần kiểm tra đối chiếu
đồng thời `ma_quyen`, `ky_hieu_quyen` và `module` từ projection RBAC; row malformed,
catalog drift hoặc lỗi database đều fail closed. Projection được cache theo actor
trong request scope, không truy vấn database khi application boot.

Lookup nhân viên dùng chung của Chấm công và Nghỉ phép tiếp tục yêu cầu đúng
`NV_VIEW`/Gate employee hiện hữu; đây là compatibility dependency, không cấp
quyền nghiệp vụ mới cho hai module đó và không thay đổi UI/business path của chúng.

Năm Gate ability nội bộ được đối chiếu với `ma_quyen`:

| Ability | `ma_quyen` | Mục đích |
| --- | ---: | --- |
| NV_VIEW | 101 | xem danh sách/chi tiết và lookup dùng chung |
| NV_CREATE | 102 | mở và submit tạo mới |
| NV_EDIT | 103 | sửa hồ sơ/địa chỉ/avatar |
| NV_DELETE | 104 | xóa hoặc chuyển nghỉ việc |
| NV_RESET_PASSWORD | 105 | reset mật khẩu |

Rollout flag NHAN_VIEN_MODULE_ENABLED phải bật trước; sau đó vẫn bắt buộc auth
và Gate đúng hành động. `NV_EDIT` cho phép sửa hồ sơ, địa chỉ và avatar của mọi
target, không phụ thuộc `ma_vt`; request/repository chỉ nhận các cột được
whitelist và giữ nguyên mã nhân viên, role, hash mật khẩu và ngày nghỉ. Xóa,
chuyển nghỉ việc và reset mật khẩu vẫn yêu cầu target `ma_vt = 5`.

Trưởng phòng (`ma_vt = 4`) bị giới hạn server-side trong phòng ban của chính
mình (`ma_pb` lấy từ auth projection); query phòng ban khác không bypass được,
target ngoài phòng trả 404 generic, payload sửa đổi `ma_pb` trả validation error,
và actor thiếu `ma_pb` fail closed. Các role còn lại giữ hành vi lọc toàn bộ
phòng ban. Seed hiện tại chỉ cấp role4 quyền `NV_VIEW`; test-only grants dùng
quyền edit/destructive để chứng minh scope khi permission được cấp sau này.

Scope được kiểm tra trước các mutation; repository transaction hiện chưa nhận
expected `ma_pb` để khóa TOCTOU xuyên suốt delete/reset/update. Vì vậy concurrent
đổi phòng ban giữa pre-check và mutation vẫn là residual security risk cần gate
riêng trước rollout production.

## 4. Quyết định database và scripts

| Tình huống | Đường dẫn đúng | Điều kiện/bảo vệ |
| --- | --- | --- |
| Database mới, disposable/local trống | `database/tao_bang.sql` → `database/du_lieu_mau.sql` | Đúng 15 bảng; chỉ dùng trên DB rỗng/disposable |
| Database 16 bảng đã tồn tại cần giữ dữ liệu | `database/sql/employee/2026_08_24_001_migrate_to_fifteen_tables.sql` | Backup đầy đủ, preflight/copy-verify-drop, approval; DDL MariaDB implicit commit |
| Muốn tái hiện browser historical | `tests/Support/employee-acceptance.ps1 -Action Start -StateFile storage/framework/testing/employee-acceptance.json -EnableDisposableMariaDb` | Legacy routine/address-table harness; không dùng thay fresh 15-table gate; luôn Stop đúng StateFile |
| Bộ 5-row synthetic legacy | `database/sql/employee/invoke-demo.ps1` | Legacy only; không dùng thay fresh 30-row seed |
| Dọn bộ 5-row synthetic legacy | `database/sql/employee/invoke-demo.ps1` | Legacy only; không chạy trên active fresh source |

Không dùng canonical dump trên database cần giữ dữ liệu. Scripts 001–006 chạy trên database đã chọn và không tự DROP DATABASE, CREATE DATABASE hay USE; phải xác nhận target, version MariaDB, read-only state, row counts và backup trước DDL. Demo SQL không được SOURCE trực tiếp: seed/cleanup fail-closed nếu thiếu marker phiên `employee_demo_guard`, token random và database khớp. Không chạy php artisan db:seed theo quán tính: seeder mặc định còn tham chiếu model User không phải identity của module.

### Kết quả rollout local đã kiểm chứng

Fresh pair đã được kiểm tra tĩnh và replay thật trên guarded disposable MariaDB:
đúng 15 bảng, 30 employee, counter 30, direct address/avatar/date columns,
bcrypt login seed `NV001`; role/RBAC explicit theo catalog; repository CRUD/address/avatar/lifecycle,
migration 16→15, allowlisted cleanup và parallel direct-repository counter pass
trong `11 tests, 344 assertions`. Đây không phải bằng chứng live production;
browser avatar vẫn là gate riêng.

### Legacy history

`quan_ly_nhan_su.session.sql`, `database/sql/du_lieu_mau.sql`, các script
`2026_08_12_001`–`006` và `demo/` là legacy history, đã có header đánh dấu và
không phải setup path. Chúng có thể còn symbol/routine/address-table cũ để
đối chiếu lịch sử; không chạy chúng thay cho fresh pair.

## 5. Bản đồ backend để tiếp tục phát triển

Luồng chuẩn là:

    routes/web.php hoặc routes/api.php
      → Form Request/validation
      → Controller
      → NhanVienServiceContract/NhanVienService
      → NhanVienRepositoryContract/NhanVienRepository
      → database/tao_bang.sql + database/du_lieu_mau.sql (fresh), hoặc migration
      → Feature/Unit/MariaDB integration tests

Các điểm vào chính:

- Web routes và middleware: routes/web.php; auth/session ở app/Http/Controllers/Auth/AuthenticatedSessionController.php và app/Auth/NhanVienUserProvider.php.
- Employee lifecycle: app/Http/Controllers/Backend/NhanVienController.php, app/Http/Requests/ListNhanVienRequest.php, StoreNhanVienRequest.php, UpdateNhanVienRequest.php, app/Services/NhanVienService.php, app/Repositories/NhanVienRepository.php.
- Permission/rollout/target guard: app/Enums/PermissionAction.php,
  app/Enums/NhanVienPermission.php, app/Enums/PhongBanPermission.php,
  config/permissions.php, app/Authorization/PermissionRegistry.php,
  app/Services/PermissionService.php, app/Repositories/PermissionRepository.php,
  config/nhanvien.php, app/Http/Middleware/EnsureNhanVienModuleEnabled.php và
  app/Support/NhanVienTargetGuard.php.
- SQL contract active: `database/tao_bang.sql` + `database/du_lieu_mau.sql`; direct
  Query Builder employee/auth/RBAC path is covered by the Unit/Feature suites and
  the new guarded `FreshEmployeeSchemaContractTest`. The older
  `tests/Integration/MariaDb/*ProcedureTest.php`, legacy fixture and native
  procedure workers are historical Task 12–20 evidence only; they target the
  superseded routine/address-table contract and are not an active setup or
  acceptance path for the 15-table source.
- Attendance compatibility: /api/v1/cham-cong/nhan-vien đi qua ChamCongController::employees và NhanVienServiceContract; chi tiết /api/v1/cham-cong dùng Query Builder trên cột canonical cham_cong.ngay_lam.

Các constraint không được phá vỡ:

- Transaction hồ sơ + địa chỉ + avatar thuộc service và cùng write connection.
- Repository dùng explicit Query Builder projections trên cùng connection;
  không gọi employee procedure/view/trigger.
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
    php artisan test tests/Feature/Auth/EmployeeAuthenticationTest.php tests/Feature/Backend/NhanVien tests/Unit/Auth/NhanVienUserProviderTest.php tests/Unit/Models/NhanVienTest.php tests/Unit/Services/PermissionServiceTest.php
    php artisan test tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php
    npm run test:frontend
    npm run build
    composer validate --no-check-publish
    git diff --check
    git status --short

Evidence hiện tại của slice này: route inventory 58; full Laravel
`318 pass, 2389 assertions`; schema contract static `4 pass, 93 assertions`;
attendance/leave compatibility và employee/auth scoped tests đều pass; guarded
MariaDB fresh contract `11 tests, 344 assertions` pass, gồm profile-edit/status-race/department projection,
Chức vụ/Phòng ban direct CRUD/count và parallel counter.
Browser avatar chưa chạy.

MariaDB fresh-contract integration phải dùng wrapper guarded và switch bắt buộc,
không trỏ vào database live:

    pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
    php artisan test tests/Integration/MariaDb/FreshEmployeeSchemaContractTest.php

Wrapper yêu cầu `MARIADB_TEST_*`, chỉ cho phép disposable target, khôi phục
process environment và trả exit code test. phpunit.xml mặc định dùng SQLite
in-memory nên suite Laravel xanh không chứng minh MariaDB DDL/FK behavior;
MariaDB fresh-contract test mới là bằng chứng DB hẹp. Browser acceptance vẫn
phải chạy riêng; không báo browser pass nếu chưa chạy browser thật.

## 8. Checklist cộng tác

- Đọc AGENTS.md, handoff, status, database và docs liên quan trước khi sửa.
- Xác nhận branch/HEAD/status live; làm trên task branch và chỉ một writer/worktree cho mỗi file scope.
- Khóa contract route/request/controller/data trước khi code; dùng vertical slice nhỏ và commit atomic có message rõ mục đích.
- Không fetch, push, force-push, rebase, merge hoặc tạo worktree nếu chưa được giao rõ; không stage .env, secrets, vendor, node_modules, public/build, backup ignored hoặc docs/CODEX_FRONTEND_HANDOFF.md.
- Cập nhật guide/status/handoff khi trạng thái thay đổi; giữ lịch sử Task 20 là historical nếu số liệu không được revalidate.
- Báo riêng verified hẹp, prototype, blocked, planned và unverified; không suy từ response 200, route list, test SQLite, Vite build hoặc seed thành nghiệp vụ production hoàn thành.
- Trước handoff, review staged diff/secret scan, link Markdown nội bộ, test focused rồi broader suite phù hợp; ghi rõ command, exit/result, giới hạn và bước tiếp theo. Không push thay cho reviewer/lead.
