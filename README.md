# Website quản lý nhân sự

Đồ án nhóm xây dựng hệ thống quản lý nhân sự bằng Laravel, phục vụ đồng thời hai môn:

- **Lập trình Web Application**: Laravel MVC, API, validation, MySQL/MariaDB, xác thực, phân quyền và kiểm thử.
- **Thiết kế giao diện người dùng**: luồng thao tác, design system, responsive, accessibility và phản hồi trạng thái.

> **Trạng thái ngày 2026-08-21:** Module Nhân viên verified hẹp đã được tích hợp vào `main` qua merge `aa7741914e60acb0243fcfe08f2d1dbee27b4a1f` (parents `1677f202f70e020ce75ef0fa88b11b9db44fa047` và `91bb7a106e6a4fae8a61c4eb383dad596bf2b199`), sau đó test attendance `9cd2c30`. Delivery evidence lịch sử nằm ở feature commits `ba6e0189e64eb3046164ae5183950afe0b5722be`, `18ea209d89efce38596dd1440151f6d55ca90156` và `7bedcadf8c374b38d2e3451617f288bca6184d5f`. Full Laravel current sau vòng authorization/guard là **230 pass, 1 fail, 1809 assertions**; failure duy nhất là `ExampleTest` với `/` 404. Baseline trước vòng này là `224/1789`. Guarded MariaDB rerun sau tích hợp timeout khoảng 184 giây rồi cleanup sạch, nên không claim pass; `165/3367` chỉ là historical Task 20. Frontend `15/15`, build, Composer validate/audit, routes và diff checks pass. Browser employee responsive `320/375/768/1024/1440` pass với console sạch; avatar upload/replacement còn **blocked/unverified** vì Chrome file URL access. Không claim production readiness.

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
9. [Hướng dẫn module Nhân viên](docs/EMPLOYEE_MODULE_GUIDE.md) — setup, database decision table, demo và bản đồ tiếp tục.

Code và database live luôn có độ ưu tiên cao hơn snapshot trong tài liệu. Khi HEAD thay đổi, phải chạy lại route, test, build và kiểm tra hợp đồng database.

## Hiện trạng đã xác minh

Local rollout hiện có bằng chứng environment-specific: 16 bảng, 1 view, 8
function, 10 trigger, 69 procedure; demo 5 employee/5 address, admin 5 quyền
employee và bốn normal zero quyền. Đây không phải production claim; xem
[EMPLOYEE_MODULE_GUIDE.md](docs/EMPLOYEE_MODULE_GUIDE.md) để biết target và
cleanup safety.

### Task 20 final evidence (2026-08-21)

Acceptance disposable đã được Stop chính thức và postcheck về `0` cho
schema/state/lock/run/upload/listener/PHP/public-storage; target
`storage/app/public` được giữ. Browser evidence hẹp bao phủ login/logout,
CRUD, auth/RBAC, stale/filter/flash/edit mapping và responsive; session restore
sau khi chuyển nghỉ việc được automated-test riêng. Avatar file
upload chưa verify vì browser extension policy. Xem chi tiết ở
[PROJECT_STATUS.md](docs/PROJECT_STATUS.md) và ignored Task20 reports.

Các số liệu Task 20 trong bảng là historical feature-branch evidence; hãy revalidate HEAD/upstream và các gate khi bắt đầu phiên mới. Merge tích hợp là `aa77419`, seed demo là `91bb7a1`, và focused attendance test là `9cd2c30`.

| Hạng mục | Kết quả |
| --- | --- |
| Git | `main` đã chứa merge `aa77419` với parents `1677f20` và `91bb7a1`; luôn revalidate HEAD/upstream khi tiếp tục |
| Laravel | 12.62.0 trên PHP 8.5.0; project target PHP 8.2+ |
| Route ứng dụng | 49 route; có login/logout, toàn bộ `/admin` yêu cầu auth và route nhân viên dùng Gate theo quyền |
| Frontend | `npm run test:frontend` 15 pass; `npm run build` pass; Vite 7.3.6, 16 modules transformed |
| Test | Scoped employee/auth hiện tại `119 pass, 1141 assertions`; attendance compatibility `12 pass, 55 assertions`; full Laravel `230 pass, 1 fail, 1809 assertions` vì `/` chưa có route |
| Database local | MariaDB 10.4.32; rollout environment-specific 16 tables/1 view/8 functions/10 triggers/69 procedures và demo 5 employee/5 address; guarded rerun sau tích hợp timeout khoảng 184 giây, cleanup sạch; không phải production |
| Auth/RBAC | Custom employee provider, login/logout, session fail-closed và 5 quyền nhân viên đã wired/test; role mặc định có 0 quyền |

`route:list`, một response `200` hoặc Vite build thành công chỉ chứng minh phạm vi hẹp; không chứng minh workflow nghiệp vụ chạy đúng.

## Trạng thái module

| Module | Trạng thái | Ghi chú ngắn |
| --- | --- | --- |
| Dashboard | Prototype | Route và Blade render được, chưa có dữ liệu nghiệp vụ |
| Lương | Prototype — blocked | API danh sách phụ thuộc procedure không tồn tại; write contract chưa chặn trùng kỳ; export/đối soát chưa có handler đầy đủ |
| Chấm công | Prototype — blocked | Chi tiết dùng Query Builder vì procedure thiếu; update/read đã khóa auth, rollout và Gate; import/export chưa có workflow an toàn |
| Nghỉ phép | Prototype | Có UI/API CRUD và duyệt; chưa có test nghiệp vụ hoặc kiểm chứng mutation đầy đủ |
| Hệ số lương | Prototype | Có API đọc/thêm/sửa; JavaScript delete không có route DELETE; validation và schema còn lệch |
| Nhân viên | Verified hẹp và đã tích hợp vào `main` qua `aa77419`; chưa production-ready | List/create/detail/edit, avatar ở automated tests, delete-or-terminate, reset password, login/session, RBAC 5 quyền và responsive browser đã kiểm tra trên disposable MariaDB. Browser upload/thay avatar còn blocked do quyền Chrome; full suite còn baseline `/` 404 |
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
- Full Laravel hiện tại: 230 pass, 1 fail, 1809 assertions; lỗi duy nhất do `/` trả 404. Baseline trước vòng authorization/guard là 224/1789.
- Guarded MariaDB rerun sau tích hợp: timeout khoảng 184 giây; process/schema/state đã cleanup sạch, không claim pass. Kết quả `165/3367` là historical Task 20.

Ngoài ra, `phpunit.xml` ép test dùng SQLite in-memory. Kể cả khi suite này xanh, nó vẫn không chứng minh stored procedure/trigger MariaDB hoạt động; cần một integration suite riêng trên database disposable.

Không sửa test để “xanh” bằng cách bỏ assertion; hãy chốt route home mong muốn rồi cập nhật route và test cùng nhau.

## Các blocker ưu tiên

1. Chốt route `/`/landing để xử lý baseline `ExampleTest` 404.
2. Xác minh browser upload/thay avatar sau khi Chrome extension được cấp file URL access; không suy từ automated test thành browser pass.
3. Tạo quy trình rollout/backup/master data cho môi trường dùng thật; không chạy canonical dump phá hủy trên database cần giữ dữ liệu.
4. Bổ sung hai procedure còn thiếu ngoài module nhân viên: `sp_phong_ban_chi_tiet` và `sp_luong_tim_kiem_phan_trang`. Chấm công chi tiết đã chuyển sang Query Builder trong scope tích hợp.
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
