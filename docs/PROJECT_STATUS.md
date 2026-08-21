# Trạng thái dự án

> Snapshot: 2026-08-21
>
> Branch: `feature/quanly-nhan-vien`
>
> Phạm vi: Tasks 13–20 đã được kiểm chứng hẹp và đã thành commit local: source/test/SQL `ba6e0189e64eb3046164ae5183950afe0b5722be`, dependency locks `18ea209d89efce38596dd1440151f6d55ca90156`. Upstream trước delivery vẫn ở `723dac63983d04364c6f146662aec7bd5eb6d87a`; commit tài liệu và push còn chờ tại thời điểm soạn snapshot. Luôn revalidate HEAD/tracking; không ghi database live.

## Bằng chứng hiện tại — Task 20 Phase E (2026-08-21)

Đây là mục authoritative mới nhất; các số liệu lịch sử bên dưới được giữ để
truy nguyên nhưng không được dùng thay cho gate mới.

- Full guarded MariaDB wrapper đã pass với `165 tests, 3367 assertions, 1 platform skip,
  exit 0`; mọi schema disposable được cleanup. Runtime chỉ dùng database test
  có tên guarded; `quan_ly_nhan_su` không bị mutation.
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
- Module nhân viên là **verified hẹp trên branch**, chưa phải production
  readiness: avatar browser còn blocked, full-suite `/` baseline còn fail, và
  các commit local chưa được push tại thời điểm soạn snapshot.

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
| Git | Branch `feature/quanly-nhan-vien`; source `ba6e018`, dependency locks `18ea209`; local ahead upstream `723dac6`, commit tài liệu/push còn chờ tại snapshot |
| MCP code graph | 1.819 node, 2.454 edge; dùng để khám phá code, không dùng thay cho route runtime |
| `php artisan route:list --except-vendor` | Pass; 49 route, gồm login/logout và employee lifecycle routes |
| `php artisan test` | `221 pass, 1 fail, 1772 assertions`; failure duy nhất là baseline `/` trả 404; Unit `95/633`, scoped Feature employee/auth/compatibility `107/1093` |
| `npm run test:frontend` | Pass; 15 tests |
| `npm run build` | Pass; Vite 7.3.6, 16 modules transformed |
| Composer dependency gates | Validate/install dry-run pass; `composer audit --locked` không còn advisory sau sáu compatible lock updates |
| MariaDB employee integration | **Pass hẹp**; full guarded wrapper `165 tests, 3367 assertions, 1 platform skip, exit 0`; cleanup schema/state/lock/run/upload/listener/PHP/link `0` |
| Task 19 harness regression/review | Process-identity/atomic-state regressions đều nằm trong full wrapper sạch; independent review **Spec PASS / Quality APPROVE**, không còn Critical/Important; skip duy nhất do Windows từ chối tạo disposable state symlink |
| Employee rollout flag | `env('NHAN_VIEN_MODULE_ENABLED', true)`; đặt `false` sẽ fail-closed 404 nhưng không thay thế auth/Gate |
| `php artisan migrate:status` | Fail: chưa có bảng `migrations` |

## Ma trận module

| Module | Web/UI | API/data | Test | Trạng thái và blocker |
| --- | --- | --- | --- | --- |
| Home/landing | Có named route `backend.frontend.home` tại `/admin`, nhưng target view `frontend.home` bị thiếu; không có route `/` | Không | Test `/` fail | **Blocked** |
| Dashboard | `/admin/bang-dieu-khien` render 200 | Chưa có dữ liệu | Không | **Prototype** |
| Phòng ban | Blade index/create lỗi | Controller gọi SP trực tiếp | Không | **Prototype — blocked**: route trỏ method thiếu, SP chi tiết thiếu, update sai placeholder |
| Nhân viên | List/create/detail/edit/lifecycle/reset/login UI; responsive browser pass hẹp | SQL `001`–`006`, repository/service, auth provider, session guard và 5 permission Gates | Unit `95/633`; scoped Feature `107/1093`; MariaDB `165/3367`; frontend `15`; browser function/RBAC/responsive matrix | **Verified hẹp trên branch; đã commit local, đang chờ push, chưa production-ready**: browser avatar upload còn blocked; full suite còn baseline `/` 404 |
| Chức vụ | Chưa có route/view | Có controller/service/repository/request/model | Không | **Prototype — unreachable** |
| Lương | Trang render, JS CRUD và hệ số được build | API resource + service/repository | Không | **Prototype — blocked**: thiếu procedure danh sách; write contract chưa ngăn trùng `(ma_nv, ky_luong)`; export/đối soát chưa có handler đầy đủ |
| Hệ số lương | UI tích hợp trong trang lương | API đọc/thêm/sửa dùng Query Builder; JavaScript có delete nhưng API chưa có route DELETE | Không | **Prototype — blocked action**: validation lệch schema, mutation chưa xác minh |
| Chấm công | Trang render, JS tải/cập nhật được nối | 4 API route | Không | **Prototype — blocked**: thiếu 2 SP phân trang; validation exception có thể bị trả 500 thay vì 422; import/export chưa có consumer an toàn |
| Nghỉ phép | Trang render, JS CRUD/duyệt được nối | 12 API route, service/repository và một số query trực tiếp | Không | **Prototype**: lookup/danh sách hẹp trả 200 trên DB rỗng; mutation chưa xác minh |
| Hợp đồng | Không | Controller rỗng, model shell | Không | **Planned** |
| Vai trò/quyền/tài khoản | Chưa có UI quản trị | RBAC schema/routines và assignment nội bộ cho bootstrap đã có | Guarded MariaDB RBAC nằm trong wrapper | **Nền tảng verified hẹp; UI quản trị planned** |
| Auth/RBAC | Login/logout và topbar auth đã wired | Custom employee provider, session fail-closed, permission cache/Gates | Feature + MariaDB + browser boundary pass hẹp | **Verified hẹp cho module nhân viên**, chưa phải security audit production toàn hệ thống |
| Báo cáo | Nút/mục tiêu rời rạc | Chưa có workflow | Không | **Planned** |
| Backup/restore | Không có workflow an toàn | SP legacy sinh cú pháp SQL Server | Không | **Planned — unsafe legacy procedures** |

## Module Nhân viên (snapshot 2026-08-21)

Phạm vi đã triển khai trên branch `feature/quanly-nhan-vien`: list/filter/pagination, create, detail, update hồ sơ/địa chỉ/avatar, delete-or-terminate, reset password, custom authentication và năm Gate nhân viên. Route khóa mã `NV###`; request/service/repository không nhận role, mã, hash hoặc ngày nghỉ từ client. Target phải giữ exact role `NHAN_VIEN_MAC_DINH`; actor tự nhắm mình hoặc target non-baseline bị chặn trước mutation, và procedure lặp lại guard sau row lock để đóng race.

### Rollout và authorization

`config/nhanvien.php` dùng `env('NHAN_VIEN_MODULE_ENABLED', true)`. Cờ `false` vẫn trả 404 trước Gate/service; khi bật, mọi `/admin` yêu cầu `auth` và mỗi employee route/Blade action dùng đúng một trong năm quyền. Hai lookup nhân viên dùng chung ở chấm công/nghỉ phép cũng yêu cầu auth, rollout và quyền XEM. Cờ rollout chỉ là kill switch, không phải authorization.

Auth provider lookup trả đúng sáu cột server-only và không expose hash ra controller/UI. Login dùng generic error, throttle theo identifier/IP, session regenerate; session restore từ chối `DA_NGHI`. Reset/lifecycle chỉ tạo hoặc truyền hash trong Laravel/repository boundary; plaintext không được flash/log/trả về.

SQL contract đi kèm được version trong `database/sql/employee/001..006` và replay vào canonical dump. Hồ sơ + địa chỉ + avatar chạy trong một transaction write; file avatar mới được bù trừ khi rollback, file cũ chỉ xóa sau commit nếu path thuộc prefix an toàn. Lifecycle hard-delete khi không có dependency, ngược lại chuyển `DA_NGHI` và giữ ngày nghỉ đầu tiên.

Browser Task 20 đã kiểm tra login/logout, CRUD, filter/flash/edit mapping, stale 404, lifecycle/RBAC boundaries và responsive `320/375/768/1024/1440`; console sạch. Session restore của tài khoản chuyển `DA_NGHI` và double-submit được automated-test, không suy rộng thành simultaneous-browser proof. Avatar browser upload/replacement còn blocked do Chrome extension file permission, dù automated upload/ownership tests xanh. Vì khoảng trống này, baseline `/` 404 và chưa có quy trình rollout database thật, module chỉ được gọi **verified hẹp**, không gọi production-ready.

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

Không có route `/`. Route home hiện được khai báo bên trong prefix `admin`, tạo `/admin`, nhưng view `frontend.home` không tồn tại.

## Data contract đang lệch

Code còn gọi ba procedure ngoài module nhân viên không có trong canonical dump:

1. `sp_phong_ban_chi_tiet`
2. `sp_cham_cong_chi_tiet_phan_trang`
3. `sp_luong_tim_kiem_phan_trang`

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

- Canonical dump replay đã chạy trên disposable schema qua `CanonicalDumpReplayTest`; không replay vào `quan_ly_nhan_su`.
- Không chạy mutation CRUD trên database live; employee mutation chỉ chạy trong disposable schema do guard tạo và đã cleanup.
- Guarded full employee wrapper đã pass hẹp: `165 tests, 3367 assertions, 1 platform skip`, cleanup schema/state/lock/run/upload/listener/PHP/link `0`.
- Browser matrix chức năng/RBAC/responsive đã có; avatar upload/replacement còn blocked và separate simultaneous context không được browser-verified đầy đủ.
- Không re-read hoặc mutate các bảng nghiệp vụ live trong phiên; response 200 trên danh sách rỗng (nếu có) không chứng minh logic với dữ liệu thật.
- Chưa xác minh tương thích MySQL 8; runtime hiện tại chỉ là MariaDB 10.4.32.
- Full Laravel chưa xanh vì baseline route `/` 404 ngoài scope module nhân viên.

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
