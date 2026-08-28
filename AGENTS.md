# AGENTS.md

Hướng dẫn bắt buộc cho Codex và AI agent làm việc trong repository `quanlynhansu`.

Nếu tài liệu mâu thuẫn với code, route, test hoặc database live, ưu tiên bằng chứng live rồi cập nhật lại tài liệu. Không suy rộng một response `200`, Vite build hoặc test mẫu thành “nghiệp vụ hoàn thành”.

## Tổng quan

- Đồ án: website quản lý nhân sự cho hai môn Web Application và UI/UX.
- Stack: Laravel 12, PHP 8.2+, Blade, JavaScript, Vite 7, Tailwind CSS 4, Bootstrap, MariaDB/MySQL.
- Nguồn dựng fresh hiện hành cho hợp đồng 15 bảng là lần lượt
  `database/sql/tao_bang.sql`, `database/sql/du_lieu_mau.sql` và
  `database/sql/quyen_vai_tro.sql`; các file SQL ở thư mục gốc và
  `quan_ly_nhan_su.session.sql` là lịch sử đã đánh dấu, không phải nguồn active.
- Main hiện có UI/API prototype cho lương, chấm công, nghỉ phép; module Nhân viên + auth/RBAC Tasks 13–20 đã verified hẹp và tích hợp vào `main` qua merge `aa77419`. Trong task hiện tại, code ownership chỉ gồm Nhân viên, Phòng ban và Chức vụ; các issue Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC và API của đồng nghiệp chỉ ghi chú, không tự sửa nếu user chưa giao rõ. Phòng ban và Chức vụ phải bám đúng catalog quyền `PhongBan.*`/`ChucVu.*` và fresh 15-table contract; xem handoff/guide để biết giới hạn browser.
- Trạng thái chi tiết: `docs/PROJECT_STATUS.md`.

## Thứ tự đọc

1. File này.
2. `docs/CODEX_NEXT_HANDOFF.md`.
3. `docs/PROJECT_STATUS.md` và tài liệu chuyên đề liên quan. Với task module Nhân viên, đọc thêm [docs/EMPLOYEE_MODULE_GUIDE.md](docs/EMPLOYEE_MODULE_GUIDE.md).
4. Route, controller, request, service/repository, model, Blade/JavaScript và test của task.
5. Ba file `database/sql/tao_bang.sql`, `database/sql/du_lieu_mau.sql` và
   `database/sql/quyen_vai_tro.sql` trước thay đổi fresh;
   đọc `quan_ly_nhan_su.session.sql` chỉ để đối chiếu legacy khi cần.
6. Instruction/skill phù hợp trong `.codex/`.

Khi HEAD thay đổi, chạy lại Git status, route, test và build trước khi tin snapshot.

## Bản đồ code

- `routes/web.php`: route web canonical chủ yếu ở root (`/nhan-vien`, `/phong-ban`, `/chuc-vu`); chỉ còn hai alias legacy dưới `admin/nhan-vien/...`.
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
- Runtime hiện còn resource API names không có `api.v1` prefix; các route phụ trợ nghỉ phép đã có tên riêng để kiểm thử middleware, không coi tên resource cũ là convention mới.
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
  quyền `ChucVu.Read`, `ChucVu.Insert`, `ChucVu.Update`, `ChucVu.Delete`
  (13–16). Các `sp_chuc_vu_*`
  trong test/dump cũ chỉ là historical, không phải caller active.
- Module Phòng ban hiện dùng Query Builder trực tiếp trên `phong_ban` và
  `nhan_vien`, trả shape `ma_pb`, `ten_pb`, `so_nhan_vien`, transaction/row lock
  và mã lỗi `PB_*`; các `sp_phong_ban_*` trong dump/script/test cũ chỉ là
  historical, không phải caller active của repository.
- Các lỗi đã audit ngoài ownership chỉ ghi chú: Dashboard còn ranh giới
  auth/permission riêng; `LuongRepository@all` gọi
  `sp_luong_tim_kiem_phan_trang` thiếu; Chấm công lookup/update gọi
  `sp_phong_ban_danh_sach`/`sp_cham_cong_cap_nhat` thiếu; Nghỉ phép approve gọi
  `sp_nghi_phep_duyet_phep` thiếu; model/validation còn legacy drift; Hợp đồng
  và quản trị RBAC mới chỉ verified hẹp hoặc thiếu mutation/browser evidence.
  Không coi các procedure này có trong active/live và không sửa chúng trong task
  Nhân viên/Phòng ban/Chức vụ.
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

Bằng chứng phiên 2026-08-27: full Laravel `288 tests, 2222 assertions`,
frontend `18` tests, Vite `19 modules transformed`, route inventory `79`,
Composer validate, PHP lint file sửa và `git diff --check` đều pass. Guarded
MariaDB trên schema disposable pass: exit `0`, PHPUnit `12 tests, 422 assertions`,
thời gian `10.110s`. Đây không phải bằng chứng cho database live,
browser hoặc production.
`phpunit.xml` mặc định dùng SQLite in-memory nên full suite vẫn không tự chứng
minh MariaDB DDL; chạy wrapper disposable hoặc `phpunit.mariadb.xml` theo đúng
guard khi có môi trường phù hợp. Nếu HEAD/baseline đổi, cập nhật
`docs/PROJECT_STATUS.md`; không che lỗi cũ bằng cách xóa assertion.

## Giao tiếp

- Trả lời người dùng bằng tiếng Việt, ngắn gọn và có bằng chứng.
- Trước thay đổi nhiều file, nêu phạm vi.
- Sau khi hoàn thành, liệt kê file đã sửa, kiểm tra đã chạy, kết quả và giới hạn.
- Phân biệt rõ: verified hẹp, prototype, blocked, planned.
- Không commit/push/merge nếu người dùng chưa yêu cầu.
