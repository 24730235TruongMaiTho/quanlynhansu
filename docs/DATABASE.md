# Database và stored procedure

> Snapshot kiểm tra: 2026-08-21
>
> Runtime tham chiếu: MariaDB 10.4.32, schema `quan_ly_nhan_su` (rollout đã được kiểm chứng environment-specific; guarded disposable integration Task 20 là historical, rerun sau tích hợp timeout khoảng 184 giây và cleanup sạch)
>
> Thao tác lịch sử Task 20: đối chiếu caller/SQL/test, hoàn tất acceptance disposable và cleanup. Current rollout/demo được ghi ở mục dưới; không dùng làm production acceptance.

## Nguồn schema

`quan_ly_nhan_su.session.sql` hiện là nguồn schema nghiệp vụ canonical trong Git. Counts dưới đây được đo trực tiếp từ file hiện tại; local rollout đã được kiểm tra riêng và không được suy rộng thành production schema:

| Loại object | Số lượng |
| --- | ---: |
| Bảng | 16 |
| View | 1 |
| Function | 8 |
| Trigger | 10 |
| Stored procedure | 69 |

## Current rollout (environment-specific, 2026-08-21)

Local `quan_ly_nhan_su` đã được cập nhật có chủ đích qua approved rollout và
demo synthetic. Postcheck ghi nhận 16 bảng, 1 view, 8 function, 10 trigger,
69 procedure; demo có 5 employee và 5 address; role admin demo có đúng 5
employee permission và bốn normal demo có zero employee permission. Backup nằm
ngoài Git/ignored. Đây không phải production rollout; MySQL 8 chưa được claim.
Xem [EMPLOYEE_MODULE_GUIDE.md](EMPLOYEE_MODULE_GUIDE.md) cho decision table,
target guard và cleanup.

Ba migration Laravel chỉ tạo users/password reset/session, cache và queue/jobs. Database live chưa có bảng `migrations` và các migration này chưa chạy.

## Mô hình dữ liệu chính

```text
phong_ban ─┐
chuc_vu ───┼── nhan_vien ──┬── dia_chi_nhan_vien
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
`trang_thai_lam_viec`, `nhan_vien`, `dia_chi_nhan_vien`, `bo_dem_ma_nhan_vien`, `loai_hop_dong`, `hop_dong`,
`loai_phep`, `nghi_phep`, `cham_cong`, `lich_su_he_so_luong`, `luong`.

View duy nhất là `vw_danh_sach_nhan_vien_chi_tiet`.

## Nhóm procedure

| Nhóm | Số lượng |
| --- | ---: |
| Phòng ban | 4 |
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

Tồn tại object không đồng nghĩa procedure đã được kiểm thử nghiệp vụ. Historical
employee integration dùng scripts `001`–`006` và canonical dump trên disposable
schema qua guarded integration; approved local rollout/demo là scope riêng. Không
gọi routine ghi trên production hoặc database cần giữ dữ liệu nếu chưa có
backup/preflight/approval.

## Registry các procedure PHP đang gọi

Snapshot caller hiện có 27 tên procedure trong lệnh `CALL` của PHP; bảng dưới đây nêu các contract trọng yếu đã audit và các lệch còn tồn tại:

| Procedure | Tham số theo caller | Trạng thái trong dump/live DB | Contract kết quả |
| --- | --- | --- | --- |
| `sp_phong_ban_danh_sach` | Không | Có | Row `ma_pb`, `ten_pb`, `so_nhan_vien` |
| `sp_phong_ban_them` | `ten_pb` | Có | Write, không result set |
| `sp_phong_ban_sua` | `ma_pb, ten_pb` | Có; caller sai placeholder | Write, không result set |
| `sp_phong_ban_xoa` | `ma_pb` | Có | Write, không result set |
| `sp_phong_ban_chi_tiet` | `ma_pb` | **Thiếu** | Caller kỳ vọng một row phòng ban |
| `sp_cham_cong_nhan_vien_phan_trang` | filter + page/per-page + `OUT total` | Có; read routines 002 và dump canonical | Aggregate nhân viên an toàn + tổng số |
| `sp_cham_cong_cap_nhat` | `ma_cc, ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som` | Có | Write; caller đọc lại table |
| `sp_luong_tim_kiem_phan_trang` | `ma_nv, ky_luong, ma_pb, ma_cv, page, per_page` | **Thiếu** | Mỗi row phải có `total_count` |
| `sp_nhan_vien_them` | 16 tham số theo SQL | Có; caller truyền 16 | Write, không result set |
| `sp_nhan_vien_sua` | 14 `IN`: `ma_nv` + hồ sơ | Có; script update 004 và dump canonical | Write, không result set; giữ mã/vai trò/hash/avatar/ngày nghỉ việc |
| `sp_dia_chi_nhan_vien_luu` | `ma_nv` + 4 phần địa chỉ | Có; script create 003 và dump canonical | Upsert một-một, không result set |
| `sp_nhan_vien_cap_nhat_anh` | `ma_nv, anh_moi`, `OUT anh_cu` | Có; script update 004 và dump canonical | Write + OUT path cũ; không result set |
| `sp_nhan_vien_danh_sach_phan_trang` | filter + page/per-page + `OUT total` | Có; read routines 002 | Row danh sách an toàn + tổng số |
| `sp_nhan_vien_chi_tiet` | `ma_nv` | Có; read routines 002 | Row hồ sơ/địa chỉ tường minh, không hash |
| `sp_chuc_vu_danh_sach` | Không | Có | Row `ma_cv`, `ten_cv`, `he_so_phu_cap` |
| `sp_trang_thai_lam_viec_danh_sach` | Không | Có | Row `ma_tt`, `ky_hieu`, `ten_tt` |
| `sp_nghi_phep_duyet_phep` | `ma_np, ma_nv, trang_thai_duyet` | Có | Write, không result set |

Result shape của hai procedure còn thiếu mới chỉ là kỳ vọng suy ra từ caller, chưa phải hợp đồng đã được nhóm chấp thuận. Chấm công chi tiết đã có Query Builder contract; trước khi bổ sung SQL cho procedure còn lại, cần viết fixture/test khóa tên cột, kiểu dữ liệu, pagination và error behavior.

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

### 5. Không seed ở trạng thái hiện tại

`DatabaseSeeder` vẫn tham chiếu `App\Models\User`, nhưng model không tồn tại. Employee scripts seed các symbol hệ thống/RBAC cần thiết; canonical dump vẫn không có business seed đầy đủ cho phòng ban, chức vụ, loại phép và dữ liệu vận hành.

Audit live cũ trước Tasks 13–20 ghi nhận 14 bảng nghiệp vụ đều 0 row; canonical hiện có 16 bảng và không được suy là đã rollout vào live. Muốn tạo nhân viên hợp lệ phải có phòng ban/chức vụ/master data; command `employee:bootstrap-demo` chỉ được dùng với `--require-disposable` cho fixture guarded.

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
    SELECT 'sp_phong_ban_chi_tiet' AS procedure_name
    UNION ALL SELECT 'sp_cham_cong_nhan_vien_phan_trang'
    UNION ALL SELECT 'sp_luong_tim_kiem_phan_trang'
) AS expected
LEFT JOIN information_schema.ROUTINES AS actual
    ON actual.ROUTINE_SCHEMA = DATABASE()
   AND actual.ROUTINE_TYPE = 'PROCEDURE'
   AND actual.ROUTINE_NAME = expected.procedure_name
WHERE actual.ROUTINE_NAME IS NULL;
```

`php artisan db:show` trên MariaDB local hiện lỗi vì thiếu `performance_schema.session_status`, dù ping/query trực tiếp vẫn chạy. Không dùng riêng lỗi này để kết luận mất kết nối.

## Lệch hợp đồng code ↔ database

Code còn gọi hai procedure không tồn tại trong canonical dump:

| Procedure thiếu | Caller chính | Hậu quả |
| --- | --- | --- |
| `sp_phong_ban_chi_tiet` | `PhongBanController@edit` | Không tải được bản ghi sửa |
| `sp_luong_tim_kiem_phan_trang` | `LuongRepository@all` | API danh sách lương trả lỗi |

`sp_phong_ban_sua(ma_pb, ten_pb)` có hai tham số nhưng controller hiện gọi `CALL sp_phong_ban_sua(?)` rồi truyền hai binding.

Không vá bằng cách tạo procedure đoán mò. Trước tiên chốt response shape/pagination mà JavaScript và repository cần, sau đó:

1. Bổ sung SQL versioned + test; hoặc
2. Đổi code sang procedure hiện có/query builder với contract tương đương.

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
3. Version hai procedure còn thiếu ngoài module nhân viên hoặc thay caller: `sp_phong_ban_chi_tiet`, `sp_luong_tim_kiem_phan_trang`. Chấm công chi tiết đã có caller Query Builder được test contract.
4. Tạo business seeders/master-data tối thiểu cho môi trường triển khai.

### P1

1. Tách dump thành schema, routines và seed hoặc chuyển sang migration/SQL script versioned.
2. Thêm unique/check/index còn thiếu cho kỳ lương và các module ngoài nhân viên.
3. Viết integration test cho procedure PHP của phòng ban/lương/chấm công/nghỉ phép.

## Giới hạn của snapshot

- Canonical dump và scripts employee đã clean-replay/mutation-test trên schema disposable; database `quan_ly_nhan_su` hiện có rollout/demo synthetic được ghi ở mục Current rollout, không phải production acceptance.
- Chưa checksum thân routine giữa live DB và dump; không dùng live DB làm nguồn acceptance.
- Chưa xác minh MySQL 8.
