# Thiết kế chuẩn hóa giao diện backend

**Ngày:** 2026-09-04
**Trạng thái:** Phương án 1 đã được người dùng chọn; chờ duyệt đặc tả trước khi triển khai
**Phạm vi:** Toàn bộ giao diện backend hiện hành; không đổi nghiệp vụ, route, quyền hoặc cấu trúc database

## 1. Mục tiêu

Giữ nguyên Bootstrap, màu sắc và bố cục tổng thể hiện tại, đồng thời chuẩn hóa bốn điểm dùng chung:

1. Mọi nút có biểu tượng nhất quán và vẫn giữ nhãn chữ dễ hiểu.
2. Bộ lọc của các module gọn, thẳng hàng, không kéo dài quá mức và đáp ứng tốt trên màn hình nhỏ.
3. Sidebar giữ submenu đang chọn và vị trí cuộn khi điều hướng sang trang mới.
4. Danh sách Vai trò và các trang danh sách khác dùng cùng cấu trúc `page-header`, lấy khối `div.left` hiện tại làm mẫu.

Thay đổi này chỉ chuẩn hóa lớp trình bày và hành vi điều hướng. Không thay đổi điều kiện lọc, dữ liệu trả về, quy tắc phân quyền hoặc hành vi của các thao tác CRUD.

## 2. Quy ước biểu tượng cho nút

Sử dụng Bootstrap Icons đã có trong hệ thống. Nút thông thường luôn hiển thị **biểu tượng + nhãn chữ**; không chuyển hàng loạt thành nút chỉ có biểu tượng vì sẽ làm giảm khả năng nhận biết và accessibility.

| Hành động | Biểu tượng chuẩn |
| --- | --- |
| Thêm/Tạo mới | `bi-plus-circle` |
| Xem/Chi tiết | `bi-eye` |
| Sửa/Cập nhật | `bi-pencil-square` |
| Xóa | `bi-trash` |
| Lưu | `bi-floppy` |
| Áp dụng bộ lọc | `bi-funnel` |
| Đặt lại bộ lọc | `bi-arrow-counterclockwise` |
| Làm mới | `bi-arrow-clockwise` |
| Quay lại | `bi-arrow-left` |
| Hủy/Đóng | `bi-x-circle` |
| Duyệt | `bi-check-circle` |
| Từ chối | `bi-x-circle` |
| Xuất dữ liệu | `bi-download` |
| Nhập dữ liệu | `bi-upload` |
| Phân quyền | `bi-shield-lock` |
| Reset mật khẩu | `bi-key` |

Biểu tượng dùng `aria-hidden="true"`; accessible name đến từ nhãn chữ hoặc `aria-label` hiện có. Màu nút tiếp tục phản ánh ý nghĩa hiện hành: primary cho hành động chính, danger cho xóa/từ chối và outline cho hành động phụ.

`backend.partials.action-buttons` và `backend.partials.filter-actions` là nguồn chuẩn cho nút thao tác trên bảng và nút bộ lọc. Các nút riêng ở page header, form, modal và màn duyệt được cập nhật theo cùng bảng ánh xạ.

## 3. Page header dùng chung

Tạo một thành phần Blade dùng chung cho các trang backend. Cấu trúc hiển thị:

- `div.page-header` bao ngoài.
- `div.left` chứa breadcrumb, tiêu đề trang và mô tả ngắn.
- Vùng hành động bên phải chứa nút chính như Thêm mới, Làm mới hoặc Quay lại.
- Nếu không có hành động, khối `left` vẫn giữ đúng khoảng cách và chiều rộng.

Trang danh sách Vai trò được chuyển về đúng cấu trúc này: phần tiêu đề và mô tả nằm bên trái, nút `Thêm vai trò` nằm bên phải; bộ lọc nằm trong card riêng bên dưới như các danh sách khác.

Áp dụng cho các trang danh sách chính: Tổng quan, Nhân viên, Phòng ban, Chức vụ, Hợp đồng, Chấm công, Nghỉ phép, Duyệt nghỉ phép, Lương, Hệ số lương, Vai trò và Phân quyền tài khoản. Nội dung và quyền hiển thị nút của từng module được giữ nguyên.

## 4. Bộ lọc dùng chung

Mỗi bộ lọc dùng cùng card, header, label, chiều cao control và nhóm nút. Không thay đổi tên query parameter hoặc logic backend.

### 4.1 Kích thước và bố trí

- Trường tìm kiếm rộng có giới hạn hợp lý, không chiếm toàn bộ hàng khi chỉ có ít điều kiện.
- Select trạng thái, phòng ban, chức vụ, tháng/năm và số dòng mỗi trang dùng cỡ gọn hơn trường tìm kiếm.
- Các trường được xếp từ trái sang phải theo thứ tự nghiệp vụ; nhóm `Áp dụng bộ lọc` và `Đặt lại` nằm cuối và căn theo đáy control.
- Không dùng các cột Bootstrap quá rộng như `col-lg-7` cho một ô tìm kiếm đơn lẻ.
- Desktop: grid gọn, ưu tiên độ rộng nội dung và cho phép xuống hàng khi không đủ chỗ.
- Tablet: hai cột khi có thể.
- Mobile: một cột; hai nút lọc có thể wrap nhưng không tràn viewport.

### 4.2 Hành vi

- Chỉ gửi bộ lọc khi người dùng bấm `Áp dụng bộ lọc`.
- `Đặt lại` quay về URL danh sách canonical.
- Thay filter vẫn quay về trang 1 theo contract hiện có.
- Loading, validation và disabled-on-submit hiện có không bị thay đổi.

Các module được phép có số trường khác nhau, nhưng dùng cùng spacing, label, control height, breakpoint và vị trí nhóm hành động.

## 5. Ghi nhớ trạng thái sidebar

Sidebar lưu hai trạng thái trong `sessionStorage`:

1. Định danh submenu đang mở.
2. `scrollTop` của vùng menu.

Mỗi nhóm submenu có một `data-sidebar-group` ổn định. Khi người dùng mở nhóm hoặc chọn liên kết, script lưu nhóm hiện tại và vị trí cuộn trước khi trình duyệt điều hướng.

Khi trang mới tải:

- Route hiện tại được đánh dấu bằng `aria-current="page"`.
- Nhóm chứa route hiện tại được ưu tiên mở; nếu không xác định được route active thì khôi phục nhóm đã lưu.
- Khôi phục `scrollTop` sau khi trạng thái submenu đã được áp dụng để tránh nhảy vị trí.
- Đồng bộ class mở, hướng mũi tên và `aria-expanded`.
- Giữ nguyên quy tắc accordion hiện tại: tại một thời điểm chỉ một nhóm cùng cấp được mở.
- Mobile drawer vẫn đóng sau điều hướng; chỉ trạng thái submenu và vị trí cuộn được giữ lại.

Dùng `sessionStorage` thay vì `localStorage` để tránh giữ trạng thái cũ qua nhiều phiên hoặc vô tình dùng lại giữa các tài khoản đăng nhập khác nhau.

## 6. Phạm vi file dự kiến

- Layout/style dùng chung trong `resources/views/backend/layouts/app.blade.php` hoặc asset hiện hành tương ứng.
- Sidebar trong `resources/views/backend/layouts/sidebar.blade.php` và `public/backend/js/script.js`.
- Partial/component dùng chung cho page header, action buttons và filter actions.
- Các trang backend có page header, filter hoặc nút chưa theo chuẩn.
- Test contract Blade và frontend JavaScript tương ứng.

Không port hoặc merge nguyên shell từ branch lịch sử; thay đổi bám đúng layout `backend.layouts.app` đang chạy.

## 7. Tiêu chí nghiệm thu

### 7.1 Giao diện

- Không còn nút người dùng nhìn thấy mà thiếu biểu tượng trong phạm vi backend, trừ control kỹ thuật không có nhãn hành động.
- Cùng một hành động dùng cùng một biểu tượng ở mọi module.
- Bộ lọc không có trường kéo dài bất hợp lý trên desktop và không tràn ở 375 px.
- Các control và nút trong bộ lọc thẳng hàng, có khoảng cách đồng nhất.
- Danh sách Vai trò có page header, filter card và table layout giống các module danh sách khác.
- Các page header dùng cùng cấu trúc `left` và vùng hành động phải.

### 7.2 Sidebar

- Mở một submenu, cuộn sidebar, chọn trang con: trang mới vẫn mở đúng submenu và trở lại gần đúng vị trí vừa chọn.
- Đường dẫn active luôn được đánh dấu; route active thắng trạng thái cũ trong session.
- Mũi tên và `aria-expanded` phản ánh đúng trạng thái thực.
- Sidebar mobile không tự mở drawer sau điều hướng.

### 7.3 Hồi quy

- Route, request, quyền và thao tác CRUD không đổi hành vi.
- Test Laravel và frontend hiện có tiếp tục pass.
- Bổ sung test cho icon mapping, cấu trúc page header/filter và sidebar persistence.
- Chạy `php artisan test`, `npm run test:frontend`, `npm run build`, PHP lint file sửa, `composer validate --no-check-publish` và `git diff --check`.
- Nghiệm thu trình duyệt ở desktop 1440 px, tablet 768 px và mobile 375 px trên các trang đại diện; kiểm tra console và network.

## 8. Ngoài phạm vi

- Không đổi database hoặc dữ liệu hiện tại.
- Không đổi business logic, API contract, route name hay permission catalog.
- Không thay Bootstrap hoặc thiết kế lại màu sắc, typography, sidebar/topbar.
- Không commit, merge hoặc push nếu người dùng chưa yêu cầu riêng.
