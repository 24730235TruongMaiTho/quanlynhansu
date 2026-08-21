# Website quản lý nhân sự

Đồ án nhóm xây dựng hệ thống quản lý nhân sự bằng Laravel, phục vụ đồng thời hai môn:

- **Lập trình Web Application**: Laravel MVC, API, validation, MySQL/MariaDB, xác thực, phân quyền và kiểm thử.
- **Thiết kế giao diện người dùng**: luồng thao tác, design system, responsive, accessibility và phản hồi trạng thái.

> **Trạng thái ngày 2026-08-21:** Tasks 13–20 đã được kiểm chứng hẹp, commit và push lên `origin/feature/quanly-nhan-vien`. Delivery checkpoint `7bedcadf8c374b38d2e3451617f288bca6184d5f` chứa source/test/SQL `ba6e0189e64eb3046164ae5183950afe0b5722be`, dependency locks `18ea209d89efce38596dd1440151f6d55ca90156` và tài liệu bằng chứng. Full guarded MariaDB wrapper đạt **165 tests, 3367 assertions, 1 platform skip, exit 0** và cleanup `0`; frontend `15/15`, build, Composer validate/audit, routes và diff checks pass. Browser employee responsive `320/375/768/1024/1440` pass với console sạch; avatar upload/replacement còn **blocked/unverified** vì Chrome file URL access. Full Laravel vẫn còn đúng baseline `/` 404 tại `ExampleTest`; không claim production readiness.

## Bắt đầu từ đâu

Đọc theo thứ tự sau trước khi nhận task:

1. [README tài liệu](docs/README.md) — bản đồ toàn bộ tài liệu.
2. [Trạng thái dự án](docs/PROJECT_STATUS.md) — module nào đã có, đang lỗi hoặc mới là kế hoạch.
3. [Kiến trúc](docs/ARCHITECTURE.md) — luồng web/API, lớp code và asset.
4. [Database](docs/DATABASE.md) — schema, stored procedure, cách import an toàn và các lệch hợp đồng.
5. [Hướng dẫn phát triển](docs/DEVELOPMENT_GUIDE.md) — setup, workflow và kiểm tra.
6. [Frontend](docs/FRONTEND_GUIDE.md) — layout hiện tại, shell mục tiêu và checklist UI.
7. [Roadmap](docs/ROADMAP.md) — thứ tự xử lý phụ thuộc.
8. [Handoff cho phiên tiếp theo](docs/CODEX_NEXT_HANDOFF.md) — snapshot kỹ thuật ngắn, có thể thay đổi theo HEAD.

Code và database live luôn có độ ưu tiên cao hơn snapshot trong tài liệu. Khi HEAD thay đổi, phải chạy lại route, test, build và kiểm tra hợp đồng database.

## Hiện trạng đã xác minh

### Task 20 final evidence (2026-08-21)

Acceptance disposable đã được Stop chính thức và postcheck về `0` cho
schema/state/lock/run/upload/listener/PHP/public-storage; target
`storage/app/public` được giữ. Browser evidence hẹp bao phủ login/logout,
CRUD, auth/RBAC, stale/filter/flash/edit mapping và responsive; session restore
sau khi chuyển nghỉ việc được automated-test riêng. Avatar file
upload chưa verify vì browser extension policy. Xem chi tiết ở
[PROJECT_STATUS.md](docs/PROJECT_STATUS.md) và ignored Task20 reports.

Snapshot này được đo trên nhánh `feature/quanly-nhan-vien`. Source commit là `ba6e0189e64eb3046164ae5183950afe0b5722be`, dependency-lock commit là `18ea209d89efce38596dd1440151f6d55ca90156`, documentation-evidence commit là `7bedcadf8c374b38d2e3451617f288bca6184d5f`. Sau lần push đầu, local HEAD, tracking upstream và remote ref đã được xác minh cùng ở `7bedcadf`; luôn revalidate lại vì commit ghi trạng thái delivery này nằm sau checkpoint đó.

| Hạng mục | Kết quả |
| --- | --- |
| Git | Branch `feature/quanly-nhan-vien`; source `ba6e018`, dependency locks `18ea209`, docs/evidence `7bedcad`; checkpoint đã push và xác minh trên origin |
| Laravel | 12.62.0 trên PHP 8.5.0; project target PHP 8.2+ |
| Route ứng dụng | 49 route; có login/logout, toàn bộ `/admin` yêu cầu auth và route nhân viên dùng Gate theo quyền |
| Frontend | `npm run test:frontend` 15 pass; `npm run build` pass; Vite 7.3.6, 16 modules transformed |
| Test | Unit `95 pass, 633 assertions`; scoped Feature employee/auth/compatibility `107 pass, 1093 assertions`; full Laravel `221 pass, 1 baseline fail, 1772 assertions` vì `/` chưa có route |
| Database local | MariaDB 10.4.32; full guarded employee wrapper **165 tests, 3367 assertions, 1 platform skip, exit 0**; cleanup schema `0`; không mutate `quan_ly_nhan_su` live |
| Auth/RBAC | Custom employee provider, login/logout, session fail-closed và 5 quyền nhân viên đã wired/test; role mặc định có 0 quyền |

`route:list`, một response `200` hoặc Vite build thành công chỉ chứng minh phạm vi hẹp; không chứng minh workflow nghiệp vụ chạy đúng.

## Trạng thái module

| Module | Trạng thái | Ghi chú ngắn |
| --- | --- | --- |
| Dashboard | Prototype | Route và Blade render được, chưa có dữ liệu nghiệp vụ |
| Lương | Prototype — blocked | API danh sách phụ thuộc procedure không tồn tại; write contract chưa chặn trùng kỳ; export/đối soát chưa có handler đầy đủ |
| Chấm công | Prototype — blocked | Hai procedure phân trang không tồn tại; validation có thể trả sai status; import/export chưa có workflow an toàn |
| Nghỉ phép | Prototype | Có UI/API CRUD và duyệt; chưa có test nghiệp vụ hoặc kiểm chứng mutation đầy đủ |
| Hệ số lương | Prototype | Có API đọc/thêm/sửa; JavaScript delete không có route DELETE; validation và schema còn lệch |
| Nhân viên | Verified hẹp và đã Git-deliver trên feature branch; chưa production-ready | List/create/detail/edit, avatar ở automated tests, delete-or-terminate, reset password, login/session, RBAC 5 quyền và responsive browser đã kiểm tra trên disposable MariaDB. Browser upload/thay avatar còn blocked do quyền Chrome; full suite còn baseline `/` 404 |
| Phòng ban | Prototype — blocked | Route/controller/Blade/procedure chưa khớp |
| Chức vụ | Prototype — unreachable | Có controller/service/repository/request/model nhưng chưa có route |
| Hợp đồng | Planned | Chưa có workflow quản trị; hiện chỉ được dùng làm dependency khi kiểm tra kết thúc làm việc |
| Vai trò/quyền | DB/RBAC nền tảng đã có; UI quản trị planned | Có 5 quyền nhân viên và procedure gán/xóa nội bộ; chưa expose workflow quản trị role qua web |
| Đăng nhập/phân quyền | Verified hẹp cho module nhân viên | Custom provider dùng bảng nhân viên, session từ chối `DA_NGHI`, route/Gate fail-closed; chưa phải security audit production toàn hệ thống |
| Báo cáo, backup/restore | Planned — unsafe legacy procedures | Procedure backup/restore hiện sinh cú pháp SQL Server, không dùng được cho MariaDB |

Chi tiết và bằng chứng nằm trong [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md).

## Kiến trúc hiện tại

```text
Blade page (/admin/*)
    └── JavaScript theo module
            └── JSON API (/api/v1/*)
                    └── Controller
                          ├── Service → Repository
                          └── Query Builder / stored procedure trực tiếp
                                  └── MariaDB
```

Code đang dùng hai hướng truy cập dữ liệu song song: service/repository và gọi `DB::select()/DB::statement()` trực tiếp. Chưa nên nhân rộng một hướng mới trước khi chốt chuẩn cho từng module.

Các thư mục chính:

```text
app/Http/Controllers/       Web/API controllers
app/Http/Requests/          Validation request
app/Models/                 Eloquent models
app/Services/               Nghiệp vụ trung gian
app/Repositories/           Truy cập stored procedure/query
resources/views/            Blade
resources/js/               JavaScript và Vite entry
routes/web.php              Route quản trị
routes/api.php              API v1
quan_ly_nhan_su.session.sql Schema nghiệp vụ hiện tại
tests/                      Unit/Feature/Frontend/MariaDB integration và acceptance harness
```

## Yêu cầu môi trường

- PHP 8.2+ và Composer.
- Node.js và npm.
- Baseline hiện chỉ được kiểm tra hẹp trên MariaDB 10.4.32. Cài mới MariaDB hoặc dùng MySQL 8 đều cần clean-replay dump và integration test.
- Git.

Máy audit hiện dùng PHP 8.5.0, Composer 2.8.12, Node.js 24.13.0, npm 11.6.2 và MariaDB 10.4.32.

## Cài đặt local an toàn trên Windows/PowerShell

```powershell
git clone <repository-url>
Set-Location quanlynhansu

composer install
Copy-Item .env.example .env
php artisan key:generate

npm install
npm run build
```

### Cấu hình môi trường

`.env.example` đã dùng MySQL/MariaDB, timezone `Asia/Ho_Chi_Minh`/`+07:00`, session/cache file và queue sync. Trước khi chạy nghiệp vụ, điền credential local và chỉ trỏ tới database disposable hoặc database local được phép dùng:

```dotenv
APP_LOCALE=vi

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quan_ly_nhan_su
DB_USERNAME=<tai-khoan-local>
DB_PASSWORD=<mat-khau-local>
DB_TIMEZONE=+07:00

NHAN_VIEN_MODULE_ENABLED=true

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Không commit `.env` hoặc thông tin đăng nhập.

### Chuẩn bị database

`quan_ly_nhan_su.session.sql` bắt đầu bằng:

```sql
DROP DATABASE IF EXISTS quan_ly_nhan_su;
```

Chỉ import vào database local/disposable không chứa dữ liệu cần giữ. Dump sẽ tạo lại schema nghiệp vụ nhưng không có business seed đầy đủ. Chưa chạy `php artisan db:seed`: seeder mặc định tham chiếu model `App\Models\User` không tồn tại.

Quy trình chi tiết: [docs/DATABASE.md](docs/DATABASE.md).

## Chạy dự án

Dùng hai terminal:

```powershell
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

Các trang có route hiện nằm dưới `/admin`; repository chưa có route `/`.

Không dùng `composer setup` như một lệnh “cài tất cả” ở trạng thái hiện tại, vì script này tự chạy migrations trong khi chiến lược import dump/migrations chưa được chốt.

## Kiểm tra

```powershell
php artisan route:list --except-vendor
php artisan test
npm run build
composer validate --no-check-publish
composer audit --locked
git diff --check
git status --short
```

Baseline Task 20 trước delivery:

- Route list: pass, 49 route.
- Frontend: 15 pass; build pass, 16 modules.
- Composer validate/install dry-run pass; audit không còn advisory sau khi nâng sáu dependency tương thích trong lockfile.
- Full Laravel: 221 pass, 1 baseline fail, 1772 assertions; lỗi duy nhất do `/` trả 404.
- Guarded MariaDB employee: 165 pass, 3367 assertions, 1 platform skip; cleanup `0`. Skip duy nhất do Windows từ chối tạo disposable state symlink.

Ngoài ra, `phpunit.xml` ép test dùng SQLite in-memory. Kể cả khi suite này xanh, nó vẫn không chứng minh stored procedure/trigger MariaDB hoạt động; cần một integration suite riêng trên database disposable.

Không sửa test để “xanh” bằng cách bỏ assertion; hãy chốt route home mong muốn rồi cập nhật route và test cùng nhau.

## Các blocker ưu tiên

1. Chốt route `/`/landing để xử lý baseline `ExampleTest` 404.
2. Xác minh browser upload/thay avatar sau khi Chrome extension được cấp file URL access; không suy từ automated test thành browser pass.
3. Tạo quy trình rollout/backup/master data cho môi trường dùng thật; không chạy canonical dump phá hủy trên database cần giữ dữ liệu.
4. Bổ sung ba procedure còn thiếu ngoài module nhân viên: `sp_phong_ban_chi_tiet`, `sp_cham_cong_chi_tiet_phan_trang`, `sp_luong_tim_kiem_phan_trang`.
5. Sửa route/action/Blade, API naming và validation/error contract của các module còn lại.
6. Khóa cả read/write contract lương, gồm unique `(ma_nv, ky_luong)`.
7. Xử lý tích hợp nhánh `frontend` như một workstream UI riêng; không merge tự động.

## Làm việc nhóm

- Mỗi task dùng branch ngắn hạn: `feature/*`, `fix/*`, `docs/*`, `test/*`.
- Không force-push lên branch dùng chung.
- Không tự động fetch/merge/rebase/push hoặc tạo upstream khi chưa được yêu cầu.
- Một module chỉ được gọi là hoàn thành khi route, validation, data contract, UI states, auth, test và browser acceptance phù hợp đều có bằng chứng.
- Pull request phải ghi phạm vi, cách kiểm tra, rủi ro còn lại và ảnh giao diện khi có thay đổi UI.

Xem checklist đầy đủ tại [docs/DEVELOPMENT_GUIDE.md](docs/DEVELOPMENT_GUIDE.md).
