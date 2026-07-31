# Handoff tiếp tục đồ án `quanlynhansu`

> Snapshot: 2026-07-14
> Nhánh: `kien`
> HEAD khi audit: `b2b2a7e` (`Add demo home`)
> Phạm vi snapshot: đọc mã nguồn, route, giao diện, cấu hình và SQL dump; không import database, không sửa code nghiệp vụ.

## Mục đích và nguồn sự thật

Đây là đồ án môn học **Website quản lý nhân sự**, dùng Laravel 12, PHP 8.2+, MySQL, Blade, Vite và CSS tùy chỉnh (có Bootstrap CDN ở layout backend, Tailwind CSS 4 trong cấu hình Vite).

Trước khi làm bất kỳ tính năng nào, đọc theo thứ tự:

1. `AGENTS.md` — quy ước và các lỗi đã biết.
2. File này — trạng thái audit và thứ tự làm tiếp.
3. Route, controller, model, view của module đang sửa.
4. `quan_ly_nhan_su.session.sql` nếu module dùng dữ liệu nghiệp vụ hoặc stored procedure.

Khi README mâu thuẫn với code, ưu tiên code hiện tại. README mới là danh sách mục tiêu nghiệp vụ, chưa phản ánh đầy đủ cách chạy và hiện trạng thực tế.

## Trạng thái đã xác minh

| Hạng mục | Trạng thái thực tế |
| --- | --- |
| Laravel boot và route | `php artisan route:list --except-vendor` chạy được; có 8 route ứng dụng. |
| Test | `php artisan test` pass 2/2 test mẫu. Chưa có test nghiệp vụ. |
| Frontend build | `npm run build` pass với Vite 7.3.6. `public/build` là output bị Git ignore. |
| Trang chủ `/` | Có landing page tĩnh, do `Frontend\\HomeController@index` trả `frontend.home`. |
| Dashboard `/dashboard` | Có controller/view tối thiểu, hiện chỉ hiển thị tiêu đề. |
| Phòng ban | Là module duy nhất có ý định CRUD, nhưng chưa chạy hoàn chỉnh do route/controller/view/SQL không khớp. |
| Đăng nhập | **Chưa có** route, controller, view, middleware hay model xác thực dùng được. `resources/js/app.js` chỉ nạp `./bootstrap` (Axios). |
| Database nghiệp vụ | Nằm trong `quan_ly_nhan_su.session.sql`; migrations Laravel hiện chỉ là migrations khung. |

Các lệnh test/build trên chỉ chứng minh khung Laravel và Vite hoạt động. Chúng không chứng minh CRUD phòng ban hay các module nghiệp vụ chạy với MySQL.

## Bản đồ mã nguồn ngắn

```text
routes/web.php                              Route hiện có: home, dashboard, phòng ban
app/Http/Controllers/Backend/               Controller quản trị
app/Http/Controllers/Frontend/HomeController.php
                                            Landing page hiện có
app/Models/PhongBan.php                     Model duy nhất, chưa map đúng schema SQL
resources/views/frontend/                   Landing page và layout frontend
resources/views/backend/                    Dashboard/layout/phòng ban còn dang dở
resources/css/app.css                       CSS dùng chung, có hiệu ứng nút tái sử dụng
resources/js/app.js                         Chỉ nạp Axios bootstrap, không có login
quan_ly_nhan_su.session.sql                 Schema, routine và nghiệp vụ chính
database/migrations/                        Chỉ users/cache/jobs Laravel mặc định
notes/frontend-btn-effect-notes.md          Ghi chú hiệu ứng nút frontend
```

## Hiện trạng giao diện

- Trang chủ có menu responsive, sticky navigation và scroll reveal. Các liên kết hiện chỉ là anchor nội trang, chưa đi tới dashboard, phòng ban hay đăng nhập.
- Vite có bốn entrypoint: `resources/css/app.css`, `resources/js/app.js`, `resources/css/frontend/home.css`, `resources/js/frontend/home.js`.
- `resources/views/backend/layout/app.blade.php` thiếu cấu trúc HTML chuẩn (`<head>`), không nạp Vite và sidebar/main content mới là comment.
- `resources/views/backend/dashboard/index.blade.php` chỉ là placeholder.
- `resources/css/frontend/home.css` dùng selector `.benefit-grid div`, trong khi markup dùng `<article>`, nên style card lợi ích không áp dụng.

### Lỗi chặn ở Blade phòng ban

| File | Hiện trạng | Hướng xử lý tiếp theo |
| --- | --- | --- |
| `resources/views/backend/phongban/index.blade.php` | `@foreach($pb in $phongban)` sai cú pháp Blade; directive có dấu `;`; HTML table chưa hợp lệ. | Viết lại view danh sách với `@foreach ($phongban as $pb)`, bảng semantic và flash/error message. |
| `resources/views/backend/phongban/create.blade.php` | Chỉ có `@form`; không có form, CSRF, input hay action. | Tạo form `POST backend.phongban.store`, có `@csrf`, validation feedback. |
| `resources/views/backend/layout/app.blade.php` | Thiếu `<head>`, chưa có navigation quản trị thực tế. | Hoàn thiện layout trước khi mở rộng các module backend. |

## Laravel và route: điểm cần biết

### Route đang khai báo

`routes/web.php` hiện có:

- `GET /` → `Frontend\\HomeController@index` (`frontend.home`)
- `GET /dashboard` → `Backend\\DashboardController@index`
- CRUD sơ khai cho `/phong-ban`

### Lỗi chặn tại module `phong_ban`

1. `GET /phong-ban/create` trỏ `PhongBanController@create`, nhưng controller chưa có method `create`.
2. `PUT /phong-ban/{id}` trỏ nhầm `show`, trong khi controller có `update` và không có `show`.
3. `DELETE /phong-ban/{id}` gọi `destroy`, nhưng controller chỉ có `detroy` (sai chính tả).
4. `PhongBanController@index()` gán `$danh_sach_phong_ban` nhưng lại `compact('phongban')`; biến `phongban` chưa tồn tại.
5. `update()` gọi `sp_phong_ban_sua(?)` nhưng truyền hai binding; procedure SQL cần hai placeholder: `sp_phong_ban_sua(?, ?)`.
6. `edit()` gọi `sp_phong_ban_chi_tiet(?)`, nhưng procedure này không có trong SQL dump. Cần quyết định: bổ sung procedure hay lấy bản ghi qua query builder/Eloquent.
7. Validation hiện chỉ kiểm tra `required`; cần ít nhất ràng buộc kiểu/độ dài theo schema trước khi gọi procedure.

### Các file chưa sẵn sàng

- `app/Http/Controllers/Backend/BangDieuKhienController.php`, `ChamCongController.php`, `ChucVuController.php`, `LuongController.php`, `NghiPhepController.php` là file rỗng.
- `app/Http/Controllers/Frontend/ChiTietNhanVienController.php` và `LuongController.php` là file rỗng.
- `app/Http/Controllers/Backend/NhanVienController.php` và `app/Services/NhanVienService.php` thiếu `<?php` và dùng namespace sai; hiện không thể autoload đúng chuẩn Laravel.
- `app/Models/PhongBan.php` cần khai báo tối thiểu:

  ```php
  protected $table = 'phong_ban';
  protected $primaryKey = 'ma_pb';
  public $timestamps = false;
  ```

- `App\\Models\\User` không tồn tại, nhưng `config/auth.php`, factory và seeder mặc định vẫn tham chiếu nó. Vì vậy không được chạy `php artisan db:seed` với kỳ vọng thành công trước khi chọn/hoàn thiện hướng auth.

## Database: nguồn dữ liệu và rủi ro

`quan_ly_nhan_su.session.sql` tạo:

- 14 bảng nghiệp vụ;
- 1 view: `vw_danh_sach_nhan_vien_chi_tiet`;
- 8 function;
- 10 trigger;
- 63 stored procedure.

Quan hệ chính:

```text
phong_ban / chuc_vu / trang_thai_lam_viec / vai_tro
                         └── nhan_vien
                              ├── hop_dong ── loai_hop_dong
                              ├── nghi_phep ── loai_phep
                              ├── cham_cong
                              ├── lich_su_he_so_luong
                              └── luong

vai_tro ── vai_tro_quyen ── quyen
```

### Quy tắc an toàn khi làm database

- Dump bắt đầu bằng `DROP DATABASE IF EXISTS quan_ly_nhan_su`; **không import trực tiếp vào database có dữ liệu**.
- Dùng MySQL (không dùng SQLite) cho module có stored procedure. `.env.example` vẫn mặc định SQLite nên phải cấu hình `.env` cẩn thận.
- Trước khi gọi procedure, kiểm tra đúng chữ ký trong SQL dump. Ví dụ phòng ban hiện có `sp_phong_ban_them(ten_pb)`, `sp_phong_ban_sua(ma_pb, ten_pb)`, `sp_phong_ban_xoa(ma_pb)`, `sp_phong_ban_danh_sach()`; không có `sp_phong_ban_chi_tiet`.
- Các migrations Laravel không tạo 14 bảng/routine nghiệp vụ. Nếu dùng dump làm nguồn sự thật, import dump vào DB trống trước, sau đó mới chạy migrations Laravel để bổ sung bảng hạ tầng nếu cần.
- Không tự động đưa backup/restore SQL vào web: hai procedure hiện sinh `BACKUP DATABASE` / `RESTORE DATABASE`, không phải cú pháp MySQL thực thi được.

### Rủi ro nghiệp vụ cần ghi nhớ

1. View nhân viên trả cả `nv.mat_khau`; không dùng `SELECT *` từ view để render API/UI.
2. `sp_nhan_vien_dang_nhap` xác thực theo `nhan_vien` và SHA-256, còn Laravel mặc định dùng `users`/bcrypt. Đây là hai hướng chưa được tích hợp.
3. `quyen.ma_quyen` không `AUTO_INCREMENT`, nhưng `sp_quyen_them` không chèn mã quyền — thêm quyền có thể lỗi.
4. `sp_cham_cong_import` hiện chủ động `SIGNAL` lỗi; export dùng `INTO OUTFILE`, phụ thuộc quyền `FILE` và `secure_file_priv` của server MySQL.
5. Lương chưa chặn unique `(ma_nv, ky_luong)` và `ky_luong` chưa được chuẩn hóa ngày đầu tháng; nên xử lý trước khi mở module lương/báo cáo.
6. `sp_vai_tro_xoa` có ý định xóa nhân viên thuộc vai trò; không gọi từ giao diện trước khi rà FK và chính sách nghiệp vụ.

## Quyết định cần chốt trước khi làm login

Login là yêu cầu hợp lý tiếp theo, nhưng chưa nên bắt đầu chỉ bằng một Blade form vì nguồn dữ liệu xác thực chưa thống nhất.

| Phương án | Ưu điểm | Đánh đổi |
| --- | --- | --- |
| Dùng Laravel `users` | Tương thích sẵn với guard/session/hash của Laravel. | Tách rời `nhan_vien`, `vai_tro` và procedure nghiệp vụ; cần thiết kế liên kết dữ liệu. |
| Dùng `nhan_vien` | Khớp schema, vai trò và `sp_nhan_vien_dang_nhap` hiện có. | Cần model/provider/guard hoặc luồng session riêng; hash SHA-256 hiện tại không phải lựa chọn Laravel hiện đại. |

Khuyến nghị thực dụng cho đồ án hiện tại: **chốt `nhan_vien` là tài khoản nghiệp vụ**, rồi thiết kế một luồng Laravel rõ ràng (model đúng bảng/khóa, login controller, session, middleware, phân quyền) trước khi tạo giao diện login. Không dùng cả hai hệ thống song song một cách ngầm định.

## Thứ tự công việc đề xuất

### Ưu tiên 0 — chuẩn bị an toàn

1. Xác nhận MySQL 8 và tạo database disposable/local riêng.
2. Import/rà SQL dump an toàn, không làm trên database có dữ liệu cần giữ.
3. Ghi lại tên database local trong `.env` nhưng không commit `.env`.

### Ưu tiên 1 — hoàn thiện một lát cắt CRUD phòng ban

1. Sửa route `PUT` → `update`; thêm `create`; đổi `detroy` → `destroy`.
2. Đồng bộ controller với procedure thật, bao gồm hai placeholder cho update.
3. Chọn cách lấy chi tiết phòng ban; không gọi procedure không tồn tại.
4. Sửa model map `phong_ban`/`ma_pb`/timestamps.
5. Viết lại Blade index/create/edit (có thể tách view edit) với CSRF, validation, flash message.
6. Thêm feature test cho các route và mock/DB test phù hợp; chạy test với MySQL tách biệt nếu cần procedure.

### Ưu tiên 2 — nền tảng quản trị và đăng nhập

1. Hoàn thiện layout backend, navigation và asset strategy (Vite hoặc CDN nhất quán).
2. Chốt phương án auth ở trên.
3. Tạo login/logout, middleware auth và kiểm tra vai trò trước khi mở các route backend.
4. Không để dashboard/CRUD công khai sau khi auth đã được thêm.

### Ưu tiên 3 — mở rộng theo dependency dữ liệu

1. Danh mục: chức vụ, trạng thái làm việc, vai trò/quyền.
2. Nhân viên.
3. Hợp đồng và lịch sử hệ số lương.
4. Nghỉ phép và chấm công.
5. Lương, báo cáo.
6. Import/export/backup/restore sau cùng, với biện pháp server-side an toàn.

Mỗi module nên đi theo lát cắt: **route → controller → validation → model/query/procedure → Blade → test**. Không sửa hàng loạt các controller rỗng chỉ để “đủ tên module”.

## Checklist cho phiên tiếp theo

```powershell
# Đọc bối cảnh
Get-Content -Raw AGENTS.md
Get-Content -Raw docs/CODEX_NEXT_HANDOFF.md

# Kiểm tra trạng thái trước khi sửa
git status --short
php artisan route:list --except-vendor

# Sau thay đổi PHP/Blade/frontend
php -l <file-php-da-sua>
php artisan test
npm run build
git diff --check
git status --short
```

Không tự ý commit, reset, import database hoặc sửa `.env` ngoài yêu cầu cụ thể của người dùng.

## Phạm vi của phiên audit này

Đã thêm duy nhất file handoff này. Không có code nghiệp vụ, SQL, cấu hình ứng dụng hay dữ liệu database nào được sửa.
