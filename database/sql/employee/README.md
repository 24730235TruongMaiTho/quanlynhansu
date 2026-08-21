# Employee database scripts

Các script trong thư mục này nâng cấp schema nhân viên theo thứ tự tên file. Chúng chạy trên database đang được chọn và không tự `DROP DATABASE`, `CREATE DATABASE` hoặc `USE`.

## `2026_08_12_001_schema.sql`

Script nền tảng cho MariaDB 10.4:

- preflight toàn bộ dữ liệu legacy trước DDL, gồm mã chỉ trong `NV001..NV999`;
- chuẩn hóa email bằng `LOWER(TRIM(email))` và CCCD bằng `TRIM(cccd)`;
- thêm ký hiệu trạng thái/vai trò, role hệ thống zero-quyền, avatar và ngày nghỉ việc;
- thêm unique email/CCCD, địa chỉ một-một cascade và bộ đếm mã không giảm;
- recreate view dùng chung bằng danh sách cột tường minh, không có `mat_khau`.

Các nhãn hệ thống trạng thái/vai trò được so sánh bằng `LOWER(TRIM(...))` rồi `BINARY`, tránh tự nhận biến thể gần giống nhưng sai dấu dưới collation `utf8mb4_unicode_ci`.

Các lỗi preflight chỉ dùng whitelist: `NV_MIGRATION_EMAIL_INVALID`, `NV_MIGRATION_CCCD_INVALID` (gồm cả mã nhân viên ngoài `NV001..NV999`), `NV_MIGRATION_STATUS_AMBIGUOUS`, `NV_MIGRATION_ROLE_AMBIGUOUS`, `NV_MIGRATION_EXISTING_TERMINATION_DATE_REQUIRED`.

Trước khi chạy DDL trên database đã có dữ liệu, phải tạo backup bằng `mariadb-dump` và chạy preflight. Không chạy canonical dump trên database cần giữ dữ liệu.

## `2026_08_12_002_read_routines.sql`

Script read contract cho MariaDB 10.4:

- thay danh sách/tìm kiếm nhân viên legacy bằng `sp_nhan_vien_danh_sach_phan_trang` có OUT total, filter và thứ tự `ma_nv ASC` ổn định;
- viết lại `sp_nhan_vien_chi_tiet` với cột hồ sơ/địa chỉ tường minh và địa chỉ nullable;
- bổ sung `sp_cham_cong_nhan_vien_phan_trang`, tổng hợp đúng tháng/năm theo quy tắc giờ `>= 8` là một ngày, `>= 4` là nửa ngày;
- khóa shape lookup dùng chung cho phòng ban, chức vụ, vai trò và trạng thái làm việc;
- không dùng dynamic SQL và không tự `DROP DATABASE`, `CREATE DATABASE` hoặc `USE`.

Chạy sau script `001` trên cùng database đã được chọn. Script không tự chọn database và không mở transaction cho toàn bộ chuỗi nâng cấp.

## `2026_08_12_003_create_routines.sql`

Script mutation tạo nhân viên cho MariaDB 10.4:

- thay `sp_nhan_vien_them` legacy bằng contract 15 `IN` + một `OUT`, tự cấp mã tuần tự dưới row lock và không nhận vai trò từ client;
- gán đúng role hệ thống `NHAN_VIEN_MAC_DINH` khi role tồn tại duy nhất và không có quyền;
- nhận nguyên hash Laravel, không nhận hoặc hash mật khẩu plaintext;
- chuẩn hóa email/CCCD và fail closed bằng mã lỗi `NV_*` ổn định;
- thêm `sp_dia_chi_nhan_vien_luu` để upsert địa chỉ một-một;
- không procedure nào tự mở, commit hoặc rollback transaction.

Chạy sau script `001` và `002`. Transaction tạo hồ sơ + địa chỉ thuộc về service Laravel trên cùng default connection.

## `2026_08_12_004_update_routines.sql`

Script mutation cập nhật hồ sơ nhân viên cho MariaDB 10.4:

- thay `sp_nhan_vien_sua` legacy bằng contract 14 `IN`, giữ nguyên mã, vai trò, hash, avatar và ngày nghỉ việc;
- khóa target rồi kiểm tra exact role `NHAN_VIEN_MAC_DINH` trước mọi validation còn lại;
- chỉ cho chuyển đổi giữa `DANG_LAM` và `THU_VIEC`, hoặc giữ nguyên cặp trạng thái/ngày nghỉ hợp lệ của nhân viên `DA_NGHI`;
- thêm `sp_nhan_vien_cap_nhat_anh` để thay/xóa đường dẫn avatar và trả đường dẫn cũ qua `OUT` cho cleanup sau commit;
- không procedure nào tự mở, commit hoặc rollback transaction.

Chạy sau script `001`, `002` và `003`. Transaction hồ sơ + địa chỉ + avatar thuộc về service Laravel trên cùng default connection; filesystem cleanup chỉ diễn ra sau commit hoặc như bù trừ rollback.

## `2026_08_12_005_lifecycle_auth_routines.sql`

Script lifecycle/auth cho MariaDB 10.4, chạy sau `001`–`004`:

- drop dứt điểm `sp_nhan_vien_xoa` và `sp_nhan_vien_dang_nhap`, sau đó tạo `sp_nhan_vien_xoa_hoac_nghi_viec` với hai `OUT` (`DELETED`/`TERMINATED`, avatar cũ);
- khóa nhân viên trước exact-role guard, kiểm tra đủ năm bảng dependency, resolve `DA_NGHI` bằng ký hiệu và giữ nguyên ngày nghỉ đầu tiên khi gọi lại;
- tách reset hash web cho role `NHAN_VIEN_MAC_DINH` khỏi compare-and-swap hash xác thực áp dụng mọi role; cả hai chỉ nhận hash đã tạo ở Laravel và không trả hash/plaintext;
- lookup tài khoản trim + case-insensitive exact theo mã/email, trả đúng sáu cột server-only kể cả trạng thái `DA_NGHI` để provider từ chối session ở task auth sau;
- không routine nào tự mở, commit hoặc rollback transaction; `NhanVienRepository` giữ SET/CALL/OUT trên cùng write connection.

Lifecycle/auth đã được tích hợp với custom Laravel provider, session guard, lifecycle/reset service/routes và RBAC Gates trong Tasks 14–18. Lookup/hash CAS vẫn chỉ đi qua repository/provider; controller/UI không nhận hoặc trả hash. Bằng chứng tổng hợp mới nhất nằm ở mục Task 20 bên dưới; các số Task 13 cũ chỉ là lịch sử component, không phải gate hiện tại.

## `2026_08_12_006_rbac.sql`

Script RBAC cho MariaDB 10.4, chạy sau `001`–`005`:

- fail-closed toàn bộ permission ID/symbol, orphan mapping, hai foreign key và role `NHAN_VIEN_MAC_DINH` trước DDL; không tự dọn drift;
- chuẩn hóa symbol bằng `UPPER(TRIM(...))`, đổi `quyen.ma_quyen` thành positive `AUTO_INCREMENT`, thêm unique symbol và recreate duy nhất `fk_vai_tro_quyen_quyen` với đúng `UPDATE_RULE/DELETE_RULE` đã preflight, giữ nguyên FK role;
- seed idempotent theo symbol năm quyền `NHAN_VIEN_XEM`, `NHAN_VIEN_TAO`, `NHAN_VIEN_SUA`, `NHAN_VIEN_XOA` và `NHAN_VIEN_DAT_LAI_MAT_KHAU`; role mặc định luôn zero mapping;
- cung cấp lookup explicit/deterministic và các routine gán/xóa mapping, xóa role an toàn, assignment bootstrap nội bộ; các routine mutation không tự mở/commit/rollback transaction và `sp_vai_tro_xoa` không bao giờ xóa nhân viên;
- canonical dump `quan_ly_nhan_su.session.sql` phải giữ cùng table shape, seed, FK và routine definitions.

Chỉ chạy sau `001`–`005`. Trên database hiện hữu, dùng quy trình backup/preflight ở dưới; không chạy canonical dump vì dump bắt đầu bằng `DROP DATABASE`.

## Nâng cấp database hiện hữu và seed demo tùy chọn

`quan_ly_nhan_su.session.sql` là canonical dump để dựng database disposable mới và có lệnh `DROP DATABASE IF EXISTS`. Không dùng file đó để cập nhật database hiện hữu cần giữ dữ liệu. Schema canonical hiện đã khớp các script employee `001`–`006`; dữ liệu demo được tách riêng để không làm canonical dump chứa tài khoản/mật khẩu demo.

Các script chạy trên database đã được chọn, không tự `USE`, `CREATE DATABASE` hoặc `DROP DATABASE`. Các lệnh direct rollout dưới đây chỉ dành cho local/dev hoặc môi trường đã được phê duyệt; shared/production cần backup đã kiểm tra, cửa sổ maintenance và approval riêng. Chạy từ repository root, sau khi xác nhận `.env` trỏ đúng target. `127.0.0.1`, `3306` và `root` chỉ là ví dụ khớp `.env` local hiện tại; máy khác phải dùng đúng `DB_HOST`, `DB_PORT`, `DB_USERNAME` và policy credential của môi trường đó, không mặc định `root`.

Với MariaDB CLI trên Windows, chạy từng file theo thứ tự `001` → `006`:

```powershell
$mysqlPwdWasPresent = Test-Path Env:MYSQL_PWD
$mysqlPwdPrevious = $env:MYSQL_PWD
function Invoke-EmployeeSql([string] $Path) {
    & mariadb --abort-source-on-error --protocol=tcp --host=127.0.0.1 --port=3306 --user=root --database=quan_ly_nhan_su --execute="source $Path"
    if ($LASTEXITCODE -ne 0) { throw "employee SQL failed: $Path" }
}
try {
    $env:MYSQL_PWD = '<local-password>'
    Invoke-EmployeeSql 'database/sql/employee/2026_08_12_001_schema.sql'
    Invoke-EmployeeSql 'database/sql/employee/2026_08_12_002_read_routines.sql'
    Invoke-EmployeeSql 'database/sql/employee/2026_08_12_003_create_routines.sql'
    Invoke-EmployeeSql 'database/sql/employee/2026_08_12_004_update_routines.sql'
    Invoke-EmployeeSql 'database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql'
    Invoke-EmployeeSql 'database/sql/employee/2026_08_12_006_rbac.sql'
}
finally {
    if ($mysqlPwdWasPresent) {
        $env:MYSQL_PWD = $mysqlPwdPrevious
    }
    else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}
```

DDL MariaDB có thể autocommit, nên phải dừng ngay nếu một file lỗi và giữ backup để phục hồi theo quy trình riêng. Trước khi chạy, xác nhận `DATABASE()='quan_ly_nhan_su'`, `@@global.read_only=0`, đúng version MariaDB, row counts và schema target. Backup đầy đủ nên dùng `mariadb-dump --single-transaction --routines --triggers --events` vào thư mục ignored; không ghi credential vào command log.

Sau `006`, seed demo là tùy chọn:

- `demo/2026_08_21_001_demo_seed.sql` tạo dữ liệu synthetic khoảng 5 nhân viên, 5 địa chỉ, 5 phòng ban/chức vụ, role `DEMO_QUAN_TRI_NHAN_VIEN` và đúng 5 quyền employee; email dùng `example.test`, phone/CCCD deterministic. Seed có guard target/schema/routine, transaction rollback và rerun idempotent. Hồ sơ/địa chỉ và mapping dùng các routine employee chính thức khi có contract phù hợp.
- `demo/2026_08_21_002_demo_cleanup.sql` chỉ xóa đúng synthetic identities/masters/role của bộ trên rồi commit; không xóa quyền hệ thống và không giảm/reuse `bo_dem_ma_nhan_vien`. Rerun seed sẽ tạo lại dữ liệu với mã mới nếu counter đã tăng.

Ví dụ chạy seed hoặc cleanup trên selected DB:

```powershell
$mysqlPwdWasPresent = Test-Path Env:MYSQL_PWD
$mysqlPwdPrevious = $env:MYSQL_PWD
function Invoke-EmployeeSql([string] $Path) {
    & mariadb --abort-source-on-error --protocol=tcp --host=127.0.0.1 --port=3306 --user=root --database=quan_ly_nhan_su --execute="source $Path"
    if ($LASTEXITCODE -ne 0) { throw "employee SQL failed: $Path" }
}
try {
    $env:MYSQL_PWD = '<local-password>'
    Invoke-EmployeeSql 'database/sql/employee/demo/2026_08_21_001_demo_seed.sql'
    # Khi cần dọn bộ synthetic:
    # Invoke-EmployeeSql 'database/sql/employee/demo/2026_08_21_002_demo_cleanup.sql'
}
finally {
    if ($mysqlPwdWasPresent) {
        $env:MYSQL_PWD = $mysqlPwdPrevious
    }
    else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}
```

Tài khoản demo quản trị: `demo.admin@employee.example.test` / `nhom3@2026`. Đây là credential chỉ dành cho local/demo; phải đổi hoặc xóa trước khi chia sẻ, deploy hoặc đưa vào môi trường thật. Không ghi plaintext password vào database; seed chỉ chứa bcrypt tương thích Laravel.

Sau seed, kiểm tra read-only: đúng 5 employee và 5 address, có cả `DANG_LAM` và `THU_VIEC`, admin role đúng 5 permission, employee role mặc định có 0 permission, không có routine tạm, counter không giảm sau cleanup, và kiểm tra mật khẩu bằng `password_verify`/`Hash::check` mà không in hash. Integration/browser acceptance vẫn cần gate riêng; seed thành công không thay thế browser verification.

## Evidence cập nhật Task 20 (2026-08-21)

Full guarded wrapper đã chạy qua `tests/Support/invoke-employee-mariadb-tests.ps1
-EnableDisposableMariaDb`: `165 tests, 3367 assertions, 1 platform skip, exit 0`.
Skip duy nhất do Windows từ chối tạo disposable state symlink; không phải skip
procedure nghiệp vụ.
Các scripts `001`–`006` và canonical dump được replay trong database
disposable; schema cleanup sau run là `0`. Acceptance/browser harness cũng chỉ
dùng target guarded, state và upload prefix theo run; official Stop đã dọn
schema, state, lock, temp, upload, listener và link acceptance về `0`, giữ lại
`storage/app/public` dùng chung. Không claim MySQL 8 hoặc database live.

Kết quả browser employee là verified hẹp cho login/session, CRUD, auth/RBAC,
filter/flash/edit mapping, stale error, console và responsive widths.
Avatar upload/replacement vẫn **blocked/unverified** vì Chrome extension không
cho file URL access; automated avatar tests vẫn là bằng chứng riêng, không thay
browser file chooser.
