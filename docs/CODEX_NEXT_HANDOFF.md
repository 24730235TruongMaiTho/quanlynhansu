# Handoff tiếp tục đồ án `quanlynhansu`

> Snapshot: 2026-08-20 (Asia/Saigon)
>
> Branch: `feature/quanly-nhan-vien`
>
> Implementation commit đã push và được xác minh là ancestor trên `origin/feature/quanly-nhan-vien`: `3c07d88db59d3083e0728c4c2a71ce3b9039f75f`
>
> Remote: `origin/feature/quanly-nhan-vien` đã xác nhận chứa implementation commit trên; revalidate current branch HEAD trước khi dùng handoff
>
> Phạm vi phiên này: Task 12 scoped vertical slice đã delivered/pushed; scoped code/test review **Approve**. Current HEAD/worktree phải được revalidate; không ghi database live.

## Đọc trước

1. `AGENTS.md`.
2. [docs/README.md](README.md).
3. [PROJECT_STATUS.md](PROJECT_STATUS.md).
4. Code, test và SQL liên quan trực tiếp tới task.

Không dùng snapshot này nếu HEAD đã đổi mà chưa chạy lại kiểm tra.

## Handoff hiện tại: cập nhật nhân viên

Các file trong implementation commit employee-update phải được bảo toàn; không sửa ngoài scope. Lát cắt gồm route/controller/request/service/repository, target-role guard, form Blade wizard, CSS/JS states, routine SQL và test. Runtime vẫn dùng `backend.layouts.app`; không copy shell từ branch `frontend`.

### Hard-disabled rollout gate

`config/nhanvien.php` dùng literal `'enabled' => false`. Đây là trạng thái bắt buộc hiện tại. `NhanVienUpdateTest` xác minh edit/update trả 404 trước service khi cờ tắt. Không set cờ thành `true`, không thêm env override và không deploy active trước Task 18 có auth/RBAC/Gates + actor authorization. Khi bật sớm, anonymous có thể lấy form/CSRF, enumerate target/role và gửi mutation/đọc PII; target-role guard không thay thế authentication. Không mô tả endpoint là public-safe.

Contract mutation:

- `PUT|PATCH /admin/nhan-vien/{ma_nv}` với `ma_nv` khớp `NV[0-9]{3}`, route name `backend.nhanvien.update` và rollout middleware.
- Target được đọc/guard trước validation; chỉ role chính xác `NHAN_VIEN_MAC_DINH` được sửa. Role, mã, hash mật khẩu và ngày nghỉ việc không nằm trong payload.
- Hồ sơ + địa chỉ + avatar chạy trong một transaction default/write connection. Avatar mới dùng prefix `nhan-vien/avatars`; file cũ chỉ xóa sau commit nếu path được chứng minh thuộc prefix.
- `sp_nhan_vien_sua` nhận 14 `IN`; `sp_dia_chi_nhan_vien_luu` upsert địa chỉ; `sp_nhan_vien_cap_nhat_anh` nhận 2 `IN` và 1 `OUT` đường dẫn cũ. Routine không tự commit/rollback.

Evidence phiên này:

- Fresh pre-push evidence: PHP employee `84/907`; MariaDB disposable `20/436`, cleanup count `0`; frontend `5`; build `13`; route `44`; Composer/lint pass.
- Feature/Unit employee tests: **84 pass, 907 assertions**.
- Frontend: `npm run test:frontend` 5 pass; `npm run build` pass (Vite 7.3.6, 13 modules).
- Laravel full suite: **158 pass, 1 baseline fail** tại `tests/Feature/ExampleTest.php` vì `/` trả 404; không sửa ngoài scope để che lỗi này.
- Route list: 44 route; PHP lint **23 PHP files in slice** pass.
- MariaDB disposable integration (`EmployeeUpdateProcedureTest`, `CanonicalDumpReplayTest`, `NhanVienRepositoryReadTest`) pass hẹp: **20 tests, 436 assertions, exit 0**, cleanup count **0**; tuyệt đối không dùng database `quan_ly_nhan_su` live.
- Hard-disable evidence: `php artisan test tests/Feature/Backend/NhanVien/NhanVienUpdateTest.php` pass; config flag vẫn literal false.
- Browser acceptance/auth-RBAC thật chưa được thực hiện. Slice đã **delivered hẹp** tại commit `3c07d88` nhưng module chưa production-ready và không được bật active. Scoped code/test re-review đã **Approve**.

## Baseline live

- PHP 8.5.0; project target PHP 8.2+.
- Laravel 12.62.0.
- 44 app route: 17 web, 27 API.
- `npm run build`: pass, Vite 7.3.6.
- `php artisan test`: 158 pass, 1 fail vì `/` trả 404; suite dùng SQLite in-memory, không chứng minh routine MariaDB.
- Composer metadata: valid.
- Runtime app: DB mysql, session/cache file, queue sync.
- MariaDB 10.4.32 / schema 14 table, 1 view, 8 function, 10 trigger, 63 procedure là số liệu kế thừa từ audit trước; phiên này không re-read hoặc mutate live.
- Migrations chưa chạy; `migrate:status` báo không có bảng migrations.

## Phân loại đúng

- **Verified hẹp:** Laravel boot, route registration, build, một số Blade page/lookup endpoint trả 200 trên DB rỗng.
- **Verified hẹp/delivered:** lát cắt cập nhật nhân viên trên SQLite/Unit/Feature/frontend và guarded MariaDB `20/436`, route `44`, Composer/lint pass; browser/auth-RBAC evidence vẫn chưa có.
- **Prototype:** dashboard, danh sách nhân viên ngoài lát cắt update, lương, chấm công, nghỉ phép, hệ số lương.
- **Blocked:** home/landing, phòng ban, danh sách lương, pagination chấm công.
- **Planned:** auth/RBAC, hợp đồng, vai trò/quyền/tài khoản, báo cáo; backup/restore vẫn là unsafe legacy procedures.

Không gọi lương/chấm công/nghỉ phép “hoàn thành” chỉ vì có UI/API code.

## Blocker P0

1. Không có route `/`; `/admin` gọi view `frontend.home` không tồn tại.
2. Hai web route phòng ban trỏ method không tồn tại ở `PhongBanController`; route delete nhân viên chưa nằm trong scope.
3. Tên route lương/chấm công/nghỉ phép bị lặp `backend.backend.*`.
4. Phòng ban có Blade lỗi, procedure chi tiết thiếu và update sai placeholder.
5. Code gọi bốn procedure không có trong dump/DB:
   - `sp_phong_ban_chi_tiet`
   - `sp_cham_cong_nhan_vien_phan_trang`
   - `sp_cham_cong_chi_tiet_phan_trang`
   - `sp_luong_tim_kiem_phan_trang`
6. `.env.example` dùng SQLite/database session-cache-queue, lệch runtime nghiệp vụ.
7. Chưa có auth middleware hoặc permission checks.
8. Database nghiệp vụ hiện rỗng và chưa có master-data seed; các FK nhân viên cần phòng ban/chức vụ/trạng thái/vai trò trước mutation.
9. Laravel dùng UTC trong khi MariaDB system timezone là UTC+7; `now()` và `CURDATE()` có thể lệch kỳ.

## Branch frontend

Local branch `frontend` ở `940e7cc`; merge-base với main là `063c669`; hai phía có 17/20 commit riêng.

Shell contract Header + Sidebar + Main + Footer/no global navbar đã được duyệt và pass automated render/controller/build tests trên branch đó; browser acceptance chưa hoàn tất và shell **chưa có trên main**. Xem [ADR-001](decisions/ADR-001-admin-shell.md).

Không tự fetch/merge/rebase/cherry-pick/force-push/tạo upstream hoặc worktree; mọi thao tác tiếp theo cần request riêng.

`docs/CODEX_FRONTEND_HANDOFF.md` là local-only qua `.git/info/exclude`; không stage file này và không coi nó là source of truth cho main.

## Task tiếp theo và sequencing

Task 12 scoped delivery đã complete tại implementation commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f`, đã push và remote xác nhận exact SHA. Scoped code/test re-review **Approve**. Task13 lifecycle/auth DB contracts là bước kế tiếp nhưng **chưa bắt đầu**; Task18 auth/RBAC/Gates là prerequisite trước enablement. Browser acceptance giữ ở Task20.

Task 18 phải hoàn tất auth/RBAC/Gates và actor authorization trước khi có bất kỳ thay đổi nào bật `config('nhanvien.enabled')`. Browser acceptance của lát cắt giữ ở Task 20; không dùng browser chưa chạy để suy rộng thành an toàn vận hành.

Implementation commit đã được push; không tự tạo commit/push tiếp theo và luôn revalidate current HEAD trước khi tiếp tục.

Roadmap đầy đủ: [ROADMAP.md](ROADMAP.md).

## Checklist phiên tiếp theo

```powershell
Get-Content -Raw AGENTS.md
Get-Content -Raw docs/CODEX_NEXT_HANDOFF.md

git status --short --branch
git rev-parse HEAD
php artisan about --only=environment,drivers
php artisan route:list --except-vendor
php artisan test
npm run build
```

Nếu task dùng database:

- Đọc [DATABASE.md](DATABASE.md).
- Chỉ test mutation trên database disposable.
- Kiểm tra procedure signature trước khi sửa PHP.

## Giới hạn bằng chứng

- Không clean-replay dump.
- Không chạy mutation nghiệp vụ.
- Không có browser acceptance mới.
- Response 200 trên database rỗng không chứng minh workflow.
- Vite build không chứng minh API/DB.
- `php artisan db:show` có thể lỗi vì MariaDB local thiếu `performance_schema.session_status`; không dùng riêng lỗi này để kết luận mất kết nối.
