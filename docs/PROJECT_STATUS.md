# Trạng thái dự án

> Snapshot: 2026-08-20
>
> Branch: `feature/quanly-nhan-vien`
>
> Phạm vi: Task 12 scoped implementation đã delivered và pushed; commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f` được xác minh là ancestor trên origin. Không ghi database live; revalidate current HEAD trước khi dùng snapshot.

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
| Git | Implementation commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f` đã push/được xác minh trên origin; current HEAD và worktree phải được revalidate trước thao tác tiếp theo |
| MCP code graph | 1.819 node, 2.454 edge; dùng để khám phá code, không dùng thay cho route runtime |
| `php artisan route:list --except-vendor` | Pass; 44 route = 17 web + 27 API |
| `php artisan test` | Fresh baseline: 158 pass, 1 fail vì `/` trả 404; employee Feature/Unit `84 pass, 907 assertions`; `phpunit.xml` dùng SQLite in-memory nên MariaDB được kiểm tra riêng |
| `npm run test:frontend` | Pass; 5 tests |
| `npm run build` | Pass; Vite 7.3.6, 13 modules transformed |
| `composer validate --no-check-publish` | Pass |
| MariaDB employee integration | **Pass hẹp**; guarded disposable trio (`EmployeeUpdateProcedureTest`, `CanonicalDumpReplayTest`, `NhanVienRepositoryReadTest`) `20 tests, 436 assertions`, exit `0`; cleanup count `0` |
| Employee rollout flag | **Hard-disabled**; `config/nhanvien.php` dùng literal `'enabled' => false` |
| `php artisan migrate:status` | Fail: chưa có bảng `migrations` |

## Ma trận module

| Module | Web/UI | API/data | Test | Trạng thái và blocker |
| --- | --- | --- | --- | --- |
| Home/landing | Có named route `backend.frontend.home` tại `/admin`, nhưng target view `frontend.home` bị thiếu; không có route `/` | Không | Test `/` fail | **Blocked** |
| Dashboard | `/admin/bang-dieu-khien` render 200 | Chưa có dữ liệu | Không | **Prototype** |
| Phòng ban | Blade index/create lỗi | Controller gọi SP trực tiếp | Không | **Prototype — blocked**: route trỏ method thiếu, SP chi tiết thiếu, update sai placeholder |
| Nhân viên | Task 12 scoped delivery complete; **hard-disabled** | Update profile + địa chỉ + avatar trong transaction; role/identity/hash/ngày nghỉ được bảo vệ; routine versioned | Feature/Unit `84/907`; MariaDB `20/436`, cleanup `0`; reviewer Approve | **Delivered hẹp nhưng module chưa production-ready/không được bật**: auth/RBAC/Gates và browser còn thiếu; Task13 chưa bắt đầu |
| Chức vụ | Chưa có route/view | Có controller/service/repository/request/model | Không | **Prototype — unreachable** |
| Lương | Trang render, JS CRUD và hệ số được build | API resource + service/repository | Không | **Prototype — blocked**: thiếu procedure danh sách; write contract chưa ngăn trùng `(ma_nv, ky_luong)`; export/đối soát chưa có handler đầy đủ |
| Hệ số lương | UI tích hợp trong trang lương | API đọc/thêm/sửa dùng Query Builder; JavaScript có delete nhưng API chưa có route DELETE | Không | **Prototype — blocked action**: validation lệch schema, mutation chưa xác minh |
| Chấm công | Trang render, JS tải/cập nhật được nối | 4 API route | Không | **Prototype — blocked**: thiếu 2 SP phân trang; validation exception có thể bị trả 500 thay vì 422; import/export chưa có consumer an toàn |
| Nghỉ phép | Trang render, JS CRUD/duyệt được nối | 12 API route, service/repository và một số query trực tiếp | Không | **Prototype**: lookup/danh sách hẹp trả 200 trên DB rỗng; mutation chưa xác minh |
| Hợp đồng | Không | Controller rỗng, model shell | Không | **Planned** |
| Vai trò/quyền/tài khoản | Không | Controller rỗng, model shell | Không | **Planned** |
| Auth/RBAC | Không có login/logout | Không có auth middleware/permission guard | Không | **Planned — critical** |
| Báo cáo | Nút/mục tiêu rời rạc | Chưa có workflow | Không | **Planned** |
| Backup/restore | Không có workflow an toàn | SP legacy sinh cú pháp SQL Server | Không | **Planned — unsafe legacy procedures** |

## Lát cắt cập nhật nhân viên (snapshot 2026-08-20)

Phạm vi đã triển khai trên branch `feature/quanly-nhan-vien`: route `edit/update` có rollout guard và ràng buộc mã `NV###`; `UpdateNhanVienRequest` xác thực target trước validation, khóa trường hệ thống và giữ invariant trạng thái; service/repository cập nhật hồ sơ, địa chỉ và avatar trong một transaction trên write connection. Target role phải đúng `NHAN_VIEN_MAC_DINH`; mã, vai trò, hash mật khẩu và ngày nghỉ việc không nhận từ client.

### Rollout safety gate (load-bearing)

`config/nhanvien.php` hiện dùng literal `'enabled' => false`; đây là chốt fail-closed, không phải cờ triển khai đã được cấp quyền bật. `NhanVienUpdateTest` chứng minh edit/update trả 404 trước khi gọi service khi flag tắt. Không set flag thành `true`, không thêm env override và không deploy active trước Task 18 hoàn tất auth/RBAC/Gates với actor authorization. Nếu bật sớm, CSRF chỉ chống request giả mạo chứ không xác thực actor: anonymous có thể lấy form/CSRF, enumerate target/role và gửi mutation/đọc PII của nhân viên.

Vì vậy lát cắt này không được mô tả là public-safe hoặc operationally complete. Task 12 scoped delivery đã complete tại commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f`, MariaDB disposable đã pass và scoped code/test review đã **Approve**. Task13 lifecycle/auth DB contracts là bước kế tiếp nhưng **chưa bắt đầu**; Task18 auth/RBAC/Gates là prerequisite trước enablement. Browser acceptance giữ ở Task20.

SQL contract đi kèm gồm `sp_nhan_vien_sua` (14 `IN`), `sp_dia_chi_nhan_vien_luu` và `sp_nhan_vien_cap_nhat_anh` (2 `IN` + 1 `OUT`), được version trong `database/sql/employee/2026_08_12_004_update_routines.sql` và replay vào canonical dump. Avatar mới chỉ được giữ sau commit; avatar cũ chỉ xóa sau commit khi chứng minh thuộc prefix an toàn; rollback/failure có bù trừ best-effort.

Verified hẹp: feature/unit employee tests 84 pass (907 assertions), guarded MariaDB trio 20 pass (436 assertions), frontend state tests 5 pass, hard-disable update test pass, route list 44 route, PHP lint **23 PHP files in slice** pass, Vite build pass và `git diff --check` pass. Chưa verified: browser acceptance, auth/RBAC thật và mutation trên database live. Vì vậy không gọi module Nhân viên là production-ready hoặc bật cờ rollout.

Scoped re-review: SQL notes và service orchestration notes đã được reviewer xác nhận **ADDRESSED**, không còn Critical/Important mới; overall review **Approve**. Đây là approval và delivery của code/test scope, không phải production enablement; module vẫn literal false.

## Route và controller đang lệch

Hai route phòng ban vẫn trỏ tới method không tồn tại:

- `PhongBanController@show`
- `PhongBanController@destroy`

Lát cắt nhân viên hiện đã có `edit`, `show` và `update`; chưa có route delete trong phạm vi task.

Các route lương/chấm công/nghỉ phép nằm trong group đã có prefix tên `backend.` nhưng lại tự thêm `backend.`, tạo các tên:

- `backend.backend.luong.index`
- `backend.backend.chamcong.index`
- `backend.backend.nghiphep.index`

API naming cũng chưa theo một contract:

- Resource routes dùng tên như `luong.index`, `cham-cong.index`, `nghi-phep.index` thay vì `api.v1.*`.
- Một số route nghỉ phép chưa có name.

Không có route `/`. Route home hiện được khai báo bên trong prefix `admin`, tạo `/admin`, nhưng view `frontend.home` không tồn tại.

## Data contract đang lệch

Code gọi bốn procedure không có trong dump hoặc MariaDB live:

1. `sp_phong_ban_chi_tiet`
2. `sp_cham_cong_nhan_vien_phan_trang`
3. `sp_cham_cong_chi_tiet_phan_trang`
4. `sp_luong_tim_kiem_phan_trang`

Ngoài ra:

- `sp_phong_ban_sua(ma_pb, ten_pb)` cần hai placeholder; controller hiện chỉ có một.
- Model `ChamCong` dùng tên bảng/cột không khớp `cham_cong.ngay_lam`.
- Model `NhanVien` và `PhongBan` chưa map đầy đủ bảng, khóa chính và timestamps.
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
- Integration test MariaDB employee đã pass hẹp: `20 tests, 436 assertions`.
- Không có browser matrix cho desktop/tablet/mobile, focus, console hoặc network.
- Không re-read hoặc mutate các bảng nghiệp vụ live trong phiên; response 200 trên danh sách rỗng (nếu có) không chứng minh logic với dữ liệu thật.
- Chưa xác minh tương thích MySQL 8; runtime hiện tại chỉ là MariaDB 10.4.32.

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
