# Trạng thái dự án

> Snapshot: 2026-08-24
>
> Historical Task 20 branch: `feature/quanly-nhan-vien`; lát cắt Chức vụ và
> refactor Phòng ban được chuẩn bị từ `feature/quan-ly-chuc-vu` trên base `4357647`
> với delivery target `main`; revalidate HEAD/upstream sau tích hợp.
>
> Phạm vi historical: Tasks 13–20 đã được kiểm chứng hẹp, commit và push lên `origin/feature/quanly-nhan-vien`: source/test/SQL `ba6e0189e64eb3046164ae5183950afe0b5722be`, dependency locks `18ea209d89efce38596dd1440151f6d55ca90156`, documentation evidence `7bedcadf8c374b38d2e3451617f288bca6184d5f`. Main đã fast-forward tới `origin/main` `1677f202f70e020ce75ef0fa88b11b9db44fa047` và merge feature tại `aa7741914e60acb0243fcfe08f2d1dbee27b4a1f`; focused attendance tests ở `9cd2c30`. Khi tiếp tục phải revalidate HEAD/upstream, không dùng snapshot này để suy ra trạng thái remote.

> **Fresh DB contract (2026-08-24):** `database/tao_bang.sql` plus
> `database/du_lieu_mau.sql` is now the active fresh source: exactly 15 tables,
> no required routine/view/trigger, direct address columns on `nhan_vien`,
> explicit role/status/permission IDs and locked `NV001..NV999` counter. The
> former 16-table/routine dump is historical only. Focused SQLite tests are
> GREEN; the guarded disposable MariaDB fresh harness now also covers direct
> Chức vụ and Phòng ban CRUD/count behavior and passed `7 tests, 231 assertions` on a
> random disposable schema in this turn. Browser avatar remains a separate gate.

## Current integrated module và rollout (2026-08-24)

### Phòng ban v1 — active direct repository (2026-08-24)

Trên branch `feature/quan-ly-chuc-vu`, CRUD web Phòng ban đã được nối
với route REST dưới auth và bốn Gate canonical `PB_VIEW/PB_CREATE/PB_EDIT/PB_DELETE`.
Danh sách hiển thị `ma_pb`, `ten_pb`, `so_nhan_vien`; form có validation, old
input, lỗi an toàn, empty/success/server-error, action gating và chặn xóa khi
đang có nhân viên. `PhongBanRepository` hiện dùng Query Builder trực tiếp trên
`phong_ban`/`nhan_vien`, explicit shape, transaction và row lock; các routine
`sp_phong_ban_*` trong script/test cũ chỉ là historical, không phải active data
contract.

Bằng chứng tự động: HTTP/controller/view và real SQLite repository/mapper pass;
scoped Department là `19 tests, 157 assertions`. Guarded fresh MariaDB replay
pass `7 tests, 231 assertions`, gồm direct CRUD/count/duplicate/missing/in-use,
delete và postcheck 0 routine trên disposable schema. Không claim live mutation
hoặc browser acceptance.

- Main là nguồn tích hợp hiện tại; merge `aa77419` có parents `1677f20` và `91bb7a1` (`feat(employee-db): add guarded local demo seed`).
- Rollout local 16 bảng/routines và demo 5-row cũ chỉ là bằng chứng
  environment-specific historical, không phải nguồn fresh và không được chạy
  lại.
- Fresh pair hiện có seed deterministic 30 employee mẫu tiếng Việt; `NV001`
  là `Nguyễn Văn An` (`ma_vt = 1`, `ma_tt = 2`,
  `an.nguyen@company.com`) với bcrypt hợp lệ. Cả 30 row dùng convention local/
  demo `nhom3@2026`; role 2 có đúng thêm `201–204, 301–304, 401–404` theo
  least privilege và counter 30. Backup/live DB không nằm trong scope.
- Hướng dẫn authoritative: [EMPLOYEE_MODULE_GUIDE.md](EMPLOYEE_MODULE_GUIDE.md). Avatar browser vẫn blocked/unverified và chưa claim MySQL 8.
- Permission registry mở rộng qua `config/permissions.php`; sidebar module visibility
  yêu cầu definition `_VIEW` đúng module, còn route/action vẫn exact Gate. Lookup
  employee của Chấm công/Nghỉ phép chỉ giữ shared `NV_VIEW` compatibility dependency.
- Current full Laravel trước vòng Phòng ban là `280 pass, 2217 assertions`; Vite build,
  Composer, route inventory và diff check đều pass. Guarded MariaDB fresh replay
  hiện pass `7 tests, 231 assertions` trên disposable schema, gồm Chức vụ và
  Phòng ban direct CRUD;
  không claim live DB/production/browser pass.

### Feature branch evidence — Chức vụ v1 (2026-08-24)

Module Chức vụ đã nối server-rendered CRUD dưới auth với catalog canonical
`CV_VIEW/CV_CREATE/CV_EDIT/CV_DELETE` (301–304), registry/config extensible,
sidebar `_VIEW`, validation server-side và Query Builder trực tiếp trên fresh
15-table schema. Repository trả shape tường minh, chuẩn hóa hệ số 2 chữ số,
transaction/row lock và map duplicate/missing/in-use/unknown DB error an toàn.
Các route lookup của Lương/Nghỉ phép không đổi.

Evidence trước lát cắt Phòng ban: Chức vụ HTTP `8 tests, 88 assertions`; nhóm
authorization/service, real SQLite repository và mapper `20 tests, 97 assertions`;
frontend `17/17`; full Laravel `280/2217`; build tạo 18 modules. Fresh MariaDB suite hiện có 7
tests, `231 assertions` trên disposable schema và đã cleanup; browser chưa chạy.

## Bằng chứng historical — Task 20 Phase E trước merge vào main (2026-08-21)

Đây là mục authoritative mới nhất; các số liệu lịch sử bên dưới được giữ để
truy nguyên nhưng không được dùng thay cho gate mới.

- Full guarded MariaDB wrapper đã pass với `165 tests, 3367 assertions, 1 platform skip,
  exit 0`; mọi schema disposable được cleanup. Ở checkpoint historical trước
  approved local rollout, runtime chỉ dùng database test có tên guarded và
  `quan_ly_nhan_su` chưa bị mutation; phần Current rollout bên trên ghi nhận
  mutation local đã được duyệt sau checkpoint đó.
- Historical Task20 scoped/browser numbers remain historical only. Current
  employee/auth/compatibility Feature/Unit suites and schema contract static
  test pass; full Laravel is `265 pass, 2086 assertions`. Historical fresh
  MariaDB DDL/FK/seed/RBAC/CRUD/lifecycle/migration test pass `5/161`, including
  direct-repository parallel counter concurrency; current branch rerun is
  recorded in the Chức vụ evidence above.
- Browser acceptance trên acceptance child đã xác minh login/logout, CRUD và
  auth/RBAC/stale/filter/flash/edit mapping theo handoff; session restore sau
  khi chuyển nghỉ việc là automated evidence riêng. Recheck responsive
  thật ở `320/375/768/1024/1440` pass với `documentOverflow=false`, bảng cuộn
  trong `.table-responsive`, và console không có lỗi. Ở 320px chỉ topbar search
  placeholder bị ẩn; filter nhân viên, title, hamburger và account vẫn hiện.
- Avatar upload/replacement browser vẫn **blocked/unverified** vì Chrome
  extension không cho cấp file URL access. Automated upload tests xanh; không
  suy rộng thành browser upload pass.
- Official Stop đã pass sau browser: state/lock/probe/artifact/run/upload,
  listener `8012`, PHP acceptance child, `public/storage` và guarded schema đều
  `0`; `storage/app/public` được giữ nguyên. Không còn runtime resource thuộc
  harness.
- Historical module nhân viên là **verified hẹp và đã Git-deliver trên feature
  branch**; code hiện đã tích hợp vào `main`, chưa phải production
  readiness: avatar browser còn blocked và full-suite `/` baseline còn fail.

## Cách đọc trạng thái

- **Wired**: route/controller/view hoặc API client đã nối ở mức cấu trúc.
- **Prototype**: có code/giao diện để phát triển tiếp nhưng chưa đủ bằng chứng nghiệp vụ.
- **Blocked**: có lỗi đã biết khiến luồng chính không chạy.
- **Planned**: mới có model/file rỗng hoặc chỉ nằm trong phạm vi dự kiến.
- **Verified hẹp**: chỉ khẳng định đúng kiểm tra được nêu, không suy rộng thành “module hoàn thành”.

`blocked`, `unreachable`, `unsafe` là modifier cho trạng thái chính, không phải mức hoàn thành độc lập.

## Baseline kỹ thuật

| Kiểm tra | Kết quả |
| --- | --- |
| Git | Main tích hợp merge `aa77419`, parents `1677f20`/`91bb7a1`; revalidate HEAD/upstream khi tiếp tục |
| MCP code graph | 1.819 node, 2.454 edge; dùng để khám phá code, không dùng thay cho route runtime |
| `php artisan route:list --except-vendor` | Pass; department routes are present under `admin/phong-ban`; full inventory rechecked |
| `php artisan test` | `287 pass, 2273 assertions`; includes Chức vụ/Phòng ban HTTP, real SQLite repositories/mappers and RBAC integration |
| `npm run test:frontend` | Pass; 17 tests |
| `npm run build` | Pass; Vite 7.3.6, 18 modules transformed |
| Composer dependency gates | Validate/install dry-run pass; `composer audit --locked` không còn advisory sau sáu compatible lock updates |
| MariaDB fresh contract | **Verified hẹp**; guarded disposable replay `7 tests, 231 assertions`, including Chức vụ/Phòng ban direct CRUD/count, migration/cleanup and counter concurrency; no live mutation |
| Task 19 harness regression/review | Historical Task 20 wrapper có process-identity/atomic-state evidence; rerun hiện tại chưa pass nên không suy rộng review cũ thành current DB gate |
| Employee rollout flag | `env('NHAN_VIEN_MODULE_ENABLED', true)`; đặt `false` sẽ fail-closed 404 nhưng không thay thế auth/Gate |
| `php artisan migrate:status` | Fail: chưa có bảng `migrations` |

## Ma trận module

| Module | Web/UI | API/data | Test | Trạng thái và blocker |
| --- | --- | --- | --- | --- |
| Home/landing | Root `/` redirect guest tới login và authenticated tới dashboard; named route `backend.frontend.home` tại `/admin` vẫn thiếu target view `frontend.home` | Không | Root redirect test pass | **Prototype — landing view blocked** |
| Dashboard | `/admin/bang-dieu-khien` render 200 | Chưa có dữ liệu | Không | **Prototype** |
| Phòng ban | Server-rendered index/create/edit; action gating và trạng thái an toàn | Direct Query Builder trên `phong_ban` + employee count; transaction/row lock; không routine | HTTP/controller/view + real SQLite repository/mapper `19/157`; MariaDB `7/231` disposable fresh CRUD/count/error; browser unverified | **Verified hẹp trên SQLite/MariaDB disposable; browser acceptance và live rollout unverified** |
| Nhân viên | List/create/detail/edit/lifecycle/reset/login UI; responsive browser pass hẹp | Fresh 15-table SQL pair, direct Query Builder repository/service, auth provider, session guard và 5 ID-based permission Gates | Scoped employee/auth tests pass; MariaDB fresh contract `7/231` pass, gồm Chức vụ/Phòng ban, migration/cleanup và counter concurrency; browser avatar chưa chạy | **Verified hẹp và chưa production-ready**: live rollout/browser avatar còn unverified |
| Chức vụ | Server-rendered index/create/edit, exact CV_* action gating, empty/error/success/submitting states | Direct Query Builder on fresh `chuc_vu` + employee count; transaction/row lock; no routine | HTTP `8/88`; repository+mapper real SQLite and RBAC included in scoped `20/97`; MariaDB `7/231` disposable | **Verified hẹp trên SQLite/HTTP/MariaDB disposable; browser unverified** |
| Lương | Trang render, JS CRUD và hệ số được build | API resource + service/repository | Không | **Prototype — blocked**: thiếu procedure danh sách; write contract chưa ngăn trùng `(ma_nv, ky_luong)`; export/đối soát chưa có handler đầy đủ |
| Hệ số lương | UI tích hợp trong trang lương | API đọc/thêm/sửa dùng Query Builder; JavaScript có delete nhưng API chưa có route DELETE | Không | **Prototype — blocked action**: validation lệch schema, mutation chưa xác minh |
| Chấm công | Trang render, JS tải/cập nhật được nối | 4 API route; index Query Builder, lookup/read/update có auth + rollout + Gate | Attendance compatibility `16 pass, 61 assertions` | **Prototype — blocked**: import/export chưa có consumer an toàn; các module khác còn contract riêng |
| Nghỉ phép | Trang render, JS CRUD/duyệt được nối | 12 API route, service/repository và một số query trực tiếp | Không | **Prototype**: lookup/danh sách hẹp trả 200 trên DB rỗng; mutation chưa xác minh |
| Hợp đồng | Không | Controller rỗng, model shell | Không | **Planned** |
| Vai trò/quyền/tài khoản | Chưa có UI quản trị | 15-table RBAC schema và assignment nội bộ guarded cho bootstrap | SQLite/unit ID contract + MariaDB fresh `7/231` pass; UI chưa có | **Nền tảng verified hẹp; UI quản trị planned** |
| Auth/RBAC | Login/logout và topbar auth đã wired | Custom employee provider, session fail-closed, permission cache/Gates | Feature + MariaDB + browser boundary pass hẹp | **Verified hẹp cho module nhân viên**, chưa phải security audit production toàn hệ thống |
| Báo cáo | Nút/mục tiêu rời rạc | Chưa có workflow | Không | **Planned** |
| Backup/restore | Không có workflow an toàn | SP legacy sinh cú pháp SQL Server | Không | **Planned — unsafe legacy procedures** |

## Module Nhân viên (snapshot 2026-08-24)

Module có list/filter/pagination, create, detail, update hồ sơ/địa chỉ/avatar,
delete-or-terminate, reset password, custom authentication và năm Gate nhân
viên. Route khóa mã `NV###`; request/service/repository không nhận role, mã,
hash hoặc ngày nghỉ từ client. Target web phải có `ma_vt = 5`; target role khác
bị chặn trước mutation. Role/status/permission đều
dùng ID, không dùng symbol DB.

### Rollout và authorization

`config/nhanvien.php` dùng `env('NHAN_VIEN_MODULE_ENABLED', true)`. Cờ `false` vẫn trả 404 trước Gate/service; khi bật, mọi `/admin` yêu cầu `auth` và mỗi employee route/Blade action dùng đúng một trong năm quyền. Hai lookup nhân viên dùng chung ở chấm công/nghỉ phép cũng yêu cầu auth, rollout và quyền XEM. Cờ rollout chỉ là kill switch, không phải authorization.

Auth provider lookup trả đúng sáu cột server-only và không expose hash ra controller/UI. Login dùng generic error, throttle theo identifier/IP, session regenerate; session restore từ chối `DA_NGHI`. Reset/lifecycle chỉ tạo hoặc truyền hash trong Laravel/repository boundary; plaintext không được flash/log/trả về.

Fresh SQL contract nằm ở `database/tao_bang.sql` + `database/du_lieu_mau.sql`
với đúng 15 bảng. Hồ sơ + địa chỉ + avatar chạy trong một transaction write;
file avatar mới được bù trừ khi rollback, file cũ chỉ xóa sau commit nếu path
thuộc prefix an toàn. Lifecycle hard-delete khi không có dependency, ngược lại
chuyển `ma_tt = 4` và ghi ngày nghỉ.

Browser Task 20 đã kiểm tra login/logout, CRUD, filter/flash/edit mapping, stale 404, lifecycle/RBAC boundaries và responsive `320/375/768/1024/1440`; console sạch. Session restore của tài khoản chuyển `DA_NGHI` và double-submit được automated-test, không suy rộng thành simultaneous-browser proof. Avatar browser upload/replacement còn blocked do Chrome extension file permission, dù automated upload/ownership tests xanh. Chưa có quy trình rollout database thật, module chỉ được gọi **verified hẹp**, không gọi production-ready.

Fresh MariaDB harness dùng target guarded/disposable, replay hai file active,
assert đúng 15 bảng/seed/RBAC và chạy direct repository CRUD/address/avatar/
counter/lifecycle; migration từ fixture 16 bảng và cleanup allowlist; hai worker
repository cấp `NV031`/`NV032` độc lập. Phòng ban và Chức vụ direct CRUD/count
cũng được kiểm tra; historical base pass `5 tests, 161 assertions`, branch hiện
pass `7 tests, 231 assertions` trên disposable schema và cleanup sạch. Browser
avatar chưa chạy; không dùng kết quả này để claim live DB/production.

## Route và controller đang lệch

Module Phòng ban v1 đã thay route lỗi bằng `index/create/store/edit/update/destroy`
với param số dương `ma_pb`; các lệch route còn lại bên dưới thuộc module khác.

Module Chức vụ v1 dùng route `backend.chucvu.*`, Gate `CV_*` và direct Query
Builder; không còn caller active tới các routine `sp_chuc_vu_*` legacy.

Module nhân viên đã có index/create/store/show/edit/update/destroy/reset-password; các route này được bảo vệ bởi auth, rollout và Gate tương ứng.

Các route lương/chấm công/nghỉ phép nằm trong group đã có prefix tên `backend.` nhưng lại tự thêm `backend.`, tạo các tên:

- `backend.backend.luong.index`
- `backend.backend.chamcong.index`
- `backend.backend.nghiphep.index`

API naming cũng chưa theo một contract:

- Resource routes dùng tên như `luong.index`, `cham-cong.index`, `nghi-phep.index` thay vì `api.v1.*`.
- Một số route nghỉ phép chưa có name.

Root `/` hiện là entrypoint: guest được chuyển tới `/dang-nhap`, user đã xác thực tới `/admin/bang-dieu-khien`. Route home legacy vẫn được khai báo bên trong prefix `admin`, tạo `/admin`, nhưng view `frontend.home` chưa tồn tại.

## Data contract đang lệch

Code còn gọi một procedure ngoài module nhân viên không có trong canonical dump:

1. `sp_luong_tim_kiem_phan_trang`

`ChamCongController@index` dùng Query Builder trên cột canonical vì
`sp_cham_cong_chi_tiet_phan_trang` không tồn tại; không ghi procedure này vào
backlog missing caller.

Ngoài ra:

- `PhongBanRepository` active dùng Query Builder trực tiếp, explicit shape và
  transaction/row lock; `sp_phong_ban_*` trong dump/script là historical only.
- `ChamCongController` vẫn có lookup legacy gọi `sp_phong_ban_danh_sach`; đây là
  dependency ngoài phạm vi refactor Phòng ban và chưa được thay đổi trong task này.
- Model `ChamCong` dùng tên bảng/cột không khớp `cham_cong.ngay_lam`.
- Model `NhanVien` đã map table/key/timestamps và ẩn `mat_khau`; `PhongBan` v1 đã map table/key/timestamps và quan hệ `nhanViens`.
- Validation `ma_nv`, hệ số và ngày hiệu lực chưa thống nhất với schema.
- Một số exception trả nguyên message của DB ra JSON, có nguy cơ lộ chi tiết nội bộ.

## Frontend và branch

Main hiện dùng `resources/views/backend/layouts/app.blade.php`: Sidebar + Topbar + content, Bootstrap/CDN và asset dưới `public/backend`.

Layout này chưa có cấu trúc `<head>` hợp lệ; meta/link đang nằm trực tiếp dưới `<html>`.

Local branch `frontend` tại `940e7cc` có shell contract đã duyệt khác hẳn: Header + Sidebar + Main + Footer, không global navbar, asset landing/admin tách biệt. Automated render/controller/build tests đã pass trên branch đó; browser acceptance vẫn chưa hoàn tất. Hai branch phân kỳ 17/20 commit từ merge-base `063c669`; shell này **chưa** có trong `main`.

Không merge/rebase/cherry-pick tự động. Xem [ADR-001](decisions/ADR-001-admin-shell.md) và [FRONTEND_GUIDE.md](FRONTEND_GUIDE.md).

## Điều chưa được xác minh

- Historical canonical dump replay đã chạy trên disposable schema qua `CanonicalDumpReplayTest`; rollout local hiện tại được ghi riêng ở phần Current integrated module và không phải production acceptance.
- Không chạy mutation CRUD trên production/live cần giữ dữ liệu; local demo rollout có backup/preflight và synthetic scope, employee mutation tests vẫn chạy trong disposable schema do guard tạo và đã cleanup.
- Guarded full employee procedure wrapper historical đã pass `165 tests, 3367
  assertions, 1 platform skip`; rerun sau tích hợp timeout khoảng 184 giây và
  cleanup schema/state/marker/process sạch. Nó không phải fresh contract gate;
  fresh direct-Query-Builder gate trên base lịch sử pass `5 tests, 161
  assertions`; trên branch hiện tại pass `7 tests, 231 assertions` trên
  disposable schema.
- Browser matrix chức năng/RBAC/responsive đã có; avatar upload/replacement còn blocked và separate simultaneous context không được browser-verified đầy đủ.
- Không dùng local demo rollout hoặc response 200 trên danh sách rỗng để chứng minh production logic; cần rollout evidence/approval riêng cho môi trường thật.
- Chưa xác minh tương thích MySQL 8; runtime hiện tại chỉ là MariaDB 10.4.32.
- Full Laravel current đã xanh; landing view legacy `/admin` và các blocker module khác vẫn chưa được xử lý.
- Browser Phòng ban chưa được gọi; MariaDB direct repository suite đã pass trên
  disposable guard, còn live rollout vẫn không được thực hiện.

## Khi nào được đổi trạng thái thành hoàn thành

Một module chỉ được đánh dấu **Done** khi có đủ:

1. Route và tên route đúng.
2. Controller/action tồn tại và validation khớp schema.
3. Model/query/procedure có hợp đồng được kiểm tra.
4. UI có loading, empty, success, validation error và server error.
5. Auth/authorization phù hợp.
6. Feature/integration test pass trên database test.
7. Build pass và browser acceptance phù hợp.
8. Không còn blocker của module trong tài liệu này.
