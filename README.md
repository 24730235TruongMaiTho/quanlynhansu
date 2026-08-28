# Website quản lý nhân sự

Đồ án nhóm Laravel cho hai môn Lập trình Web Application và Thiết kế giao diện người dùng. Ứng dụng hiện có các lát cắt quản trị Nhân viên, Phòng ban và Chức vụ; nhiều module còn lại chỉ ở mức prototype hoặc có lệch hợp đồng cần xử lý riêng.

## Quick start an toàn

Yêu cầu PHP 8.2+, Composer, Node.js/npm và MariaDB 10.4.x (runtime đã kiểm chứng: MariaDB 10.4.32).

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

Điền credential local/disposable vào `.env`, không commit file này. Database fresh phải chạy theo đúng thứ tự:

```text
database/sql/tao_bang.sql
database/sql/du_lieu_mau.sql
database/sql/quyen_vai_tro.sql
```

Ba file trên tạo hợp đồng 15 bảng, seed 19 nhân viên, 37 quyền và 12 thủ tục RBAC. Chúng có `USE quan_ly_nhan_su`; chỉ chạy trên database rỗng/disposable hoặc target đã được phê duyệt và backup. Không dùng `php artisan migrate`/`db:seed` thay cho setup này khi chưa chốt chiến lược migration.

Mở `/`: guest vào `/dang-nhap`, user đã xác thực vào `/tong-quan`. Các trang module nằm dưới `/nhan-vien`, `/phong-ban` và `/chuc-vu`.

## Kiến trúc và ownership

```text
Route + middleware auth/Gate
  -> Form Request
  -> Controller
  -> Service/Repository
  -> Query Builder trên hợp đồng SQL active
  -> MariaDB
```

Auth dùng provider `nhan-vien` trên bảng `nhan_vien`; `ma_pb` được hydrate vào model để scope Trưởng phòng hoạt động sau login. Module Nhân viên áp phạm vi tại `App\Support\NhanVienScope`: role `NhanVienRole::DepartmentManager` chỉ xem/thao tác trong phòng ban của actor, filter `ma_pb` từ request không được override. Repository không biết auth và không chứa policy HTTP.

Phạm vi code hiện tại của nhóm là Nhân viên, Phòng ban và Chức vụ. Vấn đề của Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC hoặc route API của đồng nghiệp chỉ được ghi chú trong tài liệu, trừ khi có task giao rõ; không tự sửa code ngoài ownership.

## Trạng thái module

| Module | Trạng thái hiện tại | Giới hạn chính |
| --- | --- | --- |
| Nhân viên | Verified hẹp | CRUD/lifecycle/auth/RBAC và department scope có test; browser và production chưa được claim |
| Phòng ban | Verified hẹp | Direct Query Builder, transaction/row lock, Gate canonical; browser chưa kiểm chứng |
| Chức vụ | Verified hẹp | Direct Query Builder, transaction/row lock, Gate canonical; browser chưa kiểm chứng |
| Dashboard | Prototype | Chỉ có kiểm tra auth/permission và render; dữ liệu nghiệp vụ/acceptance riêng chưa đóng |
| Lương | Prototype — blocked | `LuongRepository@all` gọi `sp_luong_tim_kiem_phan_trang`, procedure này không có trong ba SQL active |
| Chấm công | Prototype — blocked | Lookup gọi `sp_phong_ban_danh_sach` và update gọi `sp_cham_cong_cap_nhat`; các procedure không có trong ba SQL active |
| Nghỉ phép | Prototype — blocked | Duyệt gọi `sp_nghi_phep_duyet_phep`, procedure không có trong ba SQL active |
| Hợp đồng | Planned/scaffold | Chưa có workflow quản trị và mutation evidence đầy đủ |
| Vai trò/Phân quyền/RBAC | Nền tảng verified hẹp | Catalog/procedure RBAC active có test hẹp; UI quản trị và browser/mutation evidence đầy đủ chưa được claim |

Các lệch legacy khác (model/validation, API naming và exception contract ở module ngoài ownership) được giữ làm backlog; không sửa trong lát cắt này. `sp_phong_ban_*` của code cũ không phải caller active của repository Phòng ban.

## Kiểm tra

```powershell
php -l app/Support/NhanVienScope.php
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
git diff --check
git status --short
```

`phpunit.xml` mặc định dùng SQLite in-memory nên không chứng minh DDL/foreign key/procedure MariaDB. Fresh DB gate phải dùng wrapper disposable:

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
```

Không claim browser acceptance, production readiness hoặc MySQL 8 compatibility nếu chưa có bằng chứng tương ứng. Avatar file chooser vẫn là gate browser riêng.

## Làm việc nhóm

- Đọc `AGENTS.md`, `docs/CODEX_NEXT_HANDOFF.md`, `docs/PROJECT_STATUS.md`, `docs/DATABASE.md` và guide của module trước khi sửa.
- Mỗi task dùng branch ngắn hạn; bảo toàn thay đổi chưa commit của đồng đội.
- Không tự fetch, merge, rebase, cherry-pick, push hoặc tạo worktree.
- PR/handoff phải nêu rõ file, scope ownership, lệnh kiểm tra, kết quả và giới hạn; không gọi module là hoàn thành chỉ vì route, response `200`, test SQLite hoặc Vite build xanh.

Tài liệu chi tiết: [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md), [docs/DATABASE.md](docs/DATABASE.md), [docs/EMPLOYEE_MODULE_GUIDE.md](docs/EMPLOYEE_MODULE_GUIDE.md) và [docs/DEVELOPMENT_GUIDE.md](docs/DEVELOPMENT_GUIDE.md).
