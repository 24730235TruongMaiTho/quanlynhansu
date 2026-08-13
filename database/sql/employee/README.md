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

Chỉ chạy mutation qua `tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb` cho tới khi có quy trình rollout riêng. Không chạy script hoặc canonical dump trên database `quan_ly_nhan_su` đang giữ dữ liệu.

## `2026_08_12_002_read_routines.sql`

Script read contract cho MariaDB 10.4:

- thay danh sách/tìm kiếm nhân viên legacy bằng `sp_nhan_vien_danh_sach_phan_trang` có OUT total, filter và thứ tự `ma_nv ASC` ổn định;
- viết lại `sp_nhan_vien_chi_tiet` với cột hồ sơ/địa chỉ tường minh và địa chỉ nullable;
- bổ sung `sp_cham_cong_nhan_vien_phan_trang`, tổng hợp đúng tháng/năm theo quy tắc giờ `>= 8` là một ngày, `>= 4` là nửa ngày;
- khóa shape lookup dùng chung cho phòng ban, chức vụ, vai trò và trạng thái làm việc;
- không dùng dynamic SQL và không tự `DROP DATABASE`, `CREATE DATABASE` hoặc `USE`.

Chạy sau script `001`. Mọi replay/mutation chỉ thực hiện qua disposable MariaDB guard; script không được chạy trực tiếp trên database live.
