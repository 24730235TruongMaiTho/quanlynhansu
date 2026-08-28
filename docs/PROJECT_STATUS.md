# Trạng thái dự án

> Cập nhật 2026-08-27 trên local `main` (HEAD `f71c0b20a4e04e8e2ec32cdad2a68722e4aaa0b7`). Các số liệu trong tài liệu này chỉ là bằng chứng của đúng lệnh/ môi trường được ghi; không suy rộng thành production hoặc browser acceptance.

## Nguồn và phạm vi

SQL fresh active phải chạy theo thứ tự `database/sql/tao_bang.sql` → `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql` trên database rỗng/disposable đã được phê duyệt. Hợp đồng có đúng 15 bảng, 19 nhân viên, 37 quyền và 12 thủ tục RBAC. `quan_ly_nhan_su.session.sql`, `LocalDemoSeeder` và script SQL employee cũ chỉ để đối chiếu lịch sử.

Ownership hiện tại chỉ gồm code Nhân viên, Phòng ban và Chức vụ. Lỗi hoặc thiếu hợp đồng của Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC và API của đồng nghiệp được ghi chú trong tài liệu; không sửa code ngoài scope nếu chưa được giao rõ.

## Bằng chứng phiên hiện tại

| Kiểm tra | Kết quả |
| --- | --- |
| Git | `main`, HEAD `f71c0b2`, chỉ có thay đổi local của task và file untracked người dùng |
| Focused scope tests | `14 tests, 66 assertions` pass |
| Employee/auth/regression slice | `110 tests, 1308 assertions` pass |
| Full Laravel | `288 tests, 2222 assertions` pass |
| Phòng ban/Chức vụ | Feature tests pass trong full suite; code không đổi trong phiên này |
| Route inventory | `79` route; `php artisan route:list --except-vendor` pass |
| Frontend/build | `npm run test:frontend`: `18` pass; `npm run build`: `19 modules transformed` |
| Composer/lint | `composer validate --no-check-publish`, PHP lint file sửa và `git diff --check` pass |
| Fresh MariaDB | `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; schema disposable, không phải evidence database live/production |
| Browser | Chưa chạy trong phiên này |

Full suite mặc định dùng SQLite in-memory. Muốn kiểm tra DDL, foreign key và dữ liệu MariaDB phải dùng wrapper guarded disposable; không trỏ vào database live.

## Trạng thái module

| Module | Web/UI | Data/test | Trạng thái |
| --- | --- | --- | --- |
| Nhân viên | List/filter/pagination, create, show, edit, lifecycle; auth/Gate; self-delete hidden | Direct Query Builder trên 15 bảng; auth hydrate `ma_pb`; scope Trưởng phòng; automated tests pass | **Verified hẹp**, browser/production chưa claim |
| Phòng ban | CRUD server-rendered, action gating và lỗi an toàn | Direct Query Builder, transaction/row lock; feature/MariaDB evidence trước đó | **Verified hẹp**, code không đổi phiên này, browser chưa claim |
| Chức vụ | CRUD server-rendered, action gating và lỗi an toàn | Direct Query Builder, transaction/row lock; feature/MariaDB evidence trước đó | **Verified hẹp**, code không đổi phiên này, browser chưa claim |
| Dashboard | Render được, auth/permission riêng | Chưa có nghiệp vụ dữ liệu đầy đủ | **Prototype**; không sửa trong scope |
| Lương | UI/API prototype | `LuongRepository@all` gọi `sp_luong_tim_kiem_phan_trang` thiếu trong ba SQL active | **Prototype — blocked** |
| Chấm công | UI/API prototype | Lookup gọi `sp_phong_ban_danh_sach`; update gọi `sp_cham_cong_cap_nhat`; cả hai thiếu trong ba SQL active | **Prototype — blocked** |
| Nghỉ phép | UI/API prototype | Approve gọi `sp_nghi_phep_duyet_phep`, thiếu trong ba SQL active | **Prototype — blocked** |
| Hợp đồng | Scaffold/model/controller hạn chế | Chưa có workflow mutation và browser evidence đầy đủ | **Planned/scaffold** |
| Vai trò/Phân quyền/RBAC | Một phần UI quản trị | Catalog và 12 procedure RBAC thuộc SQL active; mutation/UI/browser chưa được đóng toàn bộ | **Nền tảng verified hẹp** |

## Module Nhân viên

### Scope Trưởng phòng

`App\Support\NhanVienScope` áp policy tại controller/request, repository vẫn thuần dữ liệu. Actor có `ma_vt = 4` (`NhanVienRole::DepartmentManager`) phải có `ma_pb` là số dương hợp lệ. List ép filter `ma_pb` theo actor và chỉ trả lookup phòng ban tương ứng. Show/edit/update/destroy cross-department trả 404 để không lộ target; actor thiếu/sai `ma_pb` cũng fail closed. `UpdateNhanVienRequest::authorize()` lookup target trước validation.

Destroy chặn mã actor trước khi gọi service bằng lỗi an toàn. Index/show/edit không render action phá hủy cho chính actor. Auth provider và `NhanVien::fromAuthRow()` hydrate `ma_pb` để scope không mất sau login/session restore. Không thay đổi mapping role/Gate/RBAC.

### Data contract

Repository Nhân viên dùng explicit Query Builder trên `nhan_vien` và các bảng liên quan của contract 15 bảng, không gọi procedure employee/auth hay tạo SQL object mới. Avatar/path cleanup, hash và transaction vẫn thuộc service boundary; password/hash không đi ra view/API. Địa chỉ nằm trực tiếp trên `nhan_vien`.

### Giới hạn

Automated tests không thay thế browser. Avatar file chooser/replacement, production rollout, MySQL 8 compatibility và mutation trên database thật chưa được claim. Không gọi local/disposable result là production-ready.

## Lệch hợp đồng ngoài ownership (chỉ ghi chú)

- Dashboard: vấn đề auth/thiếu quyền hoặc dữ liệu riêng cần xử lý bằng task Dashboard; không sửa ở đây.
- Lương: `LuongRepository@all` gọi `sp_luong_tim_kiem_phan_trang`, procedure không tồn tại trong SQL active.
- Chấm công: `ChamCongController` lookup gọi `sp_phong_ban_danh_sach`, update gọi `sp_cham_cong_cap_nhat`; các procedure không tồn tại trong SQL active. Không sửa caller trong task Phòng ban/Nhân viên.
- Nghỉ phép: `NghiPhepController` approve gọi `sp_nghi_phep_duyet_phep`, procedure không tồn tại trong SQL active.
- Model/validation/API/exception của các module legacy còn drift so với schema; phải audit riêng theo module.
- Hợp đồng mới chỉ là scaffold; Vai trò/Phân quyền/RBAC có catalog và procedure nền tảng nhưng UI quản trị, mutation và browser evidence chưa đầy đủ.

## Quy tắc thay đổi

Một module chỉ được gọi Done khi route, validation, data contract, UI states, auth/authorization, feature/integration tests, build và browser acceptance phù hợp đều có bằng chứng. Không xóa assertion hoặc đổi tài liệu để che blocker. Chỉ mutation trên database test/disposable; không chạy canonical SQL destructive trên database cần giữ dữ liệu.

Lệnh chuẩn:

```powershell
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
git diff --check
git status --short
```

## Cập nhật feedback giao diện 2026-08-28

Lát cắt feedback 1, 2, 4, 5, 6, 7 và 8 đã được triển khai trong phạm vi Nhân viên, Phòng ban, Chức vụ và shared UI. Danh sách Chức vụ/Phòng ban dùng filter tên, chọn số dòng và pagination số; danh sách Nhân viên dùng summary/pagination/action select thống nhất nhưng vẫn giữ whitelist query string, Gate, scope Trưởng phòng và guard tự xóa. Chi tiết Nhân viên đã nhóm dữ liệu, tăng avatar, tách Tài khoản và hiển thị hệ số chức vụ từ join hiện có.

Verification cuối phiên: focused module/regression `53 tests, 481 assertions` và repository pagination `14 tests, 64 assertions` pass; full Laravel `294 tests, 2287 assertions` pass; frontend `21/21` pass; Vite `21 modules transformed`; route inventory `79`, Composer, PHP lint và `git diff --check` pass. MariaDB disposable: `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; đây không phải database live. Browser acceptance, font/network thật và production chưa được kiểm chứng.

Contract `paginate()` mới không loại bỏ `all()`. Không tạo show route CV/PB, không query lương/hợp đồng, không thêm `noi_sinh` vào schema. Font Be Vietnam Pro và nhãn sidebar đã cập nhật. Chi tiết acceptance/deferred/backlog nằm trong `docs/FEEDBACK_ACTION_PLAN.md`; browser acceptance và database live vẫn chưa claim.
