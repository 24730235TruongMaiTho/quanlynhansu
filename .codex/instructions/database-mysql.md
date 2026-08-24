# MySQL và stored procedure

- Đọc `docs/DATABASE.md`; runtime đã xác minh hiện là MariaDB 10.4.32, chưa mặc định khẳng định MySQL 8 tương thích.
- Xem `quan_ly_nhan_su.session.sql` là nguồn schema nghiệp vụ hiện tại.
- Không import dump vào database có dữ liệu cần giữ; dump có lệnh xóa database.
- Dùng database local/disposable khi test procedure.
- Kiểm tra đúng tên procedure, số placeholder và thứ tự binding.
- Không tự tạo bằng phỏng đoán ba procedure ngoài module nhân viên đang còn thiếu (`sp_phong_ban_chi_tiet`, `sp_cham_cong_chi_tiet_phan_trang`, `sp_luong_tim_kiem_phan_trang`); phải khóa input/output/pagination contract trước.
- Không giả định migrations Laravel tạo các bảng nghiệp vụ.
- `.env.example` đã dùng MySQL/MariaDB, file/sync drivers và các biến timezone; chỉ điền credential local được phép dùng, không commit `.env`.
- Giữ `APP_TIMEZONE=Asia/Ho_Chi_Minh` và `DB_TIMEZONE=+07:00` đồng bộ trước logic dùng `now()`/`CURDATE()`.
- Không trả hash mật khẩu hoặc dữ liệu nhạy cảm từ view/query ra giao diện.
- Backup/restore phải dùng công cụ MySQL hoặc quy trình server an toàn, không dùng câu lệnh SQL Server trong web request.
