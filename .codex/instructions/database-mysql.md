# MySQL và stored procedure

- Xem `quan_ly_nhan_su.session.sql` là nguồn schema nghiệp vụ hiện tại.
- Không import dump vào database có dữ liệu cần giữ; dump có lệnh xóa database.
- Dùng database local/disposable khi test procedure.
- Kiểm tra đúng tên procedure, số placeholder và thứ tự binding.
- Không giả định migrations Laravel tạo các bảng nghiệp vụ.
- Không trả hash mật khẩu hoặc dữ liệu nhạy cảm từ view/query ra giao diện.
- Backup/restore phải dùng công cụ MySQL hoặc quy trình server an toàn, không dùng câu lệnh SQL Server trong web request.
