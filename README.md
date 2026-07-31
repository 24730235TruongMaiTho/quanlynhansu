# Website quản lý nhân sự

Đây là đồ án nhóm xây dựng một website quản lý nhân sự và được dùng làm sản phẩm nộp cho hai môn học:

1. **Lập trình Web Application**: tập trung vào kiến trúc Laravel, xử lý nghiệp vụ, xác thực, CRUD, MySQL và kiểm thử.
2. **Thiết kế giao diện người dùng**: tập trung vào luồng sử dụng, bố cục, tính nhất quán, responsive, accessibility và phản hồi trạng thái trên giao diện.

Sản phẩm hướng tới một hệ thống quản trị nội bộ, hỗ trợ quản lý hồ sơ nhân viên và các nghiệp vụ nhân sự cơ bản trong cùng một giao diện web.

## Mục tiêu đồ án

### Môn Lập trình Web Application

- Tổ chức ứng dụng theo Laravel MVC.
- Xây dựng đăng nhập, đăng xuất, session và phân quyền theo vai trò.
- Thực hiện CRUD có validation cho các module nghiệp vụ.
- Kết nối MySQL và sử dụng stored procedure khi phù hợp với thiết kế database hiện có.
- Xử lý lỗi, thông báo kết quả và bảo vệ các route quản trị.
- Có test tối thiểu cho các luồng nghiệp vụ quan trọng.

### Môn Thiết kế giao diện người dùng

- Xây dựng hệ thống layout, màu sắc, typography và component nhất quán.
- Thiết kế rõ luồng đăng nhập, dashboard, danh sách, form và trang chi tiết.
- Hỗ trợ desktop, tablet và mobile.
- Bảo đảm form dễ hiểu, có trạng thái loading, empty, success và error.
- Chú ý khả năng truy cập: semantic HTML, label, keyboard focus và độ tương phản.
- Chuẩn bị tài liệu thiết kế như sitemap, user flow, wireframe/mockup và slide thuyết trình.

## Phạm vi chức năng dự kiến

| Module | Mục tiêu chính |
| --- | --- |
| Xác thực và phân quyền | Đăng nhập, đăng xuất, bảo vệ route, vai trò và quyền |
| Nhân viên | Hồ sơ nhân viên, tìm kiếm, thêm, sửa, xem chi tiết, xóa |
| Phòng ban | Danh sách và CRUD phòng ban |
| Chức vụ | Danh sách và CRUD chức vụ, hệ số/phụ cấp liên quan |
| Hợp đồng | Quản lý loại hợp đồng, thời hạn và cảnh báo hết hạn |
| Nghỉ phép | Tạo đơn, duyệt đơn và theo dõi trạng thái |
| Chấm công | Theo dõi ngày công, đi muộn, về sớm, import/export khi khả thi |
| Lương | Hệ số lương, kỳ lương, tính lương và lịch sử lương |
| Báo cáo | Thống kê nhân sự, chấm công, nghỉ phép và lương |
| Sao lưu/khôi phục | Thực hiện bằng công cụ MySQL hoặc quy trình server an toàn |

## Hiện trạng repository

> Cập nhật theo code ngày 2026-07-15. Xem chi tiết kỹ thuật và lỗi đã biết tại [`docs/CODEX_NEXT_HANDOFF.md`](docs/CODEX_NEXT_HANDOFF.md).

| Hạng mục | Trạng thái |
| --- | --- |
| Trang chủ | Đã có landing page responsive ở mức demo |
| Dashboard | Có route/controller/view, giao diện mới là placeholder |
| Phòng ban | Có CRUD sơ khai nhưng route, controller, Blade và stored procedure chưa đồng bộ |
| Các module còn lại | Phần lớn controller/view/model chưa có hoặc còn rỗng |
| Đăng nhập/phân quyền | Chưa triển khai; đang tồn tại hai hướng dữ liệu `users` và `nhan_vien` chưa được thống nhất |
| Database nghiệp vụ | Có SQL dump gồm bảng, view, function, trigger và stored procedure |
| Kiểm thử | Chỉ có 2 test mẫu mặc định, chưa bao phủ nghiệp vụ |
| Frontend build | Vite build được; giao diện frontend và backend chưa dùng chung một design system hoàn chỉnh |

Không xem danh sách chức năng dự kiến là chức năng đã hoàn thành. Trước khi nhận task, luôn kiểm tra route, controller, model, view và SQL tương ứng.

## Công nghệ đang sử dụng

| Thành phần | Công nghệ |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Giao diện server-rendered | Blade |
| Database | MySQL; schema nghiệp vụ trong `quan_ly_nhan_su.session.sql` |
| Frontend tooling | Vite 7, JavaScript, Axios |
| CSS/UI | CSS tùy chỉnh, Tailwind CSS 4; layout backend hiện còn dùng Bootstrap 5 CDN |
| Test | PHPUnit thông qua `php artisan test` |
| Quản lý source | Git và GitHub |

Thư viện biểu đồ chưa được chốt. README cũ đề cập Recharts, nhưng repository chưa dùng React nên không thể sử dụng Recharts trực tiếp nếu chưa bổ sung React. Khi làm báo cáo, nhóm cần chọn giải pháp phù hợp với Blade hoặc thống nhất việc tích hợp React trước.

## Kiến trúc tổng quát

```text
Trình duyệt
    ↓
routes/web.php
    ↓
Controller Laravel
    ↓
Validation / Model / Query Builder / Stored Procedure
    ↓
MySQL

Controller Laravel
    ↓
Blade view + Vite assets
    ↓
HTML/CSS/JavaScript trả về trình duyệt
```

Các thư mục quan trọng:

```text
app/
├── Http/Controllers/Backend/    # Chức năng quản trị
├── Http/Controllers/Frontend/   # Trang dành cho người dùng
├── Models/                      # Model ánh xạ bảng MySQL
└── Services/                    # Nghiệp vụ dùng lại khi cần
database/migrations/             # Hiện chủ yếu là migration mặc định Laravel
resources/
├── css/                         # CSS dùng chung và theo màn hình
├── js/                          # Entrypoint Vite và JavaScript
└── views/                       # Blade frontend/backend
routes/web.php                   # Route web
tests/                           # Unit và feature test
quan_ly_nhan_su.session.sql      # Nguồn schema nghiệp vụ hiện tại
AGENTS.md                        # Quy tắc bắt buộc cho Codex/AI agent
.codex/                          # Tài sản quản lý codebase cho Codex
docs/CODEX_NEXT_HANDOFF.md       # Snapshot và thứ tự công việc tiếp theo
```

## Yêu cầu môi trường

- PHP 8.2 trở lên và Composer.
- Node.js, npm.
- MySQL 8 hoặc MySQL đi kèm XAMPP tương thích với SQL dump.
- Git.
- Có thể dùng IntelliJ IDEA, PhpStorm, Visual Studio Code hoặc công cụ tương đương.

## Cài đặt local trên Windows/PowerShell

```powershell
git clone <repository-url>
Set-Location quanlynhansu

composer install
Copy-Item .env.example .env
php artisan key:generate

npm install
npm run build
```

Sau đó cấu hình `.env` cho MySQL, tối thiểu gồm `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` và `DB_PASSWORD`.

### Chuẩn bị database

File `quan_ly_nhan_su.session.sql` hiện có lệnh `DROP DATABASE IF EXISTS quan_ly_nhan_su`. Chỉ import vào database local/disposable và không chạy trên database có dữ liệu cần giữ.

Thứ tự khuyến nghị:

1. Khởi động MySQL/XAMPP.
2. Kiểm tra SQL dump và import vào môi trường local an toàn bằng phpMyAdmin, MySQL Workbench hoặc MySQL CLI.
3. Cấu hình `.env` trỏ tới database vừa tạo.
4. Chạy `php artisan migrate` sau khi import dump nếu cần bổ sung các bảng hạ tầng Laravel như session, cache và jobs.

Không commit `.env` hoặc thông tin đăng nhập database lên GitHub.

## Chạy dự án

Cách đơn giản với hai terminal:

```powershell
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

Hoặc chạy quy trình phát triển đã khai báo trong Composer:

```powershell
composer run dev
```

## Lệnh kiểm tra thường dùng

| Lệnh | Mục đích |
| --- | --- |
| `php artisan route:list --except-vendor` | Kiểm tra route ứng dụng |
| `php artisan test` | Chạy test Laravel |
| `php -l <file.php>` | Kiểm tra cú pháp một file PHP |
| `npm run build` | Kiểm tra bundle CSS/JavaScript |
| `git diff --check` | Tìm lỗi whitespace trong thay đổi Git |
| `git status --short` | Kiểm tra phạm vi file đã thay đổi |

## Quy trình làm việc nhóm với GitHub

Mỗi thành viên làm việc trên một branch ngắn hạn, bắt đầu từ branch chung mà nhóm đã thống nhất (ví dụ `main`). Không đưa `.env`, `vendor`, `node_modules` hoặc `public/build` lên repository.

```text
main (ví dụ branch chung)
├── feature/phong-ban-crud
├── feature/dang-nhap
├── feature/giao-dien-dashboard
├── fix/phong-ban-update-route
└── docs/cap-nhat-readme
```

Quy trình cho một task:

1. Đồng bộ branch gốc và kiểm tra `git status` trước khi sửa.
2. Tạo branch theo loại công việc: `feature/`, `fix/`, `docs/`, `test/` hoặc `refactor/`.
3. Chỉ sửa đúng phạm vi task và commit theo từng thay đổi logic nhỏ.
4. Commit message nên rõ mục đích, ví dụ `feat: add department creation form`.
5. Chạy test/build phù hợp trước khi push.
6. Tạo pull request, mô tả file đã sửa, cách kiểm tra và ảnh giao diện nếu có.
7. Thành viên khác review trước khi merge vào branch chung.

Không dùng force push lên branch dùng chung và không tự ý ghi đè thay đổi chưa commit của bạn cùng nhóm.

## Tiêu chí hoàn thành một module

Một module chỉ được đánh dấu hoàn thành khi có đủ phần liên quan:

- Route có tên nhất quán.
- Controller đúng namespace và action.
- Validation phía server.
- Model/query/stored procedure khớp schema MySQL.
- Blade view có các trạng thái danh sách, form, lỗi và thông báo thành công.
- Route quản trị được bảo vệ khi hệ thống auth đã có.
- Feature test hoặc ít nhất kiểm tra thủ công có ghi lại kết quả.
- Giao diện responsive và có focus/label/contrast phù hợp.
- `php artisan test` và `npm run build` không lỗi.

## Thứ tự phát triển đề xuất

1. Chuẩn hóa môi trường MySQL và kiểm tra SQL dump trên database local an toàn.
2. Hoàn thiện trọn vẹn CRUD phòng ban làm module mẫu.
3. Hoàn thiện layout backend và chốt design system dùng chung.
4. Chốt nguồn tài khoản, triển khai đăng nhập và phân quyền.
5. Làm các danh mục: chức vụ, trạng thái làm việc, vai trò và quyền.
6. Làm nhân viên, hợp đồng và lịch sử hệ số lương.
7. Làm nghỉ phép, chấm công, lương và báo cáo.
8. Hoàn thiện test, tài liệu thiết kế, báo cáo và slide.
9. Chỉ làm import/export/backup/restore sau khi các luồng chính ổn định.

## Làm việc với Codex

- `AGENTS.md` là hướng dẫn gốc bắt buộc đọc trước khi sửa code.
- `.codex/USAGE.md` giải thích cách dùng instruction, prompt, agent role và skill riêng của dự án.
- `docs/CODEX_NEXT_HANDOFF.md` lưu trạng thái kỹ thuật gần nhất và thứ tự tiếp tục.
- `.agents/skills/` chứa các workflow kỹ thuật dùng chung; `.codex/` chứa ngữ cảnh và workflow riêng cho đồ án này.

Khi giao task cho Codex, nên nêu rõ module, kết quả mong muốn và file/màn hình liên quan. Codex cần đọc code hiện tại trước, giữ thay đổi nhỏ, báo rõ lỗi sẵn có và chạy kiểm tra phù hợp sau khi sửa.

## Quy ước chính

- PHP namespace theo chuẩn `App\\...` và PSR-4.
- Route name theo dạng `backend.<module>.<action>`.
- Blade path viết chữ thường theo đúng thư mục thật.
- Tên bảng/cột MySQL viết chữ thường và `snake_case`.
- File dùng UTF-8, LF, 4 spaces và có newline cuối file.
- Ưu tiên code hiện tại khi tài liệu mâu thuẫn, sau đó cập nhật lại tài liệu.
