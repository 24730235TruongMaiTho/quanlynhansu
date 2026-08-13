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
