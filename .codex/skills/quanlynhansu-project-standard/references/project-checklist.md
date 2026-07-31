# Checklist dự án

## Trước khi sửa

- [ ] Đã đọc `AGENTS.md` và handoff mới nhất.
- [ ] Đã kiểm tra `git status --short`.
- [ ] Đã xác định rõ module, người dùng và tiêu chí hoàn thành.
- [ ] Đã đọc đủ route/controller/model/view/test liên quan.
- [ ] Đã kiểm tra SQL dump nếu task liên quan database.

## Backend Laravel

- [ ] Namespace và base controller đúng chuẩn Laravel.
- [ ] Route, HTTP method, action và route name khớp nhau.
- [ ] Có validation và thông báo lỗi dễ hiểu.
- [ ] Không tin dữ liệu từ request khi chưa kiểm tra.
- [ ] Redirect, flash message và error handling đầy đủ.

## Database

- [ ] Model map đúng tên bảng, khóa chính và timestamps.
- [ ] Procedure tồn tại và đúng số lượng/thứ tự tham số.
- [ ] Test trên database local/disposable, không dùng database có dữ liệu cần giữ.
- [ ] Không trả mật khẩu/hash hoặc dữ liệu nhạy cảm ra view.
- [ ] Không đưa câu lệnh backup/restore kiểu SQL Server vào MySQL web flow.

## UI/UX

- [ ] Layout và component nhất quán với các màn hình hiện có.
- [ ] Form có label, lỗi theo field và trạng thái submit.
- [ ] Có trạng thái loading, empty, success và error khi phù hợp.
- [ ] Dùng semantic HTML, keyboard focus và contrast đủ rõ.
- [ ] Kiểm tra desktop, tablet và mobile.

## Kiểm tra và Git

- [ ] Chạy `php -l` cho PHP đã sửa.
- [ ] Chạy `php artisan test` khi môi trường cho phép.
- [ ] Chạy `npm run build` khi sửa Blade/CSS/JavaScript.
- [ ] Chạy `git diff --check`.
- [ ] `git status --short` chỉ chứa file đúng phạm vi.
- [ ] Pull request mô tả thay đổi, cách kiểm tra và ảnh UI nếu có.
