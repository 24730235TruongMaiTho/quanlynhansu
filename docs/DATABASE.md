# Database và hợp đồng 15 bảng

> Snapshot kiểm tra: 2026-08-24

> **Nguồn fresh active:** chạy `database/tao_bang.sql` rồi
> `database/du_lieu_mau.sql` trên database disposable. Hai file này tạo đúng
> 15 bảng và không yêu cầu view, trigger, function hay stored procedure cho
> module Nhân viên/auth/RBAC. `quan_ly_nhan_su.session.sql` và các script
> employee `2026_08_12_001`–`006` là legacy history, không dùng làm setup path.
>
> Runtime tham chiếu: MariaDB 10.4.32, schema `quan_ly_nhan_su` (rollout đã được kiểm chứng environment-specific; guarded disposable integration Task 20 là historical, rerun sau tích hợp timeout khoảng 184 giây và cleanup sạch)
>
> Thao tác lịch sử Task 20 chỉ còn để đối chiếu caller/SQL/test; không dùng
> routine dump cũ làm production acceptance.

## Nguồn schema

Dump `quan_ly_nhan_su.session.sql` bên dưới được giữ để truy nguyên legacy; nó
không phải nguồn fresh active. Counts của hợp đồng hiện hành:

| Loại object | Số lượng |
| --- | ---: |
| Bảng | 15 |
| View/function/trigger bắt buộc | 0 |
| Stored procedure bắt buộc cho employee/auth/RBAC | 0 |

## Historical rollout (environment-specific, 2026-08-21)

Local rollout cũ của `quan_ly_nhan_su` ghi nhận 16 bảng/routines; đó là bằng
chứng environment-specific của schema legacy, không phải nguồn fresh và không
được chạy lại. Fresh pair hiện hành được mô tả ở phần Nguồn schema; MariaDB
runtime vẫn cần gate disposable riêng.

Với DB legacy đang có 16 bảng, migration SQL
`database/sql/employee/2026_08_24_001_migrate_to_fifteen_tables.sql` là runbook
preflight semantic/copy-verify-drop có backup; nó bổ sung quyền 105 và mapping
admin/HR mà không xóa mapping khác; nếu thiếu, nó cũng provision canonical
quyền phòng ban `201–204` và chức vụ `301–304`, rồi thêm role 2 đúng 17 quyền
`101–105, 201–204, 301–304, 401–404`. Các mapping module khác của DB hiện hữu
không bị revoke. MariaDB DDL implicit commit nên không giả vờ atomic và không tự
chạy trên DB live. Sau postcheck, cleanup phải đi qua
`2026_08_24_002_cleanup_legacy_employee_objects.sql`, một allowlist riêng chỉ
xóa view/routine employee cũ và có postcheck giữ nguyên routine module khác.

Guarded MariaDB integration hiện đã replay fresh pair, kiểm tra migration từ
fixture 16 bảng, cleanup allowlist, Department CRUD trực tiếp và hai worker
direct repository cấp mã đồng thời: `6 tests, 196 assertions` trên disposable
database. Đây là verified hẹp,
không phải bằng chứng live/production hay MySQL 8.

Ba migration Laravel chỉ tạo users/password reset/session, cache và queue/jobs. Database live chưa có bảng `migrations` và các migration này chưa chạy.

## Mô hình dữ liệu chính

```text
phong_ban ─┐
chuc_vu ───┼── nhan_vien (địa chỉ/avatar/ngày nghỉ trực tiếp)
vai_tro ───┤                ├── hop_dong ── loai_hop_dong
trang_thai_lam_viec ────────┤
                            ├── nghi_phep ── loai_phep
                            ├── cham_cong
                            ├── lich_su_he_so_luong
                            └── luong

bo_dem_ma_nhan_vien ── cấp mã tuần tự NV001..NV999

vai_tro ── vai_tro_quyen ── quyen
```

Danh sách bảng:

`phong_ban`, `chuc_vu`, `vai_tro`, `quyen`, `vai_tro_quyen`,
`trang_thai_lam_viec`, `nhan_vien`, `bo_dem_ma_nhan_vien`, `loai_hop_dong`, `hop_dong`,
`loai_phep`, `nghi_phep`, `cham_cong`, `lich_su_he_so_luong`, `luong`.

Không có view bắt buộc trong nguồn fresh.

## Historical legacy procedure inventory (not active employee source)

| Nhóm | Số lượng |
| --- | ---: |
| Phòng ban | 5 |
| Chức vụ | 4 |
| Vai trò | 4 |
| Quyền, gán quyền và assignment nội bộ | 8 |
| Trạng thái làm việc | 1 |
| Nhân viên và địa chỉ | 10 |
| Hợp đồng và loại hợp đồng | 10 |
| Nghỉ phép và loại phép | 8 |
| Chấm công | 6 |
| Lịch sử hệ số lương | 5 |
| Lương | 7 |
| Backup/restore | 2 |

Các số liệu dưới đây chỉ là inventory legacy, không phải requirement của fresh
employee/auth/RBAC. Tồn tại object không đồng nghĩa procedure đã được kiểm thử
nghiệp vụ. Các file `tests/Integration/MariaDb/*ProcedureTest.php`, legacy
fixture và native procedure workers cũng chỉ là historical Task 12–20 evidence;
chúng target schema/routine/address-table cũ và không thuộc acceptance path hiện
hành của fresh 15-table source. Không gọi routine ghi trên production hoặc
database cần giữ dữ liệu nếu chưa có backup/preflight/approval.

## Registry procedure PHP ngoài module employee

Snapshot dưới đây chỉ inventory caller legacy/non-employee để xử lý riêng. Module
employee/auth/RBAC hiện hành không gọi procedure; bảng không phải acceptance
contract của fresh 15-table source:

| Procedure | Tham số theo caller | Trạng thái trong dump/live DB | Contract kết quả |
| --- | --- | --- | --- |
| `sp_phong_ban_danh_sach` | Không | Legacy only; Department repository không gọi | Row `ma_pb`, `ten_pb`, `so_nhan_vien` |
| `sp_phong_ban_them` | `ten_pb` | Legacy only; Department repository không gọi | Write, không result set |
| `sp_phong_ban_sua` | `ma_pb, ten_pb` | Legacy only; Department repository không gọi | Write, không result set |
| `sp_phong_ban_xoa` | `ma_pb` | Legacy only; Department repository không gọi | Write, không result set |
| `sp_phong_ban_chi_tiet` | `ma_pb` | Legacy only; Department repository không gọi | Row `ma_pb`, `ten_pb`, `so_nhan_vien` |
| `sp_cham_cong_nhan_vien_phan_trang` | filter + page/per-page + `OUT total` | Có; read routines 002 và dump canonical | Aggregate nhân viên an toàn + tổng số |
| `sp_cham_cong_cap_nhat` | `ma_cc, ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som` | Có | Write; caller đọc lại table |
| `sp_luong_tim_kiem_phan_trang` | `ma_nv, ky_luong, ma_pb, ma_cv, page, per_page` | **Thiếu** | Mỗi row phải có `total_count` |
| `sp_chuc_vu_danh_sach` | Không | Có | Row `ma_cv`, `ten_cv`, `he_so_phu_cap` |
| `sp_trang_thai_lam_viec_danh_sach` | Không | Có | Row `ma_tt`, `ky_hieu`, `ten_tt` |
| `sp_nghi_phep_duyet_phep` | `ma_np, ma_nv, trang_thai_duyet` | Có | Write, không result set |

`sp_luong_tim_kiem_phan_trang` vẫn là procedure thiếu ngoài phạm vi Phòng ban.
Chấm công chi tiết đã có Query Builder contract; không suy rộng các routine
chưa có trong dump thành đã hoàn tất.

### Contract Phòng ban v1 — legacy procedure appendix (2026-08-22)

Phần này chỉ mô tả lịch sử routine để truy nguyên; nó không phải active
contract của module Phòng ban. Active repository dùng Query Builder trực tiếp
trên `phong_ban` và `nhan_vien` của fresh pair, không cần routine/view/trigger.

Script `database/sql/department/2026_08_22_001_department_contract.sql` chạy sau
schema nền trên target do caller chọn, không tự `USE`, `DROP/CREATE DATABASE`,
map role. Trước contract routine/table DDL, preflight fail-closed phát hiện
`NULL`, rỗng, chưa trim hoặc duplicate theo collation hiện tại và dừng mà không
tự sửa dữ liệu. Sau khi preflight pass, script thêm idempotent unique index
`uq_phong_ban_ten_pb` trên `ten_pb`. Năm routine có chữ ký:

`ALTER TABLE` là DDL có thể implicit-commit trên MariaDB; caller phải chạy
rollout ở target disposable/đã được phê duyệt và có backup phù hợp, không kỳ
vọng transaction caller sẽ rollback được việc thêm index.

- `sp_phong_ban_danh_sach()` → các cột `ma_pb`, `ten_pb`, `so_nhan_vien`, sắp xếp `ma_pb ASC`.
- `sp_phong_ban_chi_tiet(ma_pb)` → cùng shape một dòng; thiếu/ID không hợp lệ trả `PB_NOT_FOUND`/`PB_ID_INVALID`.
- `sp_phong_ban_them(ten_pb)`, `sp_phong_ban_sua(ma_pb, ten_pb)` và `sp_phong_ban_xoa(ma_pb)` → không result set; normalize trim, giới hạn 100 ký tự và mã lỗi `PB_NAME_REQUIRED`, `PB_NAME_TOO_LONG`, `PB_NAME_DUPLICATE`, `PB_IN_USE`.

`sp_phong_ban_xoa` kiểm tra dependency `nhan_vien` trước DELETE và foreign key
vẫn là enforcement cuối. Tên trùng được routine kiểm tra và database unique
index enforcement; repository map duplicate-key race về `PB_NAME_DUPLICATE`.
Integration test guarded cho shape, normalize, duplicate, missing, dependency,
unique constraint qua connection độc lập, repository write cursor-drain,
preflight refusal và catalog permission đã có trong các test MariaDB, nhưng
chưa chạy trong phiên 2026-08-22.

Fresh seed dùng bốn symbol `PB_VIEW`, `PB_CREATE`, `PB_EDIT`, `PB_DELETE` với
`ma_quyen` 201–204 và module `PhongBan`. Script routine phòng ban bên trên là
legacy history; không dùng nó để provision catalog cho fresh 15-table source.

### Active Department repository contract (2026-08-24)

`App\Repositories\PhongBanRepository` dùng connection mặc định của Laravel và
Query Builder trực tiếp. `all()`/`find()` chọn tường minh `pb.ma_pb`,
`pb.ten_pb` và `COUNT(nv.ma_nv) AS so_nhan_vien`, `LEFT JOIN` nhân viên, group
theo phòng ban và sắp xếp `ma_pb ASC`; không dùng `SELECT *` hoặc `CALL`.
Create/update/delete trim tên, giới hạn 100 ký tự, dùng transaction; update và
delete khóa target bằng `lockForUpdate()`. Unique database violation map về
`PB_NAME_DUPLICATE`, dependency map về `PB_IN_USE`, target thiếu map về
`PB_NOT_FOUND`, lỗi khác dùng thông báo generic. SQLite tests bảo vệ outcome;
MariaDB fresh proof nằm trong `FreshEmployeeSchemaContractTest` và phải chạy
qua disposable guard.

## Setup local an toàn

### 1. Chốt DBMS

Máy audit dùng MariaDB 10.4.32. Nếu nhóm chọn MySQL 8, phải clean-replay dump và chạy integration test riêng trước khi ghi “tương thích”.

Baseline hiện đọc `APP_TIMEZONE=Asia/Ho_Chi_Minh` trong `config/app.php` và `DB_TIMEZONE=+07:00` cho connection MySQL/MariaDB. Môi trường triển khai vẫn phải giữ hai giá trị này đồng bộ trước các logic dùng `now()`/`CURDATE()`.

### 2. Cấu hình `.env`

`.env.example` đã được đồng bộ với baseline nghiệp vụ: Laravel `mysql` driver, timezone PHP/DB và file/sync cho hạ tầng chưa migrate. Chỉ cần điền credential local được phép dùng:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quan_ly_nhan_su
DB_USERNAME=<tai-khoan-local>
DB_PASSWORD=<mat-khau-local>
DB_TIMEZONE=+07:00

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Không ghi credential thật vào Git.

### 3. Kiểm tra target trước import

Dump bắt đầu bằng:

```sql
DROP DATABASE IF EXISTS quan_ly_nhan_su;
CREATE DATABASE quan_ly_nhan_su CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import sẽ xóa toàn bộ database cùng tên. Chỉ dùng database local/disposable và sao lưu dữ liệu cần giữ bằng công cụ MariaDB/MySQL trước.

### 4. Import

Dùng MariaDB/MySQL client, phpMyAdmin, Workbench hoặc chế độ chạy SQL script của IDE. Dump dùng `DELIMITER`, nên không gửi toàn file như một query JDBC duy nhất.

Không chạy migrations trước import vì dump sẽ xóa database. Auth hiện dùng custom provider trên `nhan_vien`; migration `users` sẽ tạo kho identity thứ hai không được ứng dụng dùng. Nếu cần bảng session/cache/jobs, phải tách/chọn migration hạ tầng có chủ đích sau import, không chạy toàn bộ theo quán tính.

### 5. Seed fresh deterministic

`database/du_lieu_mau.sql` seed explicit master IDs and the 29 permission IDs
across the `101–802` module ranges,
statuses 1–4, 30 Vietnamese employee rows from the project Git sample,
`NV001` (`Nguyễn Văn An`, `ma_vt = 1`, `ma_tt = 2`,
`an.nguyen@company.com`) with a valid bcrypt hash, and counter 30. All 30
rows use the local/demo password convention `nhom3@2026`; do not use it in
production.
Run it only after `database/tao_bang.sql` on an empty/disposable database.
`employee:bootstrap-demo` remains guarded and must never target live data.

Không chạy `php artisan db:seed` cho tới khi có model và seeder hợp lệ.

### 6. Preflight read-only

```powershell
php artisan about --only=environment,drivers
php artisan migrate:status
php artisan route:list --except-vendor
```

`about` chỉ cho biết driver cấu hình, không chứng minh kết nối DB. `migrate:status` hiện được kỳ vọng trả lỗi `Migration table not found` cho tới khi nhóm tạo bảng migrations.

Chạy các query read-only sau bằng database client/IDE:

```sql
SELECT
    VERSION() AS db_version,
    DATABASE() AS current_database,
    @@character_set_database AS database_charset,
    @@collation_database AS database_collation,
    @@global.time_zone AS global_time_zone,
    @@session.time_zone AS session_time_zone;

SELECT TABLE_TYPE, COUNT(*) AS object_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
GROUP BY TABLE_TYPE;

SELECT ROUTINE_TYPE, COUNT(*) AS object_count
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
GROUP BY ROUTINE_TYPE;

SELECT COUNT(*) AS trigger_count
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE();

SELECT expected.procedure_name AS missing_procedure
FROM (
    SELECT 'sp_cham_cong_nhan_vien_phan_trang' AS procedure_name
    UNION ALL SELECT 'sp_luong_tim_kiem_phan_trang'
) AS expected
LEFT JOIN information_schema.ROUTINES AS actual
    ON actual.ROUTINE_SCHEMA = DATABASE()
   AND actual.ROUTINE_TYPE = 'PROCEDURE'
   AND actual.ROUTINE_NAME = expected.procedure_name
WHERE actual.ROUTINE_NAME IS NULL;
```

`php artisan db:show` trên MariaDB local hiện lỗi vì thiếu `performance_schema.session_status`, dù ping/query trực tiếp vẫn chạy. Không dùng riêng lỗi này để kết luận mất kết nối.

## Lệch hợp đồng code ↔ database (non-employee legacy callers)

Các caller procedure bên dưới thuộc module ngoài employee/auth/RBAC và vẫn là
legacy drift cần xử lý riêng:

| Procedure thiếu | Caller chính | Hậu quả |
| --- | --- | --- |
| `sp_luong_tim_kiem_phan_trang` | `LuongRepository@all` | API danh sách lương trả lỗi |

Department repository không còn caller routine. `ChamCongController` vẫn có
lookup procedure legacy riêng; việc thay caller đó thuộc phạm vi Chấm công và
chưa được thực hiện trong lát cắt này.

## Rủi ro dữ liệu và bảo mật

- View nhân viên hiện dùng danh sách cột tường minh, không chứa `mat_khau`; vẫn không trả nguyên row chưa rà soát ra UI/API.
- Mật khẩu legacy có thể là hash SHA-256 không salt; contract `sp_nhan_vien_sua` mới không nhận mật khẩu và không reset hash khi cập nhật hồ sơ.
- `sp_quyen_them` không cung cấp `ma_quyen` dù cột không auto-increment.
- `sp_vai_tro_xoa` xóa nhân viên thuộc vai trò trước khi xóa vai trò.
- `sp_cham_cong_import` luôn `SIGNAL` lỗi; đây là placeholder.
- `sp_cham_cong_export` dùng `INTO OUTFILE`, phụ thuộc quyền `FILE`, `secure_file_priv` và đường dẫn server.
- `sp_database_sao_luu`/`sp_database_khoi_phuc` sinh `BACKUP DATABASE`/`RESTORE DATABASE`, là cú pháp SQL Server và không chạy trên MariaDB/MySQL.
- Bảng `luong` chưa unique `(ma_nv, ky_luong)`; có nguy cơ trùng kỳ.
- `sp_luong_them` chưa chặn lương trùng kỳ và có nhánh thông báo sai nghĩa khi thiếu lịch sử hệ số; cả read và write contract lương đều cần test lại.
- Các function ngày công dùng quy tắc 8/4 giờ chưa hoàn toàn thống nhất.
- Trigger nghỉ phép kiểm tra theo tháng bắt đầu thay vì đúng khoảng ngày giao nhau.
- Nhiều unique nghiệp vụ chỉ được kiểm tra trong procedure, nên vẫn có rủi ro race condition.
- Laravel dùng `now()` theo UTC ở một số filter, trong khi SQL dùng `CURDATE()` theo timezone DB; có thể lệch ngày/tháng lương-chấm công tại ranh giới thời gian.

## Quy tắc thay đổi database

- Không sửa trực tiếp database live rồi quên cập nhật file SQL versioned.
- Mỗi thay đổi routine phải có: chữ ký, mục đích, caller, result shape, error behavior và integration test.
- Dùng database test/disposable cho mọi test ghi dữ liệu.
- Không dùng procedure backup/restore/import/export hiện tại trong web request.
- Không log hoặc trả password hash, SQL credential hay raw DB exception cho client.

### Contract employee-update (2026-08-20)

`database/sql/employee/2026_08_12_004_update_routines.sql` là script versioned để replay sau schema 001, read routines 002 và create routines 003. `sp_nhan_vien_sua` có 14 tham số `IN`; routine khóa target và chỉ cho role `NHAN_VIEN_MAC_DINH`, đồng thời giữ các cột hệ thống. `sp_nhan_vien_cap_nhat_anh` trả avatar cũ qua `OUT` để service xóa sau commit; không routine nào tự mở/commit/rollback transaction. Historical Task 12 guarded disposable integration đã xác minh contract này với `20 tests, 436 assertions`; MySQL 8 chưa được claim.

Task 13 tiếp tục bằng `2026_08_12_005_lifecycle_auth_routines.sql`: hard-delete chỉ khi không có dependency; có dependency thì chuyển exact status `DA_NGHI` và giữ ngày đầu; reset role-default tách khỏi auth hash CAS mọi role; lookup trả đúng sáu cột server-only. Tasks 14–18 đã nối contract này vào lifecycle/reset service/routes, custom auth provider và năm RBAC Gates. Các con số Task 12/13 cũ chỉ là lịch sử component; gate tổng hợp authoritative nằm ở mục 2026-08-21 bên dưới.

### Contract và gate employee hiện tại (2026-08-21)

Scripts `001`–`006`, canonical `quan_ly_nhan_su.session.sql`, repository và
caller tests hiện được kiểm chứng hẹp bằng full guarded wrapper:
Historical Task 20 wrapper đạt `165 tests, 3367 assertions, 1 platform skip,
exit 0`. Rerun sau tích hợp timeout khoảng 184 giây; process/schema/state/marker
cleanup sạch và không claim rerun pass. Compatibility extractor đã
được đồng bộ với routine canonical phân trang; chấm công chi tiết hiện dùng
Query Builder vì routine phân trang chi tiết không tồn tại. Không bỏ qua assertion
và không đổi schema live để làm xanh test. Các routine employee tiếp tục giữ contract
caller-owned transaction, procedure result shape không chứa `mat_khau`, auth
lookup/hash chỉ ở server boundary, và role/permission mutation chỉ qua guarded
flow.

Task20 acceptance dùng `MARIADB_TEST_*` process-scoped và database disposable
được guard theo prefix; không đọc `DB_DATABASE`/`DB_URL` để chọn target và
không ghi credential/hash vào state hoặc report. Sau browser, official Stop đã
cho postcheck schema guarded `0`, state/lock/run/upload/listener/PHP/link `0`;
`storage/app/public` vẫn tồn tại như target dùng chung. MariaDB 10.4.32 là
runtime đã kiểm chứng; MySQL 8 chưa được claim.

## Backlog

### P0

1. Chốt DBMS production: hiện chỉ MariaDB 10.4.32 được kiểm chứng; MySQL 8 chưa có bằng chứng.
2. Thiết kế quy trình rollout/backup/restore không dùng canonical dump phá hủy trên database cần giữ dữ liệu.
3. Version procedure còn thiếu ngoài module nhân viên hoặc thay caller: `sp_luong_tim_kiem_phan_trang`. Chấm công chi tiết đã có caller Query Builder được test contract.
4. Tạo business seeders/master-data tối thiểu cho môi trường triển khai.

### P1

1. Tách dump thành schema, routines và seed hoặc chuyển sang migration/SQL script versioned.
2. Thêm unique/check/index còn thiếu cho kỳ lương và các module ngoài nhân viên.
3. Xử lý các caller procedure legacy còn lại của module ngoài Phòng ban theo
   từng contract riêng; không dùng legacy Department script làm active setup.

## Giới hạn của snapshot

- Canonical dump và scripts employee đã clean-replay/mutation-test trên schema disposable; database `quan_ly_nhan_su` hiện có rollout/demo synthetic được ghi ở mục Current rollout, không phải production acceptance.
- Chưa checksum thân routine giữa live DB và dump; không dùng live DB làm nguồn acceptance.
- Chưa xác minh MySQL 8.
