# MySQL và stored procedure

- Đọc `docs/DATABASE.md`; runtime đã xác minh hiện là MariaDB 10.4.32, chưa mặc định khẳng định MySQL 8 tương thích.
- Xem `quan_ly_nhan_su.session.sql` là nguồn schema nghiệp vụ hiện tại.
- Không import dump vào database có dữ liệu cần giữ; dump có lệnh xóa database.
- Dùng database local/disposable khi test procedure.
- Kiểm tra đúng tên procedure, số placeholder và thứ tự binding.
- Không tự tạo bằng phỏng đoán bốn procedure code đang gọi nhưng dump/live DB không có; phải khóa input/output/pagination contract trước.
- Không giả định migrations Laravel tạo các bảng nghiệp vụ.
- `.env.example` hiện dùng SQLite và database-backed drivers, không phải baseline nghiệp vụ đã xác minh.
- Laravel hiện dùng UTC còn MariaDB local dùng UTC+7; chốt timezone chung trước logic dùng `now()`/`CURDATE()`.
- Không trả hash mật khẩu hoặc dữ liệu nhạy cảm từ view/query ra giao diện.
- Backup/restore phải dùng công cụ MySQL hoặc quy trình server an toàn, không dùng câu lệnh SQL Server trong web request.
