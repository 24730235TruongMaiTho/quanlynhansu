# Handoff tiếp tục đồ án `quanlynhansu`

> Snapshot: 2026-08-21 (Asia/Saigon)
>
> Branch: `main`; module employee đã tích hợp, luôn revalidate HEAD/upstream trước phiên mới
>
> Base HEAD/upstream trước Tasks 13–20: `723dac63983d04364c6f146662aec7bd5eb6d87a`
>
> Source/test/SQL commit: `ba6e0189e64eb3046164ae5183950afe0b5722be`; dependency-lock commit: `18ea209d89efce38596dd1440151f6d55ca90156`.
>
> Documentation-evidence commit: `7bedcadf8c374b38d2e3451617f288bca6184d5f`.
>
> Delivery checkpoint: sau lần push đầu, local HEAD, tracking upstream và remote ref cùng ở `7bedcadf`. Commit ghi trạng thái delivery này nằm sau checkpoint; luôn chạy lại Git status/HEAD/upstream sau khi đồng bộ.

> Integration evidence: `main` fast-forwarded to `origin/main` `1677f202f70e020ce75ef0fa88b11b9db44fa047`, then merged `feature/quanly-nhan-vien` at `aa7741914e60acb0243fcfe08f2d1dbee27b4a1f` with parents `1677f202f70e020ce75ef0fa88b11b9db44fa047` and `91bb7a106e6a4fae8a61c4eb383dad596bf2b199`. Demo seed commit là `91bb7a1`; focused merged-attendance tests là `9cd2c30`. Revalidate remote state rather than relying on this snapshot.

> Local rollout evidence (environment-specific, 2026-08-21): `quan_ly_nhan_su` was intentionally updated through the approved local rollout and demo seed. Verified local shape is 16 tables, 1 view, 8 functions, 10 triggers, 69 procedures; demo state is 5 employees/5 addresses, admin role 5 employee permissions, four normal identities zero employee permissions. Backup is ignored/outside Git. This is not production evidence.

## Đọc trước

1. `AGENTS.md`.
2. [README tài liệu](README.md).
3. [PROJECT_STATUS.md](PROJECT_STATUS.md).
4. [DATABASE.md](DATABASE.md) và `quan_ly_nhan_su.session.sql` nếu task chạm dữ liệu.
5. Route, controller, request, service/repository, model, Blade/JavaScript và test đúng phạm vi.
6. [EMPLOYEE_MODULE_GUIDE.md](EMPLOYEE_MODULE_GUIDE.md) nếu task thuộc module Nhân viên.

Code, route, test và database đang được kiểm tra có ưu tiên cao hơn snapshot này.

## Trạng thái module Nhân viên

Historical Tasks 13–20 đã đưa module tới mức **verified hẹp trên feature branch**; code hiện đã tích hợp vào `main`, chưa phải production-ready:

- danh sách có filter/pagination, tạo, chi tiết, sửa hồ sơ/địa chỉ/avatar, xóa cứng hoặc chuyển nghỉ việc theo dependency, và reset mật khẩu;
- custom employee auth provider, login/logout, session từ chối nhân viên `DA_NGHI`;
- năm quyền `NHAN_VIEN_XEM`, `NHAN_VIEN_TAO`, `NHAN_VIEN_SUA`, `NHAN_VIEN_XOA`, `NHAN_VIEN_DAT_LAI_MAT_KHAU` được map qua Gate; role `NHAN_VIEN_MAC_DINH` giữ đúng zero quyền;
- route `/admin` yêu cầu auth; route nhân viên kiểm tra rollout trước Gate phù hợp; target không thuộc exact role mặc định và actor tự nhắm mình bị chặn trước mutation;
- cấu hình rollout là `env('NHAN_VIEN_MODULE_ENABLED', true)`. Có thể đặt `false` để fail-closed 404; không dùng cờ này thay cho auth/Gate;
- SQL versioned `001`–`006` và canonical dump đã đồng bộ cho schema, read/create/update, lifecycle/auth và RBAC contracts.

## Bằng chứng mới nhất

- Full guarded MariaDB wrapper historical: `165 tests, 3367 assertions, 1 platform skip, exit 0`; rerun sau tích hợp timeout khoảng 184 giây, process/schema/state/marker cleanup sạch, không claim current pass.
- Scoped employee/auth hiện tại: `119 tests, 1141 assertions`; attendance compatibility: `12 pass, 55 assertions`.
- Frontend: `15/15`; Vite 7.3.6 build pass với 16 modules.
- Full Laravel current: `230 pass, 1 fail, 1809 assertions`; failure duy nhất là baseline `Tests\Feature\ExampleTest` kỳ vọng `GET /` 200 trong khi ứng dụng trả 404. Trước vòng authorization/guard là `224/1789`.
- Composer validate/install dry-run pass; `composer audit --locked` không còn advisory sau sáu compatible lock updates. PHP lint, PowerShell parser, route inventory `49` và `git diff --check` pass trong các gate tương ứng.
- Task 19 process-identity/atomic-state regressions đều nằm trong full wrapper sạch; skip duy nhất là Windows từ chối tạo disposable state symlink.
- Independent review của checkpoint trước đã được supersede bởi vòng authorization/guard này; kiểm tra mới phải dựa trên HEAD hiện tại.

`phpunit.xml` dùng SQLite in-memory; chỉ wrapper guarded MariaDB chứng minh stored procedure trên MariaDB 10.4.32. Local rollout/seed ở trên là bằng chứng environment-specific riêng; chưa claim production hoặc MySQL 8.

## Browser acceptance Task 20

Đã xác minh trên acceptance child dùng database disposable:

- login bằng mã/email, logout và protected session;
- list/empty search, create và flash chính xác, detail/edit preload, filter/query/pagination và submitting state;
- reset rồi baseline user bị 403; stale edit sau hard-delete trả 404 an toàn;
- dependency làm action chuyển `DA_NGHI` và lần đăng nhập sau bị từ chối; việc từ chối một session đã mở trước đó được automated-test, chưa recheck trực tiếp trong simultaneous browser context;
- view-only/no-permission và target đặc quyền bị chặn đúng UI/HTTP boundary;
- không thấy SQL/stack/raw exception hoặc hash; console không có warning/error;
- responsive `320/375/768/1024/1440`: không document overflow, bảng cuộn trong `.table-responsive`; ở 320px chỉ ẩn global topbar search, employee filter/title/menu/account vẫn hiện.

Giới hạn còn lại: browser upload/thay avatar **blocked/unverified** vì Chrome extension chưa được cấp file URL access. Automated upload/ownership tests pass nhưng không thay thế browser file chooser. Separate simultaneous browser context được kiểm tra một phần qua automated auth/session tests, không được mô tả là browser-verified đầy đủ.

Official `Stop` đã dọn sạch fixture: guarded schema, state/lock/probe/run/upload, listener 8012, acceptance PHP child và `public/storage` đều `0`; `storage/app/public` dùng chung vẫn được giữ.

## Database safety và lịch sử kiểm tra

- `quan_ly_nhan_su.session.sql` bắt đầu bằng `DROP DATABASE IF EXISTS`; chỉ replay trên database disposable/local không có dữ liệu cần giữ.
- Historical Task 20 integration tests chỉ chạy trên disposable/guarded schema; local rollout hiện tại đã được approval riêng, backup ngoài Git và có demo synthetic. Không suy rộng local rollout thành production.
- Dùng `tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb` cho integration suite.
- Dùng `tests/Support/employee-acceptance.ps1` cho browser fixture; luôn chạy action `Stop`, kể cả khi browser fail.
- Không đưa password/hash, credential, DB URL, cookie/token hoặc dữ liệu cá nhân vào log/handoff.

## Việc còn lại

1. Bật quyền file URL cho Chrome extension rồi chạy riêng avatar upload/replacement acceptance nếu cần đóng khoảng trống browser.
2. Chốt route `/`/landing để sửa baseline `ExampleTest` 404 trong một task riêng.
3. Lập quy trình rollout/master-data/backup trước khi dùng database thật.
4. Chạy lại browser avatar upload khi Chrome file URL access được cấp; các module phòng ban/lương/chấm công/nghỉ phép vẫn có blocker riêng.
5. Không suy rộng trạng thái Nhân viên sang toàn dự án.

## Checklist tiếp tục

```powershell
Get-Content -Raw AGENTS.md
Get-Content -Raw docs/CODEX_NEXT_HANDOFF.md
Get-Content -Raw docs/PROJECT_STATUS.md

git status --short --branch
git rev-parse HEAD
git rev-parse '@{upstream}'
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
```

Nếu task dùng database, đọc [DATABASE.md](DATABASE.md), kiểm tra signature/result shape và chỉ mutation trên target đã được approval/backup/preflight; test routine vẫn phải dùng disposable guard.

## Branch frontend

Local branch `frontend` tại snapshot cũ có shell Header + Sidebar + Main + Footer khác runtime hiện tại và chưa được merge. Không tự fetch/merge/rebase/cherry-pick. `docs/CODEX_FRONTEND_HANDOFF.md` là local-only, bị exclude và tuyệt đối không stage.
