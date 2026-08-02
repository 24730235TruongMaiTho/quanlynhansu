# Bộ giao diện HR theo Primer

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

Giữ file CSS/JS trong `public`, sau đó copy phần:

```html
<main class="hr-page">
    ...
</main>
```

vào view Blade. Ba màn không chứa header, sidebar hoặc footer.

## Tính năng demo

- Responsive.
- Search theo tên hoặc mã nhân viên.
- Lọc phòng ban và trạng thái.
- Lọc nhanh trạng thái ở màn nghỉ phép.
- Toast phản hồi cho các nút thao tác.
