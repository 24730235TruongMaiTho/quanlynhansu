# Handoff tiếp tục đồ án `quanlynhansu`

> Snapshot: 2026-08-21 (Asia/Saigon)
>
> Branch: `feature/quanly-nhan-vien`
>
> Base HEAD/upstream trước Tasks 13–20: `723dac63983d04364c6f146662aec7bd5eb6d87a`
>
> Source/test/SQL commit: `ba6e0189e64eb3046164ae5183950afe0b5722be`; dependency-lock commit: `18ea209d89efce38596dd1440151f6d55ca90156`.
>
> Documentation-evidence commit: `7bedcadf8c374b38d2e3451617f288bca6184d5f`.
>
> Delivery checkpoint: sau lần push đầu, local HEAD, tracking upstream và remote ref cùng ở `7bedcadf`. Commit ghi trạng thái delivery này nằm sau checkpoint; luôn chạy lại Git status/HEAD/upstream sau khi đồng bộ.

## Đọc trước

1. `AGENTS.md`.
2. [README tài liệu](README.md).
3. [PROJECT_STATUS.md](PROJECT_STATUS.md).
4. [DATABASE.md](DATABASE.md) và `quan_ly_nhan_su.session.sql` nếu task chạm dữ liệu.
5. Route, controller, request, service/repository, model, Blade/JavaScript và test đúng phạm vi.

Code, route, test và database đang được kiểm tra có ưu tiên cao hơn snapshot này.

## Trạng thái module Nhân viên

Tasks 13–20 đã đưa module tới mức **verified hẹp trên branch**, chưa phải production-ready:

- danh sách có filter/pagination, tạo, chi tiết, sửa hồ sơ/địa chỉ/avatar, xóa cứng hoặc chuyển nghỉ việc theo dependency, và reset mật khẩu;
- custom employee auth provider, login/logout, session từ chối nhân viên `DA_NGHI`;
- năm quyền `NHAN_VIEN_XEM`, `NHAN_VIEN_TAO`, `NHAN_VIEN_SUA`, `NHAN_VIEN_XOA`, `NHAN_VIEN_DAT_LAI_MAT_KHAU` được map qua Gate; role `NHAN_VIEN_MAC_DINH` giữ đúng zero quyền;
- route `/admin` yêu cầu auth; route nhân viên kiểm tra rollout trước Gate phù hợp; target không thuộc exact role mặc định và actor tự nhắm mình bị chặn trước mutation;
- cấu hình rollout là `env('NHAN_VIEN_MODULE_ENABLED', true)`. Có thể đặt `false` để fail-closed 404; không dùng cờ này thay cho auth/Gate;
- SQL versioned `001`–`006` và canonical dump đã đồng bộ cho schema, read/create/update, lifecycle/auth và RBAC contracts.

## Bằng chứng mới nhất

- Full guarded MariaDB wrapper: `165 tests, 3367 assertions, 1 platform skip, exit 0`; schema/state/lock/run/upload/listener/PHP/`public/storage` cleanup `0` và giữ nguyên `storage/app/public`.
- Unit: `95 tests, 633 assertions`; scoped Feature employee/auth/compatibility: `107 tests, 1093 assertions`.
- Frontend: `15/15`; Vite 7.3.6 build pass với 16 modules.
- Full Laravel: `221 pass, 1 fail, 1772 assertions`; failure duy nhất là baseline `Tests\Feature\ExampleTest` kỳ vọng `GET /` 200 trong khi ứng dụng trả 404.
- Composer validate/install dry-run pass; `composer audit --locked` không còn advisory sau sáu compatible lock updates. PHP lint, PowerShell parser, route inventory `49` và `git diff --check` pass trong các gate tương ứng.
- Task 19 process-identity/atomic-state regressions đều nằm trong full wrapper sạch; skip duy nhất là Windows từ chối tạo disposable state symlink.
- Independent final review: **Spec PASS / Quality APPROVE**, không còn finding Critical/Important.

`phpunit.xml` dùng SQLite in-memory; chỉ wrapper guarded MariaDB chứng minh stored procedure trên MariaDB 10.4.32. Chưa claim MySQL 8 hoặc database live.

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

## Database safety

- `quan_ly_nhan_su.session.sql` bắt đầu bằng `DROP DATABASE IF EXISTS`; chỉ replay trên database disposable/local không có dữ liệu cần giữ.
- Không chạy employee mutation test trên database live/configured.
- Dùng `tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb` cho integration suite.
- Dùng `tests/Support/employee-acceptance.ps1` cho browser fixture; luôn chạy action `Stop`, kể cả khi browser fail.
- Không đưa password/hash, credential, DB URL, cookie/token hoặc dữ liệu cá nhân vào log/handoff.

## Việc còn lại

1. Bật quyền file URL cho Chrome extension rồi chạy riêng avatar upload/replacement acceptance nếu cần đóng khoảng trống browser.
2. Chốt route `/`/landing để sửa baseline `ExampleTest` 404 trong một task riêng.
3. Lập quy trình rollout/master-data/backup trước khi dùng database thật.
4. Mở review/PR và merge feature branch khi nhóm duyệt; chưa merge vào `main` trong lượt này.
5. Các module phòng ban/lương/chấm công/nghỉ phép vẫn có blocker riêng; không suy rộng trạng thái Nhân viên sang toàn dự án.

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

Nếu task dùng database, đọc [DATABASE.md](DATABASE.md), kiểm tra signature/result shape và chỉ mutation trên target disposable được guard.

## Branch frontend

Local branch `frontend` tại snapshot cũ có shell Header + Sidebar + Main + Footer khác runtime hiện tại và chưa được merge. Không tự fetch/merge/rebase/cherry-pick. `docs/CODEX_FRONTEND_HANDOFF.md` là local-only, bị exclude và tuyệt đối không stage.
