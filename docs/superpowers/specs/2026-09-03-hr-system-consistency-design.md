# Thiết kế chuẩn hóa toàn hệ thống quản lý nhân sự

**Ngày:** 2026-09-03
**Trạng thái:** Đã được người dùng ủy quyền tự quyết định và triển khai
**Phạm vi:** Toàn bộ web quản trị hiện hành, không thay shell/giao diện tổng thể

## 1. Mục tiêu

Giữ Bootstrap, màu sắc, sidebar và bố cục hiện tại; chuẩn hóa hành vi và hình thức của danh sách, bộ lọc, bảng, phân trang, ngày tháng, validation và các nút thao tác. Đồng thời hoàn thiện hồ sơ cá nhân, đổi mật khẩu, dashboard nghỉ phép, quy trình duyệt theo phòng ban, hợp đồng và phân quyền tài khoản.

Không tạo bảng mới. Nguồn schema vẫn là 15 bảng trong `database/sql/tao_bang.sql`. Có thể bổ sung một quyền mới vào catalog `quyen` và enum nhưng không đổi cấu trúc bảng.

## 2. Quyết định trải nghiệm dùng chung

### 2.1 Cột Thao tác

- Bỏ dropdown/select `Thao tác` ở mọi danh sách.
- `Xem`, `Sửa`, `Xóa`, `Reset mật khẩu`, `Phân quyền` hiển thị thành nút riêng trong cột thao tác.
- Điều hướng dùng thẻ `<a>` mang class button để giữ semantic và mở tab mới được.
- Mutation dùng `<button type="button|submit">`, có CSRF, Gate và confirm phù hợp.
- Desktop xếp cùng hàng; mobile cho phép wrap. Icon-only phải có accessible name.

### 2.2 Bộ lọc

- Mọi danh sách có filter đều dùng label hiển thị, không chỉ placeholder.
- Filter chỉ áp dụng khi bấm `Áp dụng bộ lọc`; không gửi request theo từng phím gõ hay thay đổi select.
- Luôn có `Đặt lại`; giữ whitelist query và `per_page` hợp lệ.
- Cùng thứ tự: tiêu chí → page size → `Áp dụng bộ lọc` → `Đặt lại`.

### 2.3 Phân trang

- Áp dụng cho Nhân viên, Phòng ban, Chức vụ, Hợp đồng, Chấm công, Nghỉ phép, Lương, Vai trò và Phân quyền tài khoản.
- Style dùng component Blade hiện có `backend.partials.pagination` và `pagination-summary`; danh sách chạy JS dùng cùng class/ARIA/copy.
- Page size: 10, 20, 50; mặc định 10 trừ màn dữ liệu theo ngày Chấm công có thể mặc định 31.
- Filter được giữ khi đổi trang; thay filter quay về trang 1.

### 2.4 Ngày tháng

- Mọi ngày hiển thị theo `dd/mm/yyyy`.
- Ô nhập ngày hiển thị `dd/mm/yyyy`, có label, placeholder, `inputmode="numeric"` và thông báo lỗi nhất quán.
- Helper JS `parseDisplayDate`, `formatDisplayDate`, `toIsoDate` xử lý nghiêm ngặt ngày hợp lệ; không chấp nhận ngày tràn như `31/02/2026`.
- FormRequest server normalize `dd/mm/yyyy` về `yyyy-mm-dd` trong `prepareForValidation`; DB/API tiếp tục dùng ISO để không đổi contract lưu trữ.

### 2.5 Validation và thông báo

- Mỗi field có label; lỗi field nằm ngay dưới field bằng `.invalid-feedback` và `aria-describedby`.
- Form có vùng lỗi tổng quát `role="alert"`; success dùng `role="status"`/`aria-live="polite"`.
- Copy chuẩn: `Vui lòng nhập {label}.`, `{label} không đúng định dạng.`, `{label} đã tồn tại.`, và thông báo nghiệp vụ cụ thể.
- Không trả raw exception, SQLSTATE, hash hoặc dữ liệu nhạy cảm.

### 2.6 Bảng

- Cùng class `table table-hover align-middle mb-0`, `thead.table-light`, caption ẩn, scope header và container responsive.
- Loading, empty, error có cùng cấu trúc và số cột đúng.
- Cột mã hiển thị text/bold thông thường; không dùng badge/background cho mã phòng ban và số nhân viên.

## 3. Sidebar và tài khoản góc phải

- Chức vụ trở thành group có submenu `Danh sách chức vụ`.
- Nghỉ phép có submenu `Tạo nghỉ phép`, `Danh sách nghỉ phép`, và `Duyệt nghỉ phép`; mục duyệt chỉ hiện cho actor có quyền phù hợp và nghiệp vụ Trưởng phòng.
- Hệ số lương trỏ vào section hệ số của `/luong`, mở section tương ứng thay vì `href="#"`.
- Topbar lấy tên vai trò thật của actor, không hard-code `Quản trị viên`.
- Dropdown tài khoản có `Hồ sơ cá nhân`, `Đổi mật khẩu`, `Đăng xuất`.
- Hồ sơ cá nhân cho sửa mọi thông tin cá nhân trừ mã nhân viên, phòng ban, chức vụ, vai trò và trạng thái. Email/CCCD vẫn unique.
- Đổi mật khẩu yêu cầu mật khẩu hiện tại, mật khẩu mới và xác nhận; hash server-side, invalidate session khác nếu hạ tầng hỗ trợ mà không làm mất session hiện tại.

## 4. Dashboard và nghỉ phép

- `#statsCards` thêm thẻ `Nghỉ phép chờ duyệt` chỉ cho Trưởng phòng.
- Số liệu là tất cả đơn trạng thái chờ của nhân viên cùng `ma_pb` với actor, không gồm phòng khác.
- Click thẻ mở route `backend.nghiphep.duyet-nghi-phep`.
- Trang duyệt chỉ cho Trưởng phòng có quyền `NghiPhep.Update`; danh sách chờ bị scope theo phòng ban ở query backend, không tin filter client.
- Actor không phải Trưởng phòng nhận 403; target khác phòng không xuất hiện và mutation trả 404/403 an toàn.
- Sau duyệt/từ chối, badge/thẻ và danh sách phản ánh lại số lượng.

## 5. Nhân viên, Phòng ban và Chức vụ

- Bốn field địa chỉ `dia_chi_cu_the`, `phuong_xa`, `quan_huyen`, `tinh_thanh` là nullable ở create/update frontend và FormRequest; không thay schema.
- Bỏ border của khối `Bước 1: Hồ sơ liên hệ`, giữ heading và khoảng cách.
- Dialog xác nhận xóa dùng modal/dialog căn giữa viewport, focus trap, Escape/cancel và khôi phục focus.
- Phòng ban bỏ background/badge ở mã phòng ban và số nhân viên.
- Chức vụ thêm submenu và áp dụng toàn bộ chuẩn list/action/filter/table/pagination.

## 6. Vai trò và Phân quyền

- Màn quyền theo vai trò bỏ mã kỹ thuật màu xám như `NhanVien.Read`; giữ nhãn người dùng `Đọc`, `Thêm`, `Sửa`, `Xóa`, `Reset mật khẩu`.
- Bổ sung enum/catalog permission `NhanVien.ResetPassword`; mã số phải được xác định từ catalog active hiện tại, không đoán từ dump lịch sử.
- Reset mật khẩu chỉ xuất hiện khi actor có quyền; target guard không cho tự reset hoặc reset tài khoản đặc quyền ngoài policy.
- Mật khẩu reset là `nhom3@{năm hiện tại theo config('app.timezone')}`, hash bằng Laravel, không trả hash. Thông báo chỉ nêu quy ước mật khẩu mặc định.
- Đổi tên màn `Gán vai trò tài khoản` thành `Phân Quyền`.
- Danh sách tài khoản có filter + pagination chung.
- Người dùng có thể thay vai trò nhiều dòng rồi bấm một nút `Lưu phân quyền`. Backend nhận map `assignments[ma_nv]=ma_vt`, validate toàn bộ và ghi trong một transaction; một lỗi làm rollback toàn bộ.

## 7. Hợp đồng

- Form thêm/sửa dùng date input chuẩn `dd/mm/yyyy`, validation label chuẩn và lương cơ bản format dấu chấm theo `vi-VN` trong khi nhập/hiển thị; payload server là số nguyên.
- Loại `Hợp đồng lao động không xác định thời hạn`: tự xóa ngày hết hạn, khóa field và lưu `NULL`.
- Bốn loại còn lại: người dùng tự nhập ngày hết hạn; bắt buộc và phải sau ngày ký (`after`, không phải `after_or_equal`).
- Backend resolve loại hợp đồng từ DB theo `ma_lhd`; không tin tên/type do client gửi.
- Create và update dùng cùng rule, UI chỉ hỗ trợ chứ backend là nguồn quyết định.

## 8. Phạm vi chuẩn hóa từng module

- Dashboard: thẻ chờ duyệt và link.
- Nhân viên, Phòng ban, Chức vụ: action buttons, filter, pagination, table, date/validation liên quan.
- Hợp đồng: toàn bộ list/form và business rule ngày/lương.
- Chấm công: filter có nút apply, pagination/table/action buttons, ngày; giữ kỳ tháng/năm.
- Nghỉ phép: create/list/approve, submenu, filter/pagination/table/date và department scope.
- Lương/Hệ số lương: action buttons, filter/pagination/table/date, submenu section; sửa route/CSRF cần thiết để flow hiện hữu hoạt động.
- Vai trò/Phân quyền: action buttons, bỏ mã kỹ thuật, bulk save/pagination/reset password.

## 9. Kiểm thử và điều kiện hoàn thành

Mỗi phase phải có RED trước sửa và GREEN sau sửa. Gate cuối gồm:

1. `php artisan route:list --except-vendor`.
2. `php artisan test` không còn failure; không xóa assertion để làm xanh.
3. `npm run test:frontend` không còn failure.
4. `npm run build`.
5. `composer validate --no-check-publish`, PHP lint file sửa, `git diff --check`.
6. Browser desktop 1440, tablet 768, mobile 375 cho màn đại diện; console và network không có lỗi ngoài request validation được chủ động tạo.
7. E2E trên DB local chỉ khi user đã cho phép, dùng record QA exact-key và cleanup theo `AGENTS.md`.
8. Đăng nhập tối thiểu bằng Quản trị, Nhân sự, Trưởng phòng và Nhân viên; xác minh menu, route, 403/404, department scope, profile/password và action visibility đúng vai trò.
9. Không gọi hoàn thành nếu còn yêu cầu chưa có bằng chứng runtime hoặc test bao phủ.

## 10. Thứ tự triển khai

1. Foundation UI/date/validation.
2. Sidebar/topbar/profile/password.
3. Dashboard + nghỉ phép.
4. Nhân viên/Phòng ban/Chức vụ/Hợp đồng.
5. Vai trò/quyền reset/bulk assignment.
6. Chấm công/Lương/Hệ số lương áp chuẩn và đóng các lỗi cản E2E.
7. Full regression, browser đa vai trò, cleanup và tài liệu.
