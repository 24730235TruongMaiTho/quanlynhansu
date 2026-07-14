# Frontend Button Effect Notes (Reusable for Codex)

Mục tiêu: lưu nhanh các hiệu ứng đã dùng để tái sử dụng trong các màn sau.

## 1) Hover effect: "đường xé giữa + hai lớp"
Class dùng lại:
- `btn-door` (style đã có sẵn cho button)
- `door-effect` (có thể gán cho bất kỳ element kiểu button)

### Cách bật
```html
<a class="btn btn-primary btn-door">Liên hệ</a>
<!-- hoặc -->
<a class="btn btn-primary door-effect">Liên hệ</a>
```

### Các biến toàn cục (đặt trong `:root`)
- `--door-style-angle: 5deg`
- `--door-style-split-distance: 53%`
- `--door-style-seam-overlap: 8px`
- `--door-style-outer-bg: linear-gradient(130deg, #f5df97, #d4af37 48%, #b88c2f)`
- `--door-style-inner-bg: linear-gradient(130deg, #1f2937, #0f172a 48%, #111827)`
- `--door-style-text-outer: #0f172a`
- `--door-style-text-inner: #f8fafc`

### Thay đổi nhanh cho 1 button riêng
```html
<a class="btn door-effect"
   style="--door-style-split-distance: 52%; --door-style-seam-overlap: 10px; --door-style-angle: 6deg;">
   Tuỳ biến
</a>
```

### Ghi chú kỹ thuật
- Effect được làm bằng 2 pseudo-element: `::before` và `::after`.
- Hai nửa dùng `clip-path` tách trái/phải và xoay cùng góc.
- `--door-style-seam-overlap` lớn hơn sẽ làm kín khe, giảm lỗi hở mép; nhỏ hơn cho đường chia mảnh hơn.

## 2) Kiểm tra sau khi chỉnh
- Chạy:
  - `npm run build`
  - mở trang và Ctrl+Shift+R để tránh cache cũ
- Nếu seam lệch nhẹ, ưu tiên chỉnh:
  - `--door-style-split-distance`
  - `--door-style-seam-overlap`

## 3) Vị trí lưu trữ trong repo
- File này: `notes/frontend-btn-effect-notes.md`
- CSS hiệu ứng đang ở: `resources/css/app.css`

## 4) Template nhanh cho nút mới
```html
<a href="#" class="btn btn-primary btn-door">
  <span class="door-text">Nhãn nút</span>
</a>
```
