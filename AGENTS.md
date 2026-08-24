# AGENTS.md

Hướng dẫn bắt buộc cho Codex và AI agent làm việc trong repository `quanlynhansu`.

Nếu tài liệu mâu thuẫn với code, route, test hoặc database live, ưu tiên bằng chứng live rồi cập nhật lại tài liệu. Không suy rộng một response `200`, Vite build hoặc test mẫu thành “nghiệp vụ hoàn thành”.

## Tổng quan

- Đồ án: website quản lý nhân sự cho hai môn Web Application và UI/UX.
- Stack: Laravel 12, PHP 8.2+, Blade, JavaScript, Vite 7, Tailwind CSS 4, Bootstrap, MariaDB/MySQL.
- Nguồn dựng fresh hiện hành cho hợp đồng 15 bảng là `database/tao_bang.sql`
  rồi `database/du_lieu_mau.sql`; `quan_ly_nhan_su.session.sql` là dump lịch sử
  đã đánh dấu, không phải nguồn schema active.
- Main hiện có UI/API prototype cho lương, chấm công, nghỉ phép; module Nhân viên + auth/RBAC Tasks 13–20 đã verified hẹp và tích hợp vào `main` qua merge `aa77419`. Trên các feature branch, Phòng ban và Chức vụ phải bám đúng catalog `PB_*`/`CV_*` và fresh 15-table contract; xem handoff/guide để biết giới hạn browser.
- Trạng thái chi tiết: `docs/PROJECT_STATUS.md`.

## Thứ tự đọc

1. File này.
2. `docs/CODEX_NEXT_HANDOFF.md`.
3. `docs/PROJECT_STATUS.md` và tài liệu chuyên đề liên quan. Với task module Nhân viên, đọc thêm [docs/EMPLOYEE_MODULE_GUIDE.md](docs/EMPLOYEE_MODULE_GUIDE.md).
4. Route, controller, request, service/repository, model, Blade/JavaScript và test của task.
5. `database/tao_bang.sql` và `database/du_lieu_mau.sql` trước thay đổi fresh;
   đọc `quan_ly_nhan_su.session.sql` chỉ để đối chiếu legacy khi cần.
6. Instruction/skill phù hợp trong `.codex/`.

Khi HEAD thay đổi, chạy lại Git status, route, test và build trước khi tin snapshot.

## Bản đồ code

- `routes/web.php`: web route dưới `/admin`.
- `routes/api.php`: JSON API v1 cho lương, chấm công và nghỉ phép.
- `app/Http/Controllers/Backend`: controller web/API.
- `app/Http/Requests`: validation requests.
- `app/Services`, `app/Repositories`: một phần data/business layer.
- `app/Models`: model nghiệp vụ, mức hoàn thiện không đồng đều.
- `resources/views/backend`: dashboard và page quản trị.
- `resources/js/frontend`: API clients/UI logic theo module.
- `docs/`: kiến trúc, trạng thái, DB, frontend, roadmap và handoff.

## Git và bảo toàn thay đổi

- Chạy `git status --short --branch` trước/sau khi sửa.
- Bảo toàn mọi thay đổi không thuộc task.
- Không tự động fetch, merge, rebase, push, tạo upstream hoặc worktree.
- Không force-push branch dùng chung.
- Không commit `.env`, secret, dữ liệu cá nhân, `vendor`, `node_modules` hoặc `public/build`.
- `docs/CODEX_FRONTEND_HANDOFF.md` là local-only và không được stage.

Main và local branch `frontend` đã phân kỳ. Shell ở `frontend` chưa được merge. Đọc `docs/decisions/ADR-001-admin-shell.md` trước mọi thay đổi layout hoặc tích hợp branch.

## Quy ước Laravel

- Namespace dùng `App\...`.
- Controller extend base controller Laravel.
- Route name dùng `backend.<module>.<action>` hoặc `api.v1.<module>.<action>`.
- Không lặp prefix `backend.backend.*`.
- Runtime hiện còn resource API names không có `api.v1` prefix và route nghỉ phép chưa đặt tên; xem đây là drift cần sửa, không phải convention mới.
- Blade path phải khớp chính xác thư mục (`layout` và `layouts` là hai path khác nhau).
- Validate ở server và trả lỗi an toàn; không trả raw exception/SQL message.
- Model phải map đúng table, primary key, casts và timestamps.
- Không tạo file/class rỗng chỉ để đánh dấu module.

## Database

- Đọc `docs/DATABASE.md`.
- Dump có `DROP DATABASE IF EXISTS quan_ly_nhan_su`; không import vào DB có dữ liệu cần giữ.
- Runtime audit là MariaDB 10.4.32; chưa mặc định tuyên bố tương thích MySQL 8.
- Migrations chỉ là hạ tầng Laravel và chưa tạo bảng nghiệp vụ.
- Module Nhân viên/auth/RBAC hiện dùng Query Builder trực tiếp trên hợp đồng 15
  bảng; không thêm procedure/view/trigger để né giới hạn bảng. Với procedure của
  module khác, kiểm tra tên, số tham số, thứ tự và result shape trong dump/live schema.
- Module Chức vụ hiện dùng Query Builder trực tiếp trên `chuc_vu` và `nhan_vien`,
  trả shape tường minh `ma_cv`, `ten_cv`, `he_so_phu_cap`, `so_nhan_vien` và
  quyền `CV_VIEW/CV_CREATE/CV_EDIT/CV_DELETE` (301–304). Các `sp_chuc_vu_*`
  trong test/dump cũ chỉ là historical, không phải caller active.
- Module Phòng ban hiện dùng Query Builder trực tiếp trên `phong_ban` và
  `nhan_vien`, trả shape `ma_pb`, `ten_pb`, `so_nhan_vien`, transaction/row lock
  và mã lỗi `PB_*`; các `sp_phong_ban_*` trong dump/script/test cũ chỉ là
  historical, không phải caller active của repository.
- `sp_phong_ban_chi_tiet` và `sp_luong_tim_kiem_phan_trang` không thuộc active
  repository contract; không tự tạo routine bằng phỏng đoán. Chấm công chi tiết
  hiện dùng Query Builder trên cột canonical và không gọi
  `sp_cham_cong_chi_tiet_phan_trang`.
- Chốt cùng timezone cho Laravel và DB trước các logic dùng `now()`/`CURDATE()`.
- Chỉ test mutation trên database test/disposable.
- Không dùng procedure backup/restore/import/export hiện tại từ web.
- Không trả password/hash từ view/query ra UI/API.

## Frontend/UI

- Đọc `docs/FRONTEND_GUIDE.md`.
- Runtime main hiện dùng `backend.layouts.app`; shell mục tiêu `backend.layout.app` chỉ ở branch `frontend`.
- Quyết định mục tiêu: Header + Sidebar + Main + Footer, không global navbar.
- Không thêm design system/asset strategy mới khi chưa chốt tích hợp.
- UI phải có loading, empty, success, validation error, server error và disabled/submitting.
- Dùng semantic HTML, label, keyboard focus, accessible name, contrast và responsive.
- Build/test tự động không thay thế browser acceptance.

## Cách triển khai tính năng

1. Chọn một vertical slice nhỏ.
2. Khóa contract route/request/JSON/database.
3. Viết hoặc cập nhật test thể hiện hành vi mong muốn.
4. Sửa đúng phạm vi: route → validation → controller → data → Blade/JS.
5. Kiểm tra auth/permission.
6. Chạy test/build/browser phù hợp.
7. Cập nhật `PROJECT_STATUS.md`/handoff nếu trạng thái thay đổi.

Không sửa hàng loạt lỗi ngoài scope; ghi chúng thành blocker có file và bằng chứng.

## Lệnh kiểm tra

```powershell
php -l <file.php>
php artisan route:list --except-vendor
php artisan test
npm run build
composer validate --no-check-publish
git diff --check
git status --short
```

Evidence historical trên main ngày 2026-08-21 vẫn được giữ trong docs nhưng
không thay cho gate fresh 15 bảng. Base historical fresh MariaDB contract là
`5 tests, 161 assertions` trên disposable schema, gồm migration 16→15 và hai
worker counter trực tiếp; branch Chức vụ hiện tại đã mở rộng và pass `7 tests,
231 assertions` trên disposable schema, gồm CRUD Chức vụ/Phòng ban. Fresh
harness hiện tại đã thêm CRUD Phòng ban vào gate này.
`phpunit.xml` mặc định dùng SQLite
in-memory nên full suite vẫn không tự chứng minh MariaDB DDL; chạy
`phpunit.mariadb.xml` để lặp lại gate fresh. Nếu HEAD/baseline đổi, cập nhật
`docs/PROJECT_STATUS.md`; không che lỗi cũ bằng cách xóa assertion.

## Giao tiếp

- Trả lời người dùng bằng tiếng Việt, ngắn gọn và có bằng chứng.
- Trước thay đổi nhiều file, nêu phạm vi.
- Sau khi hoàn thành, liệt kê file đã sửa, kiểm tra đã chạy, kết quả và giới hạn.
- Phân biệt rõ: verified hẹp, prototype, blocked, planned.
- Không commit/push/merge nếu người dùng chưa yêu cầu.
