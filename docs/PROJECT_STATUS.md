# Trạng thái dự án

> Snapshot: 2026-08-21
>
> Historical Task 20 branch: `feature/quanly-nhan-vien`; current integrated branch: `main`
>
> Phạm vi historical: Tasks 13–20 đã được kiểm chứng hẹp, commit và push lên `origin/feature/quanly-nhan-vien`: source/test/SQL `ba6e0189e64eb3046164ae5183950afe0b5722be`, dependency locks `18ea209d89efce38596dd1440151f6d55ca90156`, documentation evidence `7bedcadf8c374b38d2e3451617f288bca6184d5f`. Main đã fast-forward tới `origin/main` `1677f202f70e020ce75ef0fa88b11b9db44fa047` và merge feature tại `aa7741914e60acb0243fcfe08f2d1dbee27b4a1f`; focused attendance tests ở `9cd2c30`. Khi tiếp tục phải revalidate HEAD/upstream, không dùng snapshot này để suy ra trạng thái remote.

## Current integrated module và rollout (2026-08-21)

- Main là nguồn tích hợp hiện tại; merge `aa77419` có parents `1677f20` và `91bb7a1` (`feat(employee-db): add guarded local demo seed`).
- `quan_ly_nhan_su` local đã được cập nhật có chủ đích qua rollout được approval và demo synthetic; đây là bằng chứng environment-specific, không phải production. Shape đã kiểm chứng: 16 bảng, 1 view, 8 function, 10 trigger, 69 procedure.
- Demo hiện có 5 employee và 5 address; admin demo có đúng 5 employee permission; bốn normal demo zero employee permission. IDs local hiện `NV006`–`NV010` nhưng không ổn định sau cleanup/reseed. Backup nằm ngoài Git/ignored.
- Hướng dẫn authoritative: [EMPLOYEE_MODULE_GUIDE.md](EMPLOYEE_MODULE_GUIDE.md). Avatar browser vẫn blocked/unverified và chưa claim MySQL 8.
- Current full Laravel là `237 pass, 1820 assertions`; root `/` đã có regression guest → login và authenticated → dashboard. Trước entrypoint, snapshot là `234 pass, 1 fail, 1815 assertions`; trước vòng authorization/guard là `224/1789`. Scoped employee/auth snapshot là `119 pass, 1141 assertions`; attendance compatibility hiện là `16 pass, 61 assertions`. Frontend là `15/15`, build Vite 16 modules, route inventory 52. Guarded MariaDB rerun sau tích hợp timeout khoảng 184 giây và cleanup sạch; không claim rerun pass.

## Bằng chứng historical — Task 20 Phase E trước merge vào main (2026-08-21)

Đây là mục authoritative mới nhất; các số liệu lịch sử bên dưới được giữ để
truy nguyên nhưng không được dùng thay cho gate mới.

- Full guarded MariaDB wrapper đã pass với `165 tests, 3367 assertions, 1 platform skip,
  exit 0`; mọi schema disposable được cleanup. Ở checkpoint historical trước
  approved local rollout, runtime chỉ dùng database test có tên guarded và
  `quan_ly_nhan_su` chưa bị mutation; phần Current rollout bên trên ghi nhận
  mutation local đã được duyệt sau checkpoint đó.
- Scoped employee Feature/Unit, frontend `15/15`, Vite build, Composer, route
  inventory và diff checks đã pass trong các gate Task20. Full Laravel vẫn có
  đúng một baseline failure: `Tests\\Feature\\ExampleTest` kỳ vọng `GET /` là
  `200`, trong khi route hiện tại trả `404`.
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
| `php artisan route:list --except-vendor` | Pass; 52 route, gồm root login/dashboard entrypoint, login/logout, aliases `/login` và employee lifecycle routes |
| `php artisan test` | `237 pass, 1820 assertions`; root entrypoint guest/authenticated redirects pass; prior snapshot `234 pass, 1 fail, 1815 assertions`; scoped employee/auth snapshot `119/1141`, attendance compatibility `16 pass/61 assertions` |
| `npm run test:frontend` | Pass; 15 tests |
| `npm run build` | Pass; Vite 7.3.6, 16 modules transformed |
| Composer dependency gates | Validate/install dry-run pass; `composer audit --locked` không còn advisory sau sáu compatible lock updates |
| MariaDB employee integration | **Unverified sau tích hợp**; guarded rerun timeout khoảng 184 giây, process/schema/state/marker cleanup sạch; `165/3367` chỉ là historical Task 20 |
| Task 19 harness regression/review | Historical Task 20 wrapper có process-identity/atomic-state evidence; rerun hiện tại chưa pass nên không suy rộng review cũ thành current DB gate |
| Employee rollout flag | `env('NHAN_VIEN_MODULE_ENABLED', true)`; đặt `false` sẽ fail-closed 404 nhưng không thay thế auth/Gate |
| `php artisan migrate:status` | Fail: chưa có bảng `migrations` |

## Ma trận module

| Module | Web/UI | API/data | Test | Trạng thái và blocker |
| --- | --- | --- | --- | --- |
| Home/landing | Root `/` redirect guest tới login và authenticated tới dashboard; named route `backend.frontend.home` tại `/admin` vẫn thiếu target view `frontend.home` | Không | Root redirect test pass | **Prototype — landing view blocked** |
| Dashboard | `/admin/bang-dieu-khien` render 200 | Chưa có dữ liệu | Không | **Prototype** |
| Phòng ban | Blade index/create lỗi | Controller gọi SP trực tiếp | Không | **Prototype — blocked**: route trỏ method thiếu, SP chi tiết thiếu, update sai placeholder |
| Nhân viên | List/create/detail/edit/lifecycle/reset/login UI; responsive browser pass hẹp | SQL `001`–`006`, repository/service, auth provider, session guard và 5 permission Gates | Scoped employee/auth snapshot `119/1141`; attendance `16/61`; frontend `15`; browser function/RBAC/responsive matrix; MariaDB current rerun timeout; full Laravel `237/1820` | **Verified hẹp và đã tích hợp vào main; chưa production-ready**: browser avatar upload còn blocked |
| Chức vụ | Chưa có route/view | Có controller/service/repository/request/model | Không | **Prototype — unreachable** |
| Lương | Trang render, JS CRUD và hệ số được build | API resource + service/repository | Không | **Prototype — blocked**: thiếu procedure danh sách; write contract chưa ngăn trùng `(ma_nv, ky_luong)`; export/đối soát chưa có handler đầy đủ |
| Hệ số lương | UI tích hợp trong trang lương | API đọc/thêm/sửa dùng Query Builder; JavaScript có delete nhưng API chưa có route DELETE | Không | **Prototype — blocked action**: validation lệch schema, mutation chưa xác minh |
| Chấm công | Trang render, JS tải/cập nhật được nối | 4 API route; index Query Builder, lookup/read/update có auth + rollout + Gate | Attendance compatibility `16 pass, 61 assertions` | **Prototype — blocked**: import/export chưa có consumer an toàn; các module khác còn contract riêng |
| Nghỉ phép | Trang render, JS CRUD/duyệt được nối | 12 API route, service/repository và một số query trực tiếp | Không | **Prototype**: lookup/danh sách hẹp trả 200 trên DB rỗng; mutation chưa xác minh |
| Hợp đồng | Không | Controller rỗng, model shell | Không | **Planned** |
| Vai trò/quyền/tài khoản | Chưa có UI quản trị | RBAC schema/routines và assignment nội bộ cho bootstrap đã có | Guarded MariaDB RBAC nằm trong wrapper | **Nền tảng verified hẹp; UI quản trị planned** |
| Auth/RBAC | Login/logout và topbar auth đã wired | Custom employee provider, session fail-closed, permission cache/Gates | Feature + MariaDB + browser boundary pass hẹp | **Verified hẹp cho module nhân viên**, chưa phải security audit production toàn hệ thống |
| Báo cáo | Nút/mục tiêu rời rạc | Chưa có workflow | Không | **Planned** |
| Backup/restore | Không có workflow an toàn | SP legacy sinh cú pháp SQL Server | Không | **Planned — unsafe legacy procedures** |

## Module Nhân viên (snapshot 2026-08-21)

Phạm vi đã tích hợp vào `main` từ branch historical `feature/quanly-nhan-vien`: list/filter/pagination, create, detail, update hồ sơ/địa chỉ/avatar, delete-or-terminate, reset password, custom authentication và năm Gate nhân viên. Route khóa mã `NV###`; request/service/repository không nhận role, mã, hash hoặc ngày nghỉ từ client. Target phải giữ exact role `NHAN_VIEN_MAC_DINH`; actor tự nhắm mình hoặc target non-baseline bị chặn trước mutation, và procedure lặp lại guard sau row lock để đóng race.

### Rollout và authorization

`config/nhanvien.php` dùng `env('NHAN_VIEN_MODULE_ENABLED', true)`. Cờ `false` vẫn trả 404 trước Gate/service; khi bật, mọi `/admin` yêu cầu `auth` và mỗi employee route/Blade action dùng đúng một trong năm quyền. Hai lookup nhân viên dùng chung ở chấm công/nghỉ phép cũng yêu cầu auth, rollout và quyền XEM. Cờ rollout chỉ là kill switch, không phải authorization.

Auth provider lookup trả đúng sáu cột server-only và không expose hash ra controller/UI. Login dùng generic error, throttle theo identifier/IP, session regenerate; session restore từ chối `DA_NGHI`. Reset/lifecycle chỉ tạo hoặc truyền hash trong Laravel/repository boundary; plaintext không được flash/log/trả về.

SQL contract đi kèm được version trong `database/sql/employee/001..006` và replay vào canonical dump. Hồ sơ + địa chỉ + avatar chạy trong một transaction write; file avatar mới được bù trừ khi rollback, file cũ chỉ xóa sau commit nếu path thuộc prefix an toàn. Lifecycle hard-delete khi không có dependency, ngược lại chuyển `DA_NGHI` và giữ ngày nghỉ đầu tiên.

Browser Task 20 đã kiểm tra login/logout, CRUD, filter/flash/edit mapping, stale 404, lifecycle/RBAC boundaries và responsive `320/375/768/1024/1440`; console sạch. Session restore của tài khoản chuyển `DA_NGHI` và double-submit được automated-test, không suy rộng thành simultaneous-browser proof. Avatar browser upload/replacement còn blocked do Chrome extension file permission, dù automated upload/ownership tests xanh. Chưa có quy trình rollout database thật, module chỉ được gọi **verified hẹp**, không gọi production-ready.

Acceptance harness chỉ dùng target MariaDB guarded/disposable, process/cache/upload prefix riêng và cleanup fail-closed. Official Stop cuối đã đưa schema/state/lock/run/upload/listener/PHP/`public/storage` về `0`, giữ nguyên `storage/app/public`.

## Route và controller đang lệch

Hai route phòng ban vẫn trỏ tới method không tồn tại:

- `PhongBanController@show`
- `PhongBanController@destroy`

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

Code còn gọi hai procedure ngoài module nhân viên không có trong canonical dump:

1. `sp_phong_ban_chi_tiet`
2. `sp_luong_tim_kiem_phan_trang`

`ChamCongController@index` dùng Query Builder trên cột canonical vì
`sp_cham_cong_chi_tiet_phan_trang` không tồn tại; không ghi procedure này vào
backlog missing caller.

Ngoài ra:

- `sp_phong_ban_sua(ma_pb, ten_pb)` cần hai placeholder; controller hiện chỉ có một.
- Model `ChamCong` dùng tên bảng/cột không khớp `cham_cong.ngay_lam`.
- Model `NhanVien` đã map table/key/timestamps và ẩn `mat_khau`; `PhongBan` vẫn cần audit mapping.
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
- Guarded full employee wrapper historical đã pass `165 tests, 3367 assertions, 1 platform skip`; rerun sau tích hợp timeout khoảng 184 giây và cleanup schema/state/marker/process sạch, nên current MariaDB gate là unverified.
- Browser matrix chức năng/RBAC/responsive đã có; avatar upload/replacement còn blocked và separate simultaneous context không được browser-verified đầy đủ.
- Không dùng local demo rollout hoặc response 200 trên danh sách rỗng để chứng minh production logic; cần rollout evidence/approval riêng cho môi trường thật.
- Chưa xác minh tương thích MySQL 8; runtime hiện tại chỉ là MariaDB 10.4.32.
- Full Laravel current đã xanh; landing view legacy `/admin` và các blocker module khác vẫn chưa được xử lý.

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
