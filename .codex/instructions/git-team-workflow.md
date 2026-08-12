# Git và GitHub cho nhóm

- Chạy `git status --short` trước và sau khi sửa.
- Bảo toàn thay đổi chưa commit của thành viên khác.
- Không tự fetch, merge, rebase, push, tạo upstream hoặc worktree khi người dùng chưa yêu cầu.
- Main và local branch `frontend` đang phân kỳ; tích hợp shell phải là task/plan riêng.
- Dùng branch ngắn hạn: `feature/`, `fix/`, `docs/`, `test/`, `refactor/`.
- Mỗi commit chỉ chứa một thay đổi logic và có message mô tả mục đích.
- Không commit `.env`, secret, `vendor`, `node_modules` hoặc `public/build`.
- Chạy test/build phù hợp trước khi push.
- Pull request phải ghi phạm vi, cách kiểm tra, rủi ro còn lại và ảnh giao diện nếu có.
- Không force push lên branch dùng chung.
