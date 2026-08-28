# Hướng dẫn module Nhân viên

> Cập nhật 2026-08-27. Đây là guide cho code hiện tại trên `main`; code/route/test/database live được ưu tiên hơn snapshot. Module chỉ được gọi **verified hẹp**, chưa production-ready và browser chưa được kiểm chứng trong phiên hiện tại.

## Hợp đồng dữ liệu

Dựng database fresh theo thứ tự:

```text
database/sql/tao_bang.sql
database/sql/du_lieu_mau.sql
database/sql/quyen_vai_tro.sql
```

Ba file tạo 15 bảng, 19 nhân viên, 37 quyền và 12 thủ tục RBAC trên database rỗng/disposable. `nhan_vien` chứa trực tiếp các cột địa chỉ, avatar và `ngay_nghi_viec`; mã nhân viên là chuỗi 5 chữ số. `quan_ly_nhan_su.session.sql`, `LocalDemoSeeder` và script employee cũ là lịch sử, không phải setup path.

Không chạy dump trên database cần giữ dữ liệu: SQL fresh có `USE quan_ly_nhan_su` và setup phải được backup/preflight/approval riêng. Không chạy `php artisan db:seed` theo quán tính vì seeder mặc định không phải identity provider hiện tại.

## Luồng backend

```text
routes/web.php
  -> Form Request
  -> NhanVienController
  -> NhanVienService
  -> NhanVienRepository (explicit Query Builder)
  -> contract 15 bảng
```

Các file chính:

- `app/Http/Controllers/Backend/NhanVienController.php`: list/show/edit/update/lifecycle và HTTP error boundary.
- `app/Http/Requests/ListNhanVienRequest.php`, `StoreNhanVienRequest.php`, `UpdateNhanVienRequest.php`: normalize/validation; update authorize target trước validation.
- `app/Services/NhanVienService.php`: transaction hồ sơ/địa chỉ/avatar, hash và cleanup file.
- `app/Repositories/NhanVienRepository.php`: projection/query/write thuần dữ liệu; không biết auth.
- `app/Auth/NhanVienUserProvider.php`, `app/Models/NhanVien.php`: identity trên `nhan_vien`; auth projection có `ma_pb`.
- `app/Support/NhanVienScope.php`: policy scope ở ranh giới HTTP.

Route canonical: `/nhan-vien`, `/nhan-vien/create`, `/nhan-vien/{ma_nv}`, `/nhan-vien/{ma_nv}/edit`; mọi route yêu cầu auth và Gate `NhanVien.Read/Insert/Update/Delete` tương ứng. Form không nhận role, mã, password hash hoặc ngày nghỉ việc từ client.

## Scope Trưởng phòng và self-delete

Role `NhanVienRole::DepartmentManager` có mã `4`. Actor role này phải có `ma_pb` là số dương hợp lệ, được hydrate từ auth row sau login. `NhanVienScope`:

- ép list filter `ma_pb` về đúng `ma_pb` của actor, không tin filter client;
- giới hạn department lookup trên UI về phòng ban actor;
- trả list rỗng an toàn khi scope thiếu/sai;
- trả 404 cho show/edit/update/destroy cross-department hoặc scope actor không hợp lệ;
- kiểm tra target trong `UpdateNhanVienRequest::authorize()` trước validation/mutation.

Mọi actor đều bị chặn tự xóa bằng lỗi ổn định `Không thể tự xóa tài khoản đang đăng nhập.` trước khi gọi service. Index/show/edit và partial action không render nút phá hủy cho chính actor. Đây là policy Nhân viên, không sửa mapping RBAC/Gate của đồng nghiệp.

## Auth và bảo mật

Provider chỉ đọc identifier/password ở server boundary, từ chối status terminal, không support remember token và không trả `mat_khau` ra UI/API. Hash legacy chỉ được kiểm tra rồi CAS rehash sang bcrypt sau login thành công. Không log password, hash, credential, token, SQLSTATE hoặc filesystem path.

Avatar mới chỉ lưu dưới prefix owned; file cũ xóa sau commit và file mới được bù trừ khi rollback. Lỗi DB trả thông báo ổn định, không lộ raw exception.

## UI/acceptance

Các view chính: `resources/views/backend/nhanvien/index.blade.php`, `create.blade.php`, `show.blade.php`, `edit.blade.php` và `partials/`. JavaScript nằm dưới `resources/js/frontend/nhanvien/`.

UI phải giữ loading, empty, success, validation/server error, disabled/submitting, confirm action, semantic label/focus/contrast và responsive table. Automated test/build không thay thế browser; browser avatar file chooser vẫn chưa được kiểm chứng.

## Kiểm tra

```powershell
php artisan test tests/Unit/Support/NhanVienScopeTest.php tests/Feature/Backend/NhanVien/NhanVienDepartmentScopeTest.php tests/Unit/Models/NhanVienTest.php
php artisan test tests/Feature/Backend/NhanVien tests/Feature/Auth/EmployeeAuthenticationTest.php tests/Unit/Auth/NhanVienUserProviderTest.php
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
```

PHPUnit mặc định dùng SQLite in-memory. Fresh MariaDB chỉ chạy trên disposable guard:

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
```

Không claim MariaDB/production/browser gate nếu command tương ứng chưa thực sự chạy. Không tự chạy `tests/Support/employee-acceptance.ps1` để suy ra fresh 15-table/browser pass; harness đó là lịch sử và phải Stop đúng StateFile nếu được giao dùng.

## Ownership và việc ngoài phạm vi

Task Nhân viên không sửa Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC hoặc API của họ. Các blocker đã audit:

- Dashboard: auth/thiếu quyền và dữ liệu riêng cần task Dashboard.
- Lương: gọi `sp_luong_tim_kiem_phan_trang` nhưng procedure không có trong ba SQL active.
- Chấm công: lookup gọi `sp_phong_ban_danh_sach`, update gọi `sp_cham_cong_cap_nhat`; cả hai không có trong ba SQL active.
- Nghỉ phép: approve gọi `sp_nghi_phep_duyet_phep`; procedure không có trong ba SQL active.
- Model/validation/API/exception legacy của module ngoài ownership còn drift; Hợp đồng/RBAC quản trị mới chỉ verified hẹp hoặc thiếu mutation/browser evidence.

Chỉ xử lý các mục trên khi có task giao rõ và có owner/contract riêng.
