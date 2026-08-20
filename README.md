# Website quản lý nhân sự

Đồ án nhóm xây dựng hệ thống quản lý nhân sự bằng Laravel, phục vụ đồng thời hai môn:

- **Lập trình Web Application**: Laravel MVC, API, validation, MySQL/MariaDB, xác thực, phân quyền và kiểm thử.
- **Thiết kế giao diện người dùng**: luồng thao tác, design system, responsive, accessibility và phản hồi trạng thái.

> **Trạng thái ngày 2026-08-20:** Task 12 scoped implementation commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f` đã được xác minh hiện diện/ancestor trên `origin/feature/quanly-nhan-vien` và đã được scoped code/test review **Approve**. Fresh pre-push evidence: PHP employee `84/907`, MariaDB disposable `20/436` cleanup count `0`, frontend `5`, build `13`, route `44`, Composer/lint pass; full Laravel còn baseline `158 pass, 1 fail` tại `ExampleTest` vì `/` trả 404. Repository vẫn là prototype tích hợp; module hard-disabled bằng literal `config/nhanvien.php:enabled = false` và không được bật trước auth/RBAC/Gates.

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

Snapshot này được đo trên nhánh `feature/quanly-nhan-vien`; implementation commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f` đã được xác minh trên origin. Revalidate HEAD trước khi dùng snapshot vì docs delivery có thể nằm ở commit hiện tại khác.

| Hạng mục | Kết quả |
| --- | --- |
| Git | Implementation commit `3c07d88db59d3083e0728c4c2a71ce3b9039f75f` đã push/được xác minh trên origin; revalidate current HEAD và worktree trước thao tác tiếp theo |
| Laravel | 12.62.0 trên PHP 8.5.0; project target PHP 8.2+ |
| Route ứng dụng | 44 route: 17 web `/admin/*`, 27 API `/api/v1/*` |
| Frontend | `npm run test:frontend` 5 pass; `npm run build` pass; Vite 7.3.6, 13 modules transformed |
| Test | Fresh employee Feature/Unit `84 pass, 907 assertions`; full Laravel `158 pass, 1 baseline fail` vì `/` chưa có route; Composer/lint pass |
| Database local | MariaDB 10.4.32; guarded employee trio `20 tests, 436 assertions` pass, cleanup count `0`; không re-read/mutate `quan_ly_nhan_su` live |
| Auth/RBAC | Chưa có route đăng nhập, middleware auth hoặc kiểm tra quyền |

`route:list`, một response `200` hoặc Vite build thành công chỉ chứng minh phạm vi hẹp; không chứng minh workflow nghiệp vụ chạy đúng.

## Trạng thái module

| Module | Trạng thái | Ghi chú ngắn |
| --- | --- | --- |
| Dashboard | Prototype | Route và Blade render được, chưa có dữ liệu nghiệp vụ |
| Lương | Prototype — blocked | API danh sách phụ thuộc procedure không tồn tại; write contract chưa chặn trùng kỳ; export/đối soát chưa có handler đầy đủ |
| Chấm công | Prototype — blocked | Hai procedure phân trang không tồn tại; validation có thể trả sai status; import/export chưa có workflow an toàn |
| Nghỉ phép | Prototype | Có UI/API CRUD và duyệt; chưa có test nghiệp vụ hoặc kiểm chứng mutation đầy đủ |
| Hệ số lương | Prototype | Có API đọc/thêm/sửa; JavaScript delete không có route DELETE; validation và schema còn lệch |
| Nhân viên | Task 12 scoped delivery complete; hard-disabled; module chưa production-ready | Commit `3c07d88` đã push; Feature/Unit `84/907`, MariaDB `20/436` cleanup `0`, reviewer Approve. Browser/auth-RBAC chưa có; Task13 lifecycle/auth DB contracts là bước kế tiếp nhưng chưa bắt đầu. Không coi endpoint là public-safe khi bật cờ |
| Phòng ban | Prototype — blocked | Route/controller/Blade/procedure chưa khớp |
| Chức vụ | Prototype — unreachable | Có controller/service/repository/request/model nhưng chưa có route |
| Hợp đồng, vai trò, quyền, tài khoản | Planned | Nhiều controller rỗng, chưa có workflow |
| Đăng nhập/phân quyền | Planned — critical | Cần chốt nguồn tài khoản và chiến lược hash/session |
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
tests/                      Hiện mới có 2 test mẫu
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

`.env.example` hiện vẫn là cấu hình Laravel mặc định dùng SQLite và database-backed session/cache/queue; cấu hình đó **không đủ** cho các module gọi stored procedure. Trước khi chạy nghiệp vụ, chỉnh `.env` local theo nguyên tắc:

```dotenv
APP_LOCALE=vi

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quan_ly_nhan_su
DB_USERNAME=<tai-khoan-local>
DB_PASSWORD=<mat-khau-local>

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
git diff --check
git status --short
```

Baseline hiện tại:

- Route list: pass, 44 route.
- Build: pass.
- Composer metadata: hợp lệ.
- Test: đang fail 1 test do `/` trả 404.

Ngoài ra, `phpunit.xml` ép test dùng SQLite in-memory. Kể cả khi suite này xanh, nó vẫn không chứng minh stored procedure/trigger MariaDB hoạt động; cần một integration suite riêng trên database disposable.

Không sửa test để “xanh” bằng cách bỏ assertion; hãy chốt route home mong muốn rồi cập nhật route và test cùng nhau.

## Các blocker ưu tiên

1. Đồng bộ `.env.example`, DBMS, timezone và quy trình database/migrations.
2. Tạo master data/seed tối thiểu cho phòng ban, chức vụ, trạng thái, vai trò, quyền và loại phép.
3. Bổ sung hoặc thay thế bốn procedure còn thiếu:
   `sp_phong_ban_chi_tiet`, `sp_cham_cong_nhan_vien_phan_trang`,
   `sp_cham_cong_chi_tiet_phan_trang`, `sp_luong_tim_kiem_phan_trang`.
4. Sửa route/action/Blade, API naming và validation/error contract.
5. Khóa cả read/write contract lương, gồm unique `(ma_nv, ky_luong)`.
6. Giữ `config('nhanvien.enabled') === false` cho tới Task 18; hoàn tất auth/RBAC/Gates và kiểm tra quyền actor trước khi bật route nhân viên.
7. Viết feature/integration test trên database disposable cho các module đã có UI/API.
8. Xử lý tích hợp nhánh `frontend` như một workstream UI riêng; không merge tự động.

## Làm việc nhóm

- Mỗi task dùng branch ngắn hạn: `feature/*`, `fix/*`, `docs/*`, `test/*`.
- Không force-push lên branch dùng chung.
- Không tự động fetch/merge/rebase/push hoặc tạo upstream khi chưa được yêu cầu.
- Một module chỉ được gọi là hoàn thành khi route, validation, data contract, UI states, auth, test và browser acceptance phù hợp đều có bằng chứng.
- Pull request phải ghi phạm vi, cách kiểm tra, rủi ro còn lại và ảnh giao diện khi có thay đổi UI.

Xem checklist đầy đủ tại [docs/DEVELOPMENT_GUIDE.md](docs/DEVELOPMENT_GUIDE.md).
