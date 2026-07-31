# Laravel backend

- Đọc route, controller, model, Blade và SQL liên quan trước khi sửa.
- Dùng namespace `App\\...` và controller Laravel đúng chuẩn.
- Đặt route name theo `backend.<module>.<action>`.
- Validate request ở server và hiển thị lỗi rõ ràng.
- Map model đúng tên bảng, khóa chính và timestamps của schema.
- Nếu gọi stored procedure, kiểm tra tên và thứ tự tham số trong SQL dump.
- Làm từng lát cắt nhỏ; không tạo controller rỗng chỉ để đủ danh sách module.
- Sau thay đổi, chạy `php -l`, route list và test phù hợp.
