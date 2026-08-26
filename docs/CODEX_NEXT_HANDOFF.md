# Handoff tiếp tục đồ án `quanlynhansu`

> **Cập nhật 2026-08-26:** local `main` đã hòa CRUD Nhân viên, Phòng ban và
> Chức vụ lên nền `ef4685d`, đồng thời giữ nguyên auth/RBAC, Hợp đồng và Phân
> quyền của đồng nghiệp. Reset mật khẩu, rollout flag, department scope và
> target-role guard của nhánh CRUD cũ không còn trong runtime. Các mô tả trái
> với cập nhật này ở phần lịch sử bên dưới không phải contract hiện hành.
>
> **Nguồn DB hiện hành:** lần lượt chạy `database/sql/tao_bang.sql`,
> `database/sql/du_lieu_mau.sql` và `database/sql/quyen_vai_tro.sql` trên
> database rỗng/disposable đã được phê duyệt. `Database\\Seeders\\LocalDemoSeeder`
> đã superseded và chỉ còn để đối chiếu lịch sử.

> Snapshot: 2026-08-24 (Asia/Saigon)
>
> Delivery target: `main`; lát cắt Chức vụ và refactor Phòng ban được chuẩn bị
> từ nền `4357647`. Luôn revalidate HEAD/upstream sau khi tích hợp và trước phiên mới.
>
> Base HEAD/upstream trước Tasks 13–20: `723dac63983d04364c6f146662aec7bd5eb6d87a`
>
> Source/test/SQL commit: `ba6e0189e64eb3046164ae5183950afe0b5722be`; dependency-lock commit: `18ea209d89efce38596dd1440151f6d55ca90156`.
>
> Documentation-evidence commit: `7bedcadf8c374b38d2e3451617f288bca6184d5f`.

> **Current DB source-of-truth:** Build a disposable database from ba file SQL
> active theo thứ tự nêu trên. Contract gồm đúng 15 bảng, 19 nhân viên, 37
> quyền, 12 thủ tục RBAC, mã nhân viên năm chữ số và counter `NHAN_VIEN = 19`.
> `quan_ly_nhan_su.session.sql`, `LocalDemoSeeder` và các script employee
> `2026_08_12_001`–`006` là lịch sử. Guarded fresh MariaDB harness pass
> `12 tests, 422 assertions` trên disposable schema; browser chưa kiểm chứng.

> **Live local 2026-08-26:** sau backup và restore thử độc lập, `quan_ly_nhan_su`
> đã được thay bằng ba SQL active; hậu kiểm đạt 15 bảng, 19 nhân viên, 37
> quyền, 12 thủ tục, counter 19 và orphan khóa ngoại bằng 0. Backup/hash/manifest
> ở `storage/app/backups/`; không thay đổi database nào khác.
>
> Delivery checkpoint: sau lần push đầu, local HEAD, tracking upstream và remote ref cùng ở `7bedcadf`. Commit ghi trạng thái delivery này nằm sau checkpoint; luôn chạy lại Git status/HEAD/upstream sau khi đồng bộ.

> Integration evidence: `main` fast-forwarded to `origin/main` `1677f202f70e020ce75ef0fa88b11b9db44fa047`, then merged `feature/quanly-nhan-vien` at `aa7741914e60acb0243fcfe08f2d1dbee27b4a1f` with parents `1677f202f70e020ce75ef0fa88b11b9db44fa047` and `91bb7a106e6a4fae8a61c4eb383dad596bf2b199`. Demo seed commit là `91bb7a1`; focused merged-attendance tests là `9cd2c30`. Revalidate remote state rather than relying on this snapshot.

> Local rollout evidence (environment-specific, 2026-08-21) is legacy only:
> 16 tables/routines and 5-row demo. The active fresh contract is the 15-table
> SQL set stated above; historical rollout này không thay đổi live DB.

## Đọc trước

1. `AGENTS.md`.
2. [README tài liệu](README.md).
3. [PROJECT_STATUS.md](PROJECT_STATUS.md).
4. [DATABASE.md](DATABASE.md), rồi ba nguồn active `database/sql/tao_bang.sql`,
   `database/sql/du_lieu_mau.sql` và `database/sql/quyen_vai_tro.sql`; chỉ đọc
   `LocalDemoSeeder` cùng dữ liệu SQL khác để đối chiếu lịch sử.
5. Route, controller, request, service/repository, model, Blade/JavaScript và test đúng phạm vi.
6. [EMPLOYEE_MODULE_GUIDE.md](EMPLOYEE_MODULE_GUIDE.md) nếu task thuộc module Nhân viên.

Code, route, test và database đang được kiểm tra có ưu tiên cao hơn snapshot này.

## Phòng ban v1 — active direct repository (2026-08-24)

Branch `feature/quan-ly-chuc-vu` hiện có server-rendered CRUD Phòng
ban dưới auth với route names `backend.phongban.index/create/store/edit/update/destroy`,
param dương `ma_pb`, bốn Gate canonical `PB_*`, model mapping chuẩn,
repository/service dùng Query Builder trực tiếp, form validation và UI runtime
`backend.layouts.app`. Danh sách trả explicit `ma_pb`, `ten_pb`,
`so_nhan_vien`; mutation dùng transaction/row lock và map lỗi an toàn. Các
`sp_phong_ban_*` trong script/test cũ là historical, không phải active repository
contract; lookup legacy của Chấm công nằm ngoài phạm vi task.

Evidence đã chạy: HTTP/controller/view và real SQLite repository/mapper pass;
guarded MariaDB fresh suite pass `12 tests/422 assertions` trên disposable
schema, gồm direct CRUD/count/duplicate/missing/in-use/delete, thủ tục RBAC và
counter. Không claim production/MySQL 8 hoặc browser acceptance.

## Feature branch evidence — Chức vụ v1 (2026-08-24)

Trên local `main`, module Chức vụ có server-rendered CRUD tại `/chuc-vu` với
route names `backend.chucvu.index/create/store/edit/update/destroy`, tham số
dương `ma_cv`, và Gate canonical `ChucVu.Read`, `ChucVu.Insert`,
`ChucVu.Update`, `ChucVu.Delete` (13–16). Sidebar chỉ hiện khi registry có
definition `ChucVu` với quyền `.Read`; hai lookup Chức vụ của Lương/Nghỉ phép
được giữ nguyên.

Repository dùng Query Builder trực tiếp, transaction và `lockForUpdate`; danh
sách chỉ chọn `ma_cv`, `ten_cv`, `he_so_phu_cap`, `so_nhan_vien`. Duplicate,
missing, dependency và lỗi DB được map về mã/thông báo an toàn, không lộ SQL.

Evidence hiện hành: full Laravel `272 tests, 2144 assertions`; frontend `18/18`,
Vite `19 modules`, route list, PHP lint, Composer validate và diff check đã chạy.
Guarded MariaDB fresh suite passed `12 tests, 422 assertions` on a random
disposable schema and cleaned it up. Browser acceptance chưa được kiểm chứng;
local live replacement đã có backup/restore proof riêng, còn production rollout
không được suy rộng từ môi trường local.

## Trạng thái module Nhân viên

Historical Tasks 13–20 đã đưa module tới mức **verified hẹp trên feature branch**; code hiện đã tích hợp vào `main`, chưa phải production-ready:

- danh sách có filter/pagination, tạo, chi tiết, sửa hồ sơ/địa chỉ/avatar, xóa cứng hoặc chuyển nghỉ việc theo dependency; reset mật khẩu đã bị loại khỏi runtime;
- custom employee auth provider, login/logout, session từ chối `ma_tt = 4, 5, 6`;
- bốn Gate ability Nhân viên được repository đối chiếu bằng `ma_quyen` 17–20 cùng symbol dotted/module;
- registry Gate tại `config/permissions.php` dùng symbol dotted thật của catalog và đối chiếu đồng
  thời ID, symbol, module; malformed row hoặc lỗi database fail closed, cache chỉ
  sống trong request scope;
- lookup employee của Chấm công/Nghỉ phép vẫn gọi service nhân viên ở tầng dữ liệu,
  nhưng ranh giới route dùng riêng `ChamCong.Read` hoặc `NghiPhep.Read`; không dùng
  `NhanVien.Read` thay thế và không mở rộng business/UI ngoài gate cần thiết;
- seed dùng role ID `1..5`, status ID `1..6` và quyền dotted ID `1..37` theo ba SQL active;
- route CRUD tại `/nhan-vien`, `/phong-ban`, `/chuc-vu` yêu cầu auth và Gate; không áp dụng
  target-role guard hay department scope riêng;
- rollout flag của module Nhân viên đã được loại bỏ; auth/Gate là ranh giới truy cập hiện hành;
- ba file SQL active tạo đúng 15 bảng, 19 nhân viên, 37 quyền và 12 thủ tục RBAC; SQL `001`–`006` chỉ là legacy history.
- Repository employee/auth/RBAC hiện dùng explicit Query Builder trên fresh
  columns và ID contracts; các test `tests/Integration/MariaDb/*ProcedureTest.php`,
  legacy fixture và native procedure workers còn lại chỉ là historical Task
  12–20 evidence, không phải acceptance path hiện hành.

## Bằng chứng mới nhất

- Full guarded MariaDB wrapper historical: `165 tests, 3367 assertions, 1 platform skip, exit 0`; rerun sau tích hợp timeout khoảng 184 giây, process/schema/state/marker cleanup sạch, không claim current pass.
- Current full Laravel trên branch này: `272 pass, 2144 assertions`; schema contract static:
  `4 pass, 57 assertions`; employee/auth và attendance/leave compatibility
  suites pass. Fresh MariaDB contract hiện pass `12 tests, 422 assertions` trên
  disposable schema, gồm CRUD ba module, 12 thủ tục RBAC và parallel counter;
  không claim production hoặc MySQL 8.
- Historical frontend snapshot: `15/15`; Vite 7.3.6 build pass với 16 modules.
- Historical pre-refactor full snapshot: `237 pass, 1820 assertions`; current
  full result is recorded above and must be used for this handoff.
- Composer validate/install dry-run pass; `composer audit --locked` không còn advisory sau sáu compatible lock updates. PHP lint, PowerShell parser, route inventory `73` và `git diff --check` pass trong các gate tương ứng.
- Task 19 process-identity/atomic-state regressions đều nằm trong full wrapper sạch; skip duy nhất là Windows từ chối tạo disposable state symlink.
- Independent review của checkpoint trước đã được supersede bởi vòng authorization/guard này; kiểm tra mới phải dựa trên HEAD hiện tại.

`phpunit.xml` dùng SQLite in-memory; `phpunit.mariadb.xml` chạy fresh 15-table
contract bằng ba SQL active, thủ tục RBAC, direct counter worker và ba repository;
pass `12 tests, 422 assertions` trên disposable schema. Không claim production
hoặc MySQL 8; browser chưa được kiểm chứng.

## Browser acceptance Task 20 (Lịch sử)

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
2. Chốt landing `/admin`/`frontend.home` và sửa view còn thiếu; entrypoint `/` login/dashboard đã hoàn tất.
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
