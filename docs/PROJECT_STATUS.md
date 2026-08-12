# Trạng thái dự án

> Snapshot: 2026-08-11
>
> Branch/HEAD: `main` / `643563c029e10a49636f1a6f2e70b4e427f1dc7e`
>
> Phạm vi: audit read-only code, route, render hẹp, build, test, SQL dump và MariaDB local.

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
| Git | `main` đồng bộ `origin/main`; sạch trước thay đổi tài liệu |
| MCP code graph | 1.819 node, 2.454 edge; dùng để khám phá code, không dùng thay cho route runtime |
| `php artisan route:list --except-vendor` | Pass; 44 route = 17 web + 27 API |
| `php artisan test` | Fail; 1 pass, 1 fail vì `/` trả 404; `phpunit.xml` dùng SQLite in-memory nên không kiểm tra procedure MariaDB |
| `npm run build` | Pass; Vite 7.3.6, 8 module transformed |
| `composer validate --no-check-publish` | Pass |
| MariaDB MCP ping | Pass; MariaDB 10.4.32, schema `quan_ly_nhan_su` |
| `php artisan migrate:status` | Fail: chưa có bảng `migrations` |

## Ma trận module

| Module | Web/UI | API/data | Test | Trạng thái và blocker |
| --- | --- | --- | --- | --- |
| Home/landing | Có named route `backend.frontend.home` tại `/admin`, nhưng target view `frontend.home` bị thiếu; không có route `/` | Không | Test `/` fail | **Blocked** |
| Dashboard | `/admin/bang-dieu-khien` render 200 | Chưa có dữ liệu | Không | **Prototype** |
| Phòng ban | Blade index/create lỗi | Controller gọi SP trực tiếp | Không | **Prototype — blocked**: route trỏ method thiếu, SP chi tiết thiếu, update sai placeholder |
| Nhân viên | Hai trang render; dữ liệu danh sách hard-code; form chỉ alert/reset | `store()` chưa lưu; route edit/show/destroy trỏ method thiếu | Không | **Prototype — blocked** |
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

## Route và controller đang lệch

Năm route web trỏ tới method không tồn tại:

- `PhongBanController@show`
- `PhongBanController@destroy`
- `NhanVienController@edit`
- `NhanVienController@show`
- `NhanVienController@destroy`

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

- Không clean-replay SQL dump trong phiên audit này.
- Không chạy mutation CRUD có thể thay đổi dữ liệu.
- Không có integration test với MariaDB disposable.
- Không có browser matrix cho desktop/tablet/mobile, focus, console hoặc network.
- Database đang rỗng ở các bảng nghiệp vụ chính; response 200 trên danh sách rỗng không chứng minh logic với dữ liệu thật.
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
