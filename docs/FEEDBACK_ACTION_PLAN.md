# Kế hoạch xử lý feedback giao diện

> Cập nhật 2026-08-28. Phạm vi tài liệu này chỉ gồm các module Nhân viên, Phòng ban, Chức vụ và phần giao diện dùng chung được giao.

## Trạng thái theo từng mục

| Mục | Phạm vi xử lý | Trạng thái và bằng chứng |
| --- | --- | --- |
| 1. Font | Đổi font runtime trong `public/backend/css/style.css` sang Be Vietnam Pro, có fallback hệ thống | Đã triển khai; cần browser kiểm tra trực quan khi môi trường cho phép |
| 2. Sidebar | Đổi nhãn `Chức vụ` thành `Quản lý chức vụ`; giữ nguyên route và Gate | Đã triển khai; feature view kiểm tra được nhãn |
| 4. Chức vụ | Breadcrumb, lọc theo tên, chọn 5/10/20/50/100 dòng, pagination số first/previous/next/last, summary, action select theo Gate; delete có confirm | Đã triển khai bằng Query Builder, binding của framework và contract `paginate()`; targeted feature test pass |
| 5. Phòng ban | Tương tự Chức vụ, giữ transaction, row lock và mã lỗi `PB_*` | Đã triển khai bằng Query Builder; targeted feature test pass |
| 6. Danh sách Nhân viên | Giữ filter/scope/RBAC, summary/pagination thống nhất, action select theo quyền và guard tự xóa/phạm vi | Đã triển khai; targeted feature test và scope regression pass |
| 7. Chi tiết Nhân viên | Avatar lớn hơn, nhóm thông tin cá nhân, phụ cấp chức vụ, tài khoản; hiển thị hệ số phụ cấp chức vụ đã join và ngày nghỉ việc | Đã triển khai trên projection hiện có; không query thêm bảng ngoài phạm vi |
| 8. Chỉnh sửa Nhân viên | Giữ CRUD hiện có, breadcrumb/heading/form accessible và nhất quán | Đã rà soát, bổ sung liên kết trợ giúp form; không áp dụng pagination hoặc base salary lẫn từ module khác |

## Quyết định và giới hạn

- `all()` của Chức vụ và Phòng ban vẫn được giữ trong service/repository để không phá caller hiện có; trang danh sách dùng `paginate()` với request whitelist.
- Không tạo route xem chi tiết giả cho Chức vụ hoặc Phòng ban. Select chỉ đưa ra action có route và quyền thật.
- Action xóa trong select chỉ reset lựa chọn, hỏi xác nhận rồi mới submit form có CSRF; dependency và Gate vẫn chặn trước khi render action.
- Active SQL chỉ là `database/sql/tao_bang.sql` → `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql`. Không thêm bảng, cột, procedure, view hoặc trigger.
- Active schema không có `noi_sinh`; giao diện ghi rõ dữ liệu này deferred, không thêm cột. Hệ số lương hiện tại và loại hợp đồng cũng deferred vì thuộc module Lương/Hợp đồng.
- Không trả hoặc hiển thị `mat_khau`/hash. Scope Trưởng phòng và các Gate `NhanVien.*`, `PhongBan.*`, `ChucVu.*` không được nới rộng.

## Backlog ngoài ownership

Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC và API của đồng nghiệp không được sửa trong lát cắt này. Các vấn đề procedure/schema, bố cục hoặc quyền của những module đó cần task riêng và phải khóa contract active trước khi triển khai.

## Acceptance còn lại

Verification phiên triển khai: focused module/regression `53 tests, 481 assertions` và repository pagination `14 tests, 64 assertions` pass; full Laravel `294 tests, 2287 assertions` pass; frontend `21/21` pass; Vite `21 modules transformed`; route inventory `79`, Composer, PHP lint và `git diff --check` pass. MariaDB disposable: `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; đây không phải database live. Browser acceptance, font tải từ mạng thật, responsive trực quan và production/database live chưa được claim nếu chưa có bằng chứng tương ứng.
