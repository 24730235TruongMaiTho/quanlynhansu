# Thiết kế giao diện người dùng

- Đọc `docs/FRONTEND_GUIDE.md` và `docs/decisions/ADR-001-admin-shell.md` trước khi đổi layout/assets.
- Phân biệt runtime `backend.layouts.app` trên main với shell mục tiêu `backend.layout.app` trên branch frontend; không copy/merge ngầm.
- Xem UI là một phần chấm điểm riêng, không chỉ là lớp trang trí cho backend.
- Giữ design system nhất quán: màu, typography, spacing, button, form và table.
- Ưu tiên semantic HTML, label, keyboard focus, contrast và thông báo lỗi dễ hiểu.
- Thiết kế đủ trạng thái loading, empty, success, error và disabled khi phù hợp.
- Kiểm tra desktop, tablet và mobile.
- Không trộn Bootstrap, Tailwind và CSS tùy chỉnh trong cùng component nếu chưa có lý do rõ ràng.
- Khi thay đổi UI, chạy build và cung cấp ảnh trước/sau trong pull request.
