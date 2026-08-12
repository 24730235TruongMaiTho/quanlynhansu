# Bộ giao diện HR theo Primer

> **Phân loại: prototype/reference.** Các file trong thư mục này là HTML tĩnh để tham khảo UI, không phải Blade/Vite runtime của ứng dụng trên `main` và không chứng minh API/nghiệp vụ đã hoạt động.

Gồm 3 màn hình:

- `01-bang-luong.html`
- `02-cham-cong.html`
- `03-nghi-phep.html`

Tài nguyên dùng chung:

- `primer-hr.css`
- `primer-hr.js`

## Cách xem

Giải nén toàn bộ thư mục rồi mở từng file HTML bằng trình duyệt.

## Cách đưa vào Laravel Blade

Không copy nguyên CSS/JS vào `public` hoặc tạo thêm một layout riêng. Trước tiên đọc `../FRONTEND_GUIDE.md` và quyết định shell/asset strategy đang được tích hợp.

Khi chuyển một prototype thành Blade, chỉ giữ phần nội dung page tương đương:

```html
<main class="hr-page">
    ...
</main>
```

Ba màn không chứa header, sidebar hoặc footer. Route, API contract, loading/error state, auth và test phải được triển khai riêng trước khi gọi màn hình là hoàn thành.

## Tính năng demo

- Responsive.
- Search theo tên hoặc mã nhân viên.
- Lọc phòng ban và trạng thái.
- Lọc nhanh trạng thái ở màn nghỉ phép.
- Toast phản hồi cho các nút thao tác.
