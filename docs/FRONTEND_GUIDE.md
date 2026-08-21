# Hướng dẫn frontend

## Hai trạng thái phải phân biệt

### Runtime trên `main`

Layout hiện tại là `resources/views/backend/layouts/app.blade.php` và child page dùng:

```blade
@extends('backend.layouts.app')
```

Shell gồm Sidebar + Topbar + content, không có Footer. Navigation hard-code; asset chính dùng Bootstrap/CDN và `public/backend/*`. Lương, chấm công, nghỉ phép push thêm Vite entry riêng.

Layout hiện thiếu `<head>` semantic hợp lệ; meta/link nằm trực tiếp dưới `<html>`.

### Shell mục tiêu trên branch `frontend`

Local branch `frontend` tại `940e7cc` có shell đã được duyệt:

- Header + Sidebar + Main + Footer.
- Không có global navbar.
- Desktop sidebar mở rộng, thu gọn thành icon rail.
- Accordion cùng cấp, tự mở group active.
- Flyout khi rail thu gọn.
- Mobile drawer độc lập.
- Landing/admin asset runtime tách biệt.

Shell này dùng layout `backend.layout.app` (không có `s`) và chưa được tích hợp vào `main`. Xem [ADR-001](decisions/ADR-001-admin-shell.md).

Contract và automated render/controller/build tests đã được xác minh trên branch.
Task20 đã có browser evidence hẹp cho employee runtime: responsive
`320/375/768/1024/1440`, clipping, console và các flow auth/CRUD chính đã pass;
avatar upload/replacement vẫn **blocked/unverified** vì browser extension không
cho file URL access. Shell mục tiêu trên branch `frontend` vẫn là workstream
riêng và chưa được coi là đã merge.

Không copy ngẫu nhiên từng file giữa hai branch: layout path, route name, Vite inputs, page contract và tests khác nhau.

## Hiện trạng page trên branch tính năng hiện tại

| Page | Hiện trạng |
| --- | --- |
| Dashboard | Render được, nội dung tối thiểu |
| Lương | UI + JavaScript CRUD/hệ số đã nối một phần; danh sách lương bị DB block; JS xóa hệ số không có route DELETE; export không có binding và đối soát chỉ enable/disable, chưa có click handler |
| Chấm công | UI + JavaScript tải/cập nhật đã nối; import/export mới phát event |
| Nghỉ phép | UI + JavaScript CRUD/duyệt đã nối; chưa có test workflow |
| Nhân viên | List/filter/pagination, detail/edit, delete-or-terminate/reset, permission-aware actions và trạng thái empty/success/error/submitting đã wired; verified hẹp bằng automated + browser acceptance |
| Thêm nhân viên | Wizard POST có CSRF/validation, lưu hồ sơ + địa chỉ + account/avatar qua service/repository; không nhận role từ client |
| Phòng ban | Blade/layout bị lỗi |
| Landing | View bị thiếu; `/` không có route |

`docs/noi_dung_3` chỉ là prototype HTML tham khảo, không phải Vite/Blade runtime.

## Asset trên main

`vite.config.js` build bảy entry JavaScript cho:

- Lương chính.
- Form lương.
- Hệ số lương.
- Form hệ số lương.
- Chấm công.
- Nghỉ phép.
- Nhân viên.

Các điểm cần chuẩn hóa:

- Layout admin vẫn dựa nhiều vào CDN và asset `public/backend`.
- Landing/shared `app.css` và `app.js` không có trong Vite input.
- `resources/js/app.js` import `./bootstrap`, nhưng file này không tồn tại.
- Sidebar/navigation vẫn hard-code và chưa dùng chung route-active contract của shell mục tiêu.
- Route name lương/chấm công/nghỉ phép bị lặp `backend.backend.*`.

## Page contract khi tích hợp shell mục tiêu

Khi được phép port shell từ `frontend`, child page mục tiêu:

```blade
@extends('backend.layout.app')

@section('title', 'Tên trang')
@section('page-width', 'fluid')

@section('page-header')
    <x-backend.page-header
        title="Tên trang"
        description="Mô tả ngắn"
    />
@endsection

@section('content')
    {{-- Chỉ nội dung riêng của page --}}
@endsection
```

Không lặp `<!DOCTYPE>`, `html`, `head`, Header, Sidebar, Footer hoặc outer `main`.

- Dùng `fluid` cho dashboard/bảng.
- Dùng `contained` cho form/detail.
- Dùng shared empty state cho module chưa có data contract.
- Chỉ thêm navigation item sau khi named route tồn tại.

Contract này là mục tiêu đã duyệt, chưa phải layout/page contract hiện có trên main.

## Kế hoạch tích hợp an toàn

1. Tạo branch tích hợp từ `main` khi người dùng yêu cầu.
2. Lập diff `main...frontend`; không merge toàn bộ theo quán tính.
3. Chốt và chuẩn hóa named-route contract trước hoặc trong cùng atomic change với navigation; giữ URL `/admin/*` nếu chưa có ADR đổi URL.
4. Port shell, components, navigation config và test shell sau khi mọi route mà config tham chiếu đã tồn tại.
5. Chuyển từng page business từ `backend.layouts.app` sang contract mới.
6. Hợp nhất Vite input để landing/admin/module không tải asset chéo.
7. Giữ API clients mới trên `main`, bổ sung route/action còn thiếu và khóa URL bằng test.
8. Chạy Laravel test, frontend test, build và browser matrix.

## UI state bắt buộc

Mỗi màn dữ liệu phải có:

- Loading.
- Empty.
- Success.
- Validation error.
- Server/network error.
- Disabled/submitting.
- Confirm cho action phá hủy.

Không hiển thị fixture/hard-code như dữ liệu thật. Nếu dùng mock cho demo, gắn nhãn rõ.

## Accessibility

Checklist tối thiểu:

- [ ] Mỗi input có label.
- [ ] Icon-only button có accessible name.
- [ ] Menu/drawer cập nhật `aria-expanded`.
- [ ] Focus được đưa vào/khôi phục khi mở đóng drawer/dialog.
- [ ] Modal/drawer có keyboard flow và Escape phù hợp.
- [ ] Hàng/cột tương tác không chỉ dựa vào click chuột.
- [ ] Dynamic HTML được escape.
- [ ] Toast/error dùng `aria-live` hợp lý.
- [ ] Contrast và focus ring kiểm tra trong browser.
- [ ] Animation tôn trọng `prefers-reduced-motion`.

Khoảng trống toàn shell hiện tại gồm focus management của drawer/dropdown, accessible name cho một số icon, submenu `href="#"` và control động thiếu label. Employee dialog/filter/responsive đã có kiểm tra hẹp; không suy rộng kết quả đó sang mọi page hoặc shell ở branch `frontend`.

## Browser acceptance

### Employee runtime evidence (2026-08-21)

Browser recheck ghi nhận `documentOverflow=false` ở cả năm viewport; ở 320px
`.main-content` co đúng nhờ `min-width: 0`, bảng giữ overflow-x trong
`.table-responsive`, và media rule chỉ ẩn global topbar search placeholder.
Employee filter, title, hamburger và account vẫn có mặt; từ 375px search hiện
lại. Console không có lỗi trong các trang đã kiểm tra. Đây là bằng chứng runtime
hẹp của employee page, không thay thế acceptance của shell `frontend` branch.

Avatar upload/replacement chưa được browser verify do file chooser bị Chrome
extension từ chối; automated upload/ownership tests không chứng minh được thao
tác file thật trong browser.

Tối thiểu kiểm tra:

| Viewport | Luồng |
| --- | --- |
| Desktop rộng sau tích hợp shell | Sidebar mở/thu, bảng, flyout, modal |
| Tablet | Layout chuyển breakpoint, bảng cuộn |
| Mobile 375px | Drawer, header, form, dialog |

Ở mỗi viewport kiểm tra:

- Visual clipping/overflow.
- Keyboard/focus.
- Console error/warning.
- Network request/status/payload.
- Loading/empty/error state.

Automated DOM/build test không thay thế browser acceptance.
