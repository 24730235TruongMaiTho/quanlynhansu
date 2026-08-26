# Kiến trúc hiện tại

Tài liệu này mô tả code dùng chung và module Nhân viên tại snapshot 2026-08-26.
Đây là kiến trúc **đang tồn tại**, không phải cam kết production cho mọi module.

> **Runtime hiện hành:** Ba module Nhân viên, Phòng ban và Chức vụ là CRUD công
> khai. Root `/` chuyển tới danh sách Nhân viên; không còn route/UI đăng nhập,
> đăng xuất hoặc reset mật khẩu, cũng không còn auth/RBAC/Gate, department scope,
> target-role guard hay rollout flag do chúng ta xây dựng. Các đoạn có nhãn
> historical chỉ giữ để truy nguyên thay đổi trước đó.

> **Evidence current 2026-08-26:** Full Laravel `208 tests, 2573 assertions`,
> frontend `17/17`, Vite `18 modules`, route inventory `52`, MariaDB disposable
> `11 tests, 341 assertions`; browser chưa kiểm chứng.

## Sơ đồ request

```mermaid
flowchart LR
    B[Browser] --> W[routes/web.php]
    W --> V[Blade views]
    V --> J[JavaScript theo module]
    J --> A[routes/api.php /api/v1]
    A --> C[Backend controllers]
    C --> S[Services]
    S --> R[Repositories]
    C --> D[DB facade / Query Builder]
    R --> D
    D --> M[(MariaDB)]
```

Các đường truy cập database đang cùng tồn tại theo phạm vi module:

- Employee CRUD: `Controller → Service → Repository → Query Builder/table`.
- Các module legacy khác: `Controller → Service/Repository → procedure/query`.

Không nên coi sơ đồ lớp là chuẩn hoàn chỉnh: một số module bỏ qua service/repository, và một số model chưa map đúng schema.

## Điểm vào ứng dụng

### Web

`routes/web.php` đăng ký các web route hiện hành, gồm các route CRUD và route dưới `/admin`:

- Dashboard.
- Phòng ban.
- Nhân viên.
- Trang lương, chấm công, nghỉ phép.

Các route CRUD ba module không yêu cầu đăng nhập hoặc permission middleware. Một
số route module khác vẫn là prototype và có thể còn lệch contract riêng.

Named-route contract chưa đồng nhất: web có `backend.backend.*`, resource API không có `api.v1` prefix và một số route nghỉ phép chưa được đặt tên.

### API

`bootstrap/app.php` đăng ký `routes/api.php`. Có 27 route dưới `/api/v1`:

| Nhóm | Số route | Vai trò |
| --- | ---: | --- |
| `cham-cong` | 4 | Danh sách nhân viên/phòng ban, chi tiết và cập nhật |
| `luong` | 11 | CRUD lương, lookup và hệ số lương |
| `nghi-phep` | 12 | CRUD nghỉ phép, nhân viên/lookup và duyệt |

Số route được lấy từ Laravel runtime. MCP code graph chỉ trích xuất được một phần route và được dùng để khám phá symbol/call graph, không phải nguồn đếm cuối.

## Các lớp code

```text
app/
├── Http/
│   ├── Controllers/Backend/
│   ├── Controllers/Frontend/
│   └── Requests/
├── Models/
├── Repositories/
└── Services/
```

### Controller

- `LuongController`, `NghiPhepController`, `ChucVuController` dùng service/repository cho CRUD chính.
- `ChamCongController` gọi Query Builder/stored procedure trực tiếp và chứa logic pagination/response.
- `LuongHeSoLuongController`, `LuongPhongBanController`, `LuongChucVuController` là endpoint phụ trợ.
- `PhongBanController` gọi procedure trực tiếp nhưng hợp đồng hiện bị lệch.
- `NhanVienController` có list/create/store/show/edit/update/lifecycle; dữ liệu
  employee đi qua repository Query Builder trực tiếp.

### Request validation

Có Form Request cho lương, chấm công, nghỉ phép và chức vụ. Tuy nhiên không phải request nào cũng được controller dùng, và một số rule không khớp kiểu/độ dài/nullability trong SQL.

### Service và repository

Có bốn cặp service/repository:

- Chấm công.
- Chức vụ.
- Lương.
- Nghỉ phép.

Chúng cung cấp các action CRUD dạng `getAll/getById/create/update/delete`. Trước khi tái sử dụng, phải đối chiếu procedure thực tế và cách xử lý exception; tên lớp tồn tại không chứng minh contract đúng.

### Model

Có 14 model tương ứng phần lớn bảng nghiệp vụ, nhưng mức hoàn thiện không đồng đều. Một số model chỉ là class shell; một số quan hệ/cast đã có ở lương, nghỉ phép và chấm công.

## Blade và asset

Main hiện có ba chiến lược asset chồng nhau:

1. Layout admin `backend.layouts.app` dùng Bootstrap/Font Awesome/Bootstrap Icons/Select2 qua CDN và `public/backend/*`.
2. Trang lương, chấm công, nghỉ phép push thêm entry Vite riêng.
3. `resources/css/app.css`, `resources/js/app.js` và Primer/Tailwind là phần landing/shared cũ nhưng không nằm trong input Vite hiện tại.

Layout admin main còn thiếu `<head>` semantic hợp lệ; meta/link hiện nằm trực tiếp dưới `<html>`.

`vite.config.js` hiện build sáu entry JavaScript:

- `resources/js/frontend/luong/luong.js`
- `resources/js/frontend/luong/luongCreateUpdate.js`
- `resources/js/frontend/luong/luongHeSo.js`
- `resources/js/frontend/luong/luongHeSoCreateUpdate.js`
- `resources/js/frontend/chamcong/chamcong.js`
- `resources/js/frontend/nghiphep/nghiphep.js`

Frontend layout cũ lại yêu cầu `resources/css/app.css` và `resources/js/app.js`, nhưng hai entry này không có trong manifest hiện tại; `app.js` còn import `./bootstrap` trong khi file đó không tồn tại.

## Database

Nguồn fresh active cho dữ liệu module employee là `database/tao_bang.sql` rồi
`database/du_lieu_mau.sql`:

- đúng 15 bảng;
- không yêu cầu view/function/trigger/stored procedure;
- `nhan_vien` chứa trực tiếp address/avatar/date columns;
- role/status/permission dùng ID contracts và counter row lock.

`quan_ly_nhan_su.session.sql` cùng các script employee 001–006 là legacy
history; xem [DATABASE.md](DATABASE.md) và không dùng làm fresh setup path.

Ba migrations Laravel chỉ tạo hạ tầng users/session/cache/jobs và chưa được chạy trên database live. Xem [DATABASE.md](DATABASE.md).

Baseline hiện dùng `APP_TIMEZONE=Asia/Ho_Chi_Minh` và `DB_TIMEZONE=+07:00`. Môi trường triển khai phải giữ hai giá trị đồng bộ vì SQL vẫn dùng `CURDATE()` và PHP dùng `now()`.

## Auth và ranh giới bảo mật (historical)

Auth/RBAC từng được tích hợp hẹp cho module nhân viên trong các Task 13–20 lịch sử:

- route `dang-nhap`/`dang-xuat`, custom `nhan-vien` provider và `App\Models\NhanVien` làm identity;
- hash mới/rehash dùng Laravel hasher, lookup/hash CAS chỉ ở server boundary, session từ chối `DA_NGHI`;
- toàn bộ `/admin` có `auth`; năm Gate ability employee được đối chiếu với
  `ma_quyen` 101–105 và cache trong một request;
- employee Blade action và route dùng cùng permission; `NV_EDIT` cho phép sửa
  hồ sơ/địa chỉ/avatar của mọi target, còn xóa/chuyển nghỉ việc và reset mật khẩu
  vẫn guard target `ma_vt = 5`;
- row scope server-side giới hạn `ma_vt = 4` trong `ma_pb` của actor cho index,
  show và các entrypoint edit/update/delete/reset; mismatch trả 404 generic,
  payload update đổi `ma_pb` trả validation error, malformed identity fail closed.
  Các mutation chưa khóa expected `ma_pb` xuyên suốt transaction; concurrent đổi
  phòng ban giữa pre-check và mutation vẫn là residual cần xử lý khi rollout.
- hai lookup nhân viên dùng chung ở chấm công/nghỉ phép yêu cầu web session, rollout và quyền XEM.

Các lớp historical nêu trên đã được tháo khỏi runtime của nhánh hiện hành; route
CRUD không gọi auth provider, Gate hoặc department scope. Đây là verified hẹp
trên automated/disposable, chưa phải security audit production cho toàn ứng dụng.
Nếu cần auth/RBAC sau này, phải có quyết định kiến trúc riêng và ưu tiên code
của module đồng nghiệp khi hợp nhất.

## Kiến trúc mục tiêu chưa tích hợp

Branch local `frontend` chứa shell contract đã được duyệt và pass automated render/controller/build tests riêng, nhưng browser acceptance chưa hoàn tất và branch không phải ancestor của `main`. Mục tiêu được lưu tại [ADR-001](decisions/ADR-001-admin-shell.md).

Khi được phép tích hợp, cần xử lý như một thay đổi kiến trúc có chủ đích:

1. Chọn branch tích hợp từ `main`.
2. Port shell và test cần thiết, không merge mù toàn branch.
3. Giữ API/business work mới trên `main`.
4. Chuẩn hóa layout path, route name và Vite inputs.
5. Chạy lại Laravel, frontend và browser acceptance.

## Quyết định còn mở

- MariaDB 10.4 hay MySQL 8 là DBMS chuẩn của nhóm.
- Dùng `nhan_vien` hay `users` làm identity chính.
- Chuẩn data access: stored procedure qua repository hay Query Builder/Eloquent.
- Cách version schema/routine: một dump lớn hay migration/SQL scripts tách nhỏ.
- Cách tích hợp shell `frontend` vào `main`.

Khi nhóm chốt một quyết định khó đảo ngược, tạo ADR mới trong `docs/decisions/`.
