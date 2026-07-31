---
name: quanlynhansu-project-standard
description: Chuẩn bị, triển khai và review thay đổi trong đồ án Laravel quản lý nhân sự `quanlynhansu`. Dùng khi làm route, controller, model, Blade, CSS/JavaScript, MySQL/stored procedure, test, README hoặc pull request trong repository này; đặc biệt khi cần đồng bộ yêu cầu của môn Lập trình Web Application và môn Thiết kế giao diện người dùng.
---

# Chuẩn dự án Quản lý Nhân sự

Giữ mọi thay đổi bám code hiện tại, phù hợp đồ án nhóm và có thể kiểm tra trước khi đưa lên GitHub.

## Thứ tự đọc bắt buộc

1. Đọc `AGENTS.md`.
2. Đọc `docs/CODEX_NEXT_HANDOFF.md` nếu file tồn tại.
3. Đọc route, controller, model, view và test liên quan trực tiếp tới task.
4. Đọc `quan_ly_nhan_su.session.sql` trước mọi thay đổi phụ thuộc database hoặc stored procedure.
5. Đọc instruction phù hợp trong `.codex/instructions/` khi task liên quan backend, UI, database hoặc Git.

Ưu tiên code hiện tại khi README mâu thuẫn. Nêu rõ giả định nếu chưa thể xác minh bằng code hoặc database local.

## Quy trình thực hiện

1. Chạy `git status --short` và bảo toàn thay đổi đang có.
2. Xác định một lát cắt nhỏ có thể kiểm tra: route → controller → validation → data → Blade → test.
3. Kiểm tra hợp đồng dữ liệu trước khi sửa: tên bảng, khóa chính, timestamps, procedure và thứ tự tham số.
4. Thực hiện đúng phạm vi; không sửa hàng loạt module rỗng hoặc refactor ngoài yêu cầu.
5. Với UI, kiểm tra desktop/mobile, semantic HTML, label, focus, contrast và các trạng thái loading/empty/success/error.
6. Chạy kiểm tra phù hợp với file đã sửa.
7. Báo cáo file thay đổi, kết quả kiểm tra, lỗi còn lại và bước tiếp theo.

## Ràng buộc dự án

- Dùng namespace `App\\...`, route name `backend.<module>.<action>` và Blade path chữ thường.
- Xem `quan_ly_nhan_su.session.sql` là nguồn schema nghiệp vụ hiện tại; migrations vẫn chủ yếu là Laravel mặc định.
- Không import SQL dump vào database có dữ liệu vì dump có lệnh xóa database.
- Không giả định login đã tồn tại. Nguồn tài khoản `users` và `nhan_vien` chưa được thống nhất.
- Không commit `.env`, secrets, `vendor`, `node_modules` hoặc `public/build`.
- Không đánh dấu module hoàn thành chỉ vì có tên trong README.

## Kiểm tra tối thiểu

- PHP: `php -l <file.php>`.
- Route/boot: `php artisan route:list --except-vendor`.
- Laravel: `php artisan test`.
- Frontend/Blade asset: `npm run build`.
- Git hygiene: `git diff --check` và `git status --short`.

Đọc `references/project-checklist.md` khi triển khai module hoặc review trước merge.
