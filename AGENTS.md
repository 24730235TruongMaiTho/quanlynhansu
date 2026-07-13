# AGENTS.md

Hướng dẫn này dành cho Codex và các agent AI khi làm việc trong repo đồ án môn học `quanlynhansu`.
Đọc file này trước khi sửa code. Nếu thông tin trong code khác với README, ưu tiên code hiện tại và ghi rõ giả định trong câu trả lời khi cần.

## Tổng Quan Dự Án

- Tên đồ án: website quản lý nhân sự.
- Stack chính: Laravel 12, PHP 8.2+, MySQL, Blade, Bootstrap 5, Vite, Tailwind CSS 4.
- Mục tiêu nghiệp vụ trong README: quản lý nhân viên, phòng ban, chức vụ, chấm công, lương, nghỉ phép, hợp đồng, vai trò/phân quyền, báo cáo, sao lưu/khôi phục.
- Repo hiện tại mới có một phần khung Laravel và module `phong_ban`; nhiều controller/view đang rỗng hoặc chưa hợp lệ.
- Database dump chính hiện nằm ở `quan_ly_nhan_su.session.sql`; migrations trong `database/migrations` vẫn là migrations mặc định của Laravel.

## Cấu Trúc Cần Biết

- `routes/web.php`: khai báo route web. Hiện mới có dashboard và phòng ban.
- `app/Http/Controllers/Backend`: controller backend. `PhongBanController` là controller có logic nhiều nhất.
- `app/Http/Controllers/Frontend`: hiện các file frontend controller đang rỗng.
- `app/Models`: hiện mới có `PhongBan`.
- `resources/views/backend`: Blade layout, dashboard và phòng ban.
- `resources/css/app.css`, `resources/js/app.js`: entrypoint Vite.
- `quan_ly_nhan_su.session.sql`: tạo database, bảng, view, function, trigger và stored procedure.

## Lệnh Hay Dùng

Chạy trong PowerShell tại root repo:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
php artisan test
```

Khi chạy local:

```powershell
php artisan serve
npm run dev
```

Nếu cần dùng database thật, cấu hình `.env` sang MySQL và import `quan_ly_nhan_su.session.sql` vào MySQL trước khi test các màn hình dùng stored procedure.

## Quy Ước Code

- Trả lời và ghi chú giải thích cho người dùng bằng tiếng Việt, ngắn gọn và rõ việc đã làm.
- Giữ định dạng theo `.editorconfig`: UTF-8, LF, 4 spaces, có newline cuối file.
- PHP namespace phải đúng chuẩn Laravel, ví dụ `App\Http\Controllers\Backend`, không dùng `app/Http/...`.
- Controller nên extend `App\Http\Controllers\Controller` hoặc base controller Laravel đúng chuẩn.
- Route name nên theo dạng `backend.<module>.<action>`, ví dụ `backend.phongban.index`.
- Blade view nên dùng lowercase path theo thư mục thật, ví dụ `backend.phongban.index`.
- Tên bảng/cột MySQL dùng chữ thường và `snake_case`, theo README.
- Không sửa file trong `vendor`, `storage/framework`, `bootstrap/cache` trừ khi có lý do rõ ràng.
- Không commit hoặc đưa thông tin nhạy cảm vào `.env`.

## Hướng Dẫn Làm Việc Với Database

- Code hiện tại đang gọi stored procedure bằng `DB::select()` và `DB::statement()`.
- Trước khi gọi một stored procedure, kiểm tra chữ ký tham số trong `quan_ly_nhan_su.session.sql`.
- Khi thêm model cho bảng trong SQL dump, đặt đúng tên bảng và khóa chính nếu khác mặc định Laravel. Ví dụ `phong_ban` có khóa chính `ma_pb`, không phải `id`.
- Nếu viết query trực tiếp, ưu tiên query builder/Eloquent nếu phù hợp; nếu đồ án đang yêu cầu dùng stored procedure thì giữ nhất quán với stored procedure.
- Cần cảnh giác với SQL dump: một số đoạn có cú pháp dễ bị sai trong MySQL như `NVARCHAR(MAX)`, `BACKUP DATABASE`, `RESTORE DATABASE`. Hãy verify bằng MySQL/XAMPP trước khi kết luận là chạy được.

## Hiện Trạng Lỗi/Thiếu Cần Lưu Ý

Những điểm này không nhất thiết phải sửa nếu không liên quan đến yêu cầu, nhưng phải biết để không đưa ra giả định sai:

- `app/Services/NhanVienService.php` chưa có `<?php` và namespace sai.
- `app/Http/Controllers/Backend/NhanVienController.php` chưa có `<?php`, namespace sai, view path sai.
- Nhiều controller backend/frontend đang rỗng: chức vụ, chấm công, lương, nghỉ phép, frontend lương, chi tiết nhân viên.
- `PhongBanController@index` gán biến `$danh_sach_phong_ban` nhưng view lại nhận `compact('phongban')`.
- `PhongBanController` có method `detroy` sai chính tả, trong khi route gọi `destroy`.
- Route update phòng ban đang map `PUT /phong-ban/{id}` vào `show`, nhưng controller có `update`.
- `resources/views/backend/phongban/index.blade.php` có cú pháp Blade sai: `@extends(...);`, `@section(...);`, `@foreach($pb in $phongban)`.
- `resources/views/backend/phongban/create.blade.php` mới có placeholder `@form`.
- Layout backend thiếu thẻ `<head>` rõ ràng và sidebar/main content mới là comment.

## Cách Tiếp Cận Khi Sửa Tính Năng

1. Đọc route, controller, model, view và stored procedure liên quan trước khi sửa.
2. Nếu sửa module đã có code, sửa đúng lượng nhỏ nhất để làm module chạy được.
3. Nếu thêm module mới, tạo đủ các phần tối thiểu: route, controller, model nếu cần, view Blade, validation, redirect/flash message, và test nếu khả thi.
4. Nếu làm CRUD dùng stored procedure, map đúng các action: danh sách, chi tiết, thêm, sửa, xóa.
5. Sau khi sửa PHP, chạy `php -l` với các file PHP đã sửa nếu chưa chạy được full test.
6. Sau khi sửa Blade/frontend, chạy `npm run build` nếu dependencies sẵn sàng.
7. Chạy `php artisan test` khi `vendor` và `.env` sẵn sàng; nếu không chạy được, báo rõ lý do.

## Nguyên Tắc Giao Tiếp Với Người Dùng

- Người dùng là sinh viên đang làm đồ án nhóm; giải thích thẳng vào vấn đề, không nói quá dài.
- Khi phát hiện lỗi sẵn có trong repo, ghi rõ file và lý do; không tự ý sửa hàng loạt lỗi ngoài phạm vi yêu cầu.
- Nếu cần lựa chọn kiến trúc, đề xuất phương án thực dụng phù hợp với trình độ đồ án môn học.
- Trước khi thay đổi nhiều file, tóm tắt ngắn gọn phạm vi sẽ sửa.
- Sau khi hoàn thành, nói rõ file đã tạo/sửa và lệnh đã dùng để kiểm tra.
