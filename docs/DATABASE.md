# Database và stored procedure

> Snapshot kiểm tra: 2026-08-20
>
> Runtime tham chiếu: MariaDB 10.4.32, schema `quan_ly_nhan_su` (live không bị mutate; guarded disposable integration employee đã pass hẹp)
>
> Thao tác phiên này: đối chiếu caller/SQL/test và cập nhật contract employee-update; không ghi database live.

## Nguồn schema

`quan_ly_nhan_su.session.sql` hiện là nguồn schema nghiệp vụ chính. Dump và database local có cùng tập tên object:

| Loại object | Số lượng |
| --- | ---: |
| Bảng | 14 |
| View | 1 |
| Function | 8 |
| Trigger | 10 |
| Stored procedure | 63 |

Ba migration Laravel chỉ tạo users/password reset/session, cache và queue/jobs. Database live chưa có bảng `migrations` và các migration này chưa chạy.

## Mô hình dữ liệu chính

```text
phong_ban ─┐
chuc_vu ───┼── nhan_vien ──┬── hop_dong ── loai_hop_dong
vai_tro ───┤                ├── nghi_phep ── loai_phep
trang_thai_lam_viec ────────┤
                            ├── cham_cong
                            ├── lich_su_he_so_luong
                            └── luong

vai_tro ── vai_tro_quyen ── quyen
```

Danh sách bảng:

`phong_ban`, `chuc_vu`, `vai_tro`, `quyen`, `vai_tro_quyen`,
`trang_thai_lam_viec`, `nhan_vien`, `loai_hop_dong`, `hop_dong`,
`loai_phep`, `nghi_phep`, `cham_cong`, `lich_su_he_so_luong`, `luong`.

View duy nhất là `vw_danh_sach_nhan_vien_chi_tiet`.

## Nhóm procedure

| Nhóm | Số lượng |
| --- | ---: |
| Phòng ban | 4 |
| Chức vụ | 4 |
| Vai trò | 4 |
| Quyền và gán quyền | 6 |
| Trạng thái làm việc | 1 |
| Nhân viên | 7 |
| Hợp đồng và loại hợp đồng | 10 |
| Nghỉ phép và loại phép | 8 |
| Chấm công | 5 |
| Lịch sử hệ số lương | 5 |
| Lương | 7 |
| Backup/restore | 2 |

Tồn tại object không đồng nghĩa procedure đã được kiểm thử nghiệp vụ. Các procedure employee update đã được gọi chỉ trên disposable schema qua guarded integration; không gọi routine ghi trên live schema.

## Registry các procedure PHP đang gọi

Snapshot caller hiện có 18 tên procedure trong lệnh `CALL` của PHP; bảng dưới đây nêu các contract đã audit và các lệch còn tồn tại:

| Procedure | Tham số theo caller | Trạng thái trong dump/live DB | Contract kết quả |
| --- | --- | --- | --- |
| `sp_phong_ban_danh_sach` | Không | Có | Row `ma_pb`, `ten_pb`, `so_nhan_vien` |
| `sp_phong_ban_them` | `ten_pb` | Có | Write, không result set |
| `sp_phong_ban_sua` | `ma_pb, ten_pb` | Có; caller sai placeholder | Write, không result set |
| `sp_phong_ban_xoa` | `ma_pb` | Có | Write, không result set |
| `sp_phong_ban_chi_tiet` | `ma_pb` | **Thiếu** | Caller kỳ vọng một row phòng ban |
| `sp_cham_cong_nhan_vien_phan_trang` | `tu_khoa, ma_pb, thang, nam, page, per_page` | **Thiếu** | Mỗi row phải có `total_count` |
| `sp_cham_cong_chi_tiet_phan_trang` | `ma_nv, nam, thang, page, per_page` | **Thiếu** | Mỗi row phải có `total_count` |
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

Result shape của bốn procedure thiếu mới chỉ là kỳ vọng suy ra từ caller, chưa phải hợp đồng đã được nhóm chấp thuận. Trước khi bổ sung SQL, cần viết fixture/test khóa tên cột, kiểu dữ liệu, pagination và error behavior.

## Setup local an toàn

### 1. Chốt DBMS

Máy audit dùng MariaDB 10.4.32. Nếu nhóm chọn MySQL 8, phải clean-replay dump và chạy integration test riêng trước khi ghi “tương thích”.

Nhóm cũng phải chốt timezone. Laravel hiện cố định `UTC`; MariaDB local dùng system timezone UTC+7. Chỉ thêm `APP_TIMEZONE` vào `.env` chưa có tác dụng nếu `config/app.php` không đọc biến này.

### 2. Cấu hình `.env`

`.env.example` hiện lệch với runtime nghiệp vụ: nó dùng SQLite và database-backed session/cache/queue. Baseline local nên dùng Laravel `mysql` driver và file/sync cho hạ tầng chưa migrate:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quan_ly_nhan_su
DB_USERNAME=<tai-khoan-local>
DB_PASSWORD=<mat-khau-local>

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

Chưa chạy migrations trước import vì dump sẽ xóa database. Nếu nhóm cần bảng users/session/cache/jobs, chỉ chạy migrations **sau** import và sau khi chốt chiến lược auth/hạ tầng. Migrations tạo kho tài khoản `users` thứ hai; thao tác này không tự tích hợp login với `nhan_vien`/SHA-256.

### 5. Không seed ở trạng thái hiện tại

`DatabaseSeeder` tham chiếu `App\Models\User`, nhưng model không tồn tại. Dump cũng không có business seed độc lập cho vai trò, quyền, trạng thái, loại phép và danh mục tối thiểu.

Tại snapshot audit, cả 14 bảng nghiệp vụ local đều có 0 row. Muốn tạo nhân viên hợp lệ phải bootstrap tối thiểu phòng ban, chức vụ, trạng thái làm việc và vai trò trước do ràng buộc khóa ngoại.

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
    UNION ALL SELECT 'sp_cham_cong_chi_tiet_phan_trang'
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

Code gọi bốn procedure không tồn tại trong cả dump và database local:

| Procedure thiếu | Caller chính | Hậu quả |
| --- | --- | --- |
| `sp_phong_ban_chi_tiet` | `PhongBanController@edit` | Không tải được bản ghi sửa |
| `sp_cham_cong_nhan_vien_phan_trang` | `ChamCongController@employees` | Danh sách nhân viên chấm công lỗi |
| `sp_cham_cong_chi_tiet_phan_trang` | `ChamCongController@index` | Chi tiết chấm công lỗi |
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

`database/sql/employee/2026_08_12_004_update_routines.sql` là script versioned để replay sau schema 001, read routines 002 và create routines 003. `sp_nhan_vien_sua` có 14 tham số `IN`; routine khóa target và chỉ cho role `NHAN_VIEN_MAC_DINH`, đồng thời giữ các cột hệ thống. `sp_nhan_vien_cap_nhat_anh` trả avatar cũ qua `OUT` để service xóa sau commit; không routine nào tự mở/commit/rollback transaction. Guarded disposable integration đã xác minh contract này với `20 tests, 436 assertions`; MySQL 8 chưa được claim.

Scoped code/test re-review đã **Approve**, SQL lock/transaction-boundary notes đã ADDRESSED, và Task 12 scoped implementation đã delivered/pushed trong commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f`. Task13 lifecycle/auth DB contracts là bước kế tiếp nhưng chưa bắt đầu; Task18 auth/RBAC/Gates là prerequisite trước enablement.

## Backlog

### P0

1. Chốt MariaDB 10.4 hay MySQL 8 và timezone thống nhất giữa PHP/DB.
2. Đồng bộ `.env.example` và bỏ phụ thuộc không chủ đích vào migrations.
3. Version bốn procedure thiếu hoặc thay caller.
4. Tạo business seeders tối thiểu.
5. Loại password khỏi view/result và chốt auth Laravel.

### P1

1. Tách dump thành schema, routines và seed hoặc chuyển sang migration/SQL script versioned.
2. Sửa các procedure xóa/reset mật khẩu/quyền có rủi ro.
3. Thêm unique/check/index cho identity và kỳ lương.
4. Viết integration test cho mỗi procedure PHP đang gọi.

## Giới hạn của snapshot

- Chưa clean-replay dump.
- Chưa mutation test hoặc trigger test.
- Chưa checksum thân routine giữa live DB và dump; mới xác minh tập tên object.
- Chưa xác minh MySQL 8.
