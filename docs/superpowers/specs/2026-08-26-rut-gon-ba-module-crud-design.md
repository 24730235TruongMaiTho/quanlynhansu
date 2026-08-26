# Thiết kế rút gọn ứng dụng về CRUD Nhân viên, Phòng ban và Chức vụ

Ngày: 2026-08-26  
Trạng thái: Đã được người dùng duyệt trong hội thoại, chờ duyệt lại đặc tả trước khi lập kế hoạch triển khai.

## 1. Bối cảnh

Nhánh hiện tại đã tích hợp CRUD Nhân viên, Phòng ban và Chức vụ cùng nhiều phần mở rộng do chúng ta xây dựng: đăng nhập bằng nhân viên, RBAC, Gate, giới hạn Trưởng phòng theo phòng ban, giới hạn mục tiêu theo vai trò, đặt lại mật khẩu và bộ kiểm thử/acceptance tương ứng.

Các thành viên khác đã đưa code module của họ, kể cả Vai trò và Phân quyền, lên Git. Lần hợp nhất tiếp theo có thể xung đột tại route, provider, layout và mã phân quyền dùng chung. Code của đồng nghiệp phải được ưu tiên; thay đổi trong nhánh này không được ghi đè hoặc thay thế nghiệp vụ họ sở hữu.

Không được revert nguyên các commit lịch sử vì các commit đó trộn lẫn CRUD cần giữ với auth/RBAC cần tháo. Việc rút gọn phải thực hiện bằng thay đổi tiến về phía trước, theo từng trách nhiệm.

## 2. Mục tiêu

Sau thay đổi, ứng dụng hiện tại giữ CRUD đầy đủ của ba module:

- Nhân viên: danh sách, tìm kiếm, phân trang, chi tiết, thêm, sửa hồ sơ, địa chỉ, ảnh đại diện và xóa hoặc chuyển nghỉ việc an toàn.
- Phòng ban: danh sách, thêm, sửa, xóa và chặn xóa khi đang được nhân viên sử dụng.
- Chức vụ: danh sách, thêm, sửa, xóa và chặn xóa khi đang được nhân viên sử dụng.

Các chức năng do chúng ta bổ sung nhưng không thuộc CRUD ba module phải được tháo:

- đăng nhập và đăng xuất;
- custom auth provider cho Nhân viên;
- RBAC, Permission Registry, Permission Service/Repository, Gate và middleware quyền;
- giới hạn Trưởng phòng chỉ thấy nhân viên cùng phòng;
- giới hạn sửa/xóa theo vai trò của nhân viên mục tiêu;
- đặt lại mật khẩu;
- rollout kill switch `NHAN_VIEN_MODULE_ENABLED` và middleware chỉ phục vụ giai đoạn triển khai module;
- công cụ demo, acceptance harness và test chỉ phục vụ auth/RBAC.

## 3. Ngoài phạm vi

- Không sửa nghiệp vụ, controller, view hoặc JavaScript của Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò và Phân quyền, trừ việc hoàn tác chính xác phần phụ thuộc auth/RBAC do chúng ta từng cài vào file dùng chung hoặc module đó.
- Không thiết kế lại module Vai trò/Phân quyền của đồng nghiệp.
- Không thay đổi `database/tao_bang.sql`, `database/du_lieu_mau.sql` hoặc `database/sql/du_lieu_mau.sql`.
- Không tạo `database/du_lieu_mauV2.sql`.
- Không cập nhật database live.
- Không fetch, merge, rebase, commit hoặc push khi chưa có yêu cầu riêng.

## 4. Quy tắc ưu tiên code đồng nghiệp

1. Không revert hàng loạt commit và không dùng thao tác Git phá hủy lịch sử.
2. Thay đổi chỉ được thực hiện trên trạng thái nhánh hiện tại đã kiểm tra.
3. Với file dùng chung, chỉ sửa hunk tối thiểu cần thiết để tháo phần do chúng ta tạo.
4. Khi hợp nhất code đồng nghiệp trong tương lai, phiên bản của đồng nghiệp là nền đối với module họ sở hữu, Vai trò và Phân quyền.
5. Sau khi nhận code đồng nghiệp, chỉ áp lại phần CRUD tối thiểu của Nhân viên, Phòng ban và Chức vụ nếu thật sự còn thiếu.
6. Không chọn toàn bộ một phía trong xung đột nếu file chứa cả code đồng nghiệp và CRUD cần giữ; phải giải quyết thủ công theo trách nhiệm.

## 5. Kiến trúc sau khi rút gọn

### 5.1 Route và điểm vào ứng dụng

- Xóa route `/dang-nhap`, `/login`, `/dang-xuat` và route test đăng nhập.
- Route `/` chuyển trực tiếp tới `backend.nhanvien.index`.
- Gỡ middleware `auth` khỏi nhóm route web hiện tại.
- Gỡ middleware `can:*` khỏi route Nhân viên, Phòng ban và Chức vụ.
- Gỡ middleware auth/RBAC do chúng ta thêm vào API lookup dùng chung. Không thay đổi action, URL, payload hoặc nghiệp vụ controller của module đồng nghiệp.
- Giữ tên route CRUD hiện tại để Blade, redirect và test không bị đổi contract không cần thiết.

### 5.2 Hạ tầng đăng nhập và RBAC

Các thành phần chỉ phục vụ auth/RBAC do chúng ta xây dựng sẽ được xóa khi không còn tham chiếu:

- controller phiên đăng nhập, login request và login Blade;
- `NhanVienUserProvider` và đăng ký provider trong `AppServiceProvider`;
- Permission Registry, các contract permission, Permission Repository/Service, permission action/enum và `config/permissions.php`;
- Gate definitions và các helper test giả lập quyền;
- test auth, authorization và MariaDB procedure chỉ chứng minh auth/RBAC legacy.

`config/auth.php` trở về cấu hình Laravel trung tính, không tham chiếu custom provider đã xóa. Không thêm một hệ thống đăng nhập thay thế.

### 5.3 Module Nhân viên

- `NhanVienController` không đọc `$request->user()` và không dùng `NhanVienDepartmentScope` hoặc `NhanVienTargetGuard`.
- `UpdateNhanVienRequest` chỉ xác minh nhân viên tồn tại và validation dữ liệu; không kiểm tra phòng ban của actor.
- Repository không áp bộ lọc phòng ban theo actor.
- Mọi nhân viên đều có thể được xem và sửa, không phụ thuộc `ma_vt`.
- Xóa giữ nguyên quy tắc toàn vẹn dữ liệu: xóa cứng khi không có dependency; chuyển trạng thái nghỉ việc khi đã có dữ liệu liên quan.
- Xóa endpoint, service/repository method, giao diện, dialog và test đặt lại mật khẩu.
- Xóa `EnsureNhanVienModuleEnabled` và khóa cấu hình `enabled`; giữ cấu hình đường dẫn avatar vì đây là phần CRUD ảnh đại diện.
- Khi tạo nhân viên, service vẫn tạo một bcrypt hash nội bộ để thỏa cột `mat_khau NOT NULL`. Hash không được hiển thị hoặc trả về giao diện, và không còn ý nghĩa tài khoản đăng nhập.
- `ma_vt` vẫn được ghi giá trị mặc định tương thích schema, nhưng không được dùng để cấp quyền hoặc chặn CRUD.
- `mat_khau` tiếp tục nằm trong `$hidden` để tránh rò rỉ dữ liệu tồn tại trong database.

### 5.4 Module Phòng ban và Chức vụ

- Giữ controller, request, model, service, repository, mapper lỗi, Blade và JavaScript CRUD.
- Bỏ permission enum/service và điều kiện Gate.
- Nút thêm, sửa, xóa được render trực tiếp.
- Giữ validation, transaction, row lock, thông báo lỗi an toàn và chặn xóa bản ghi đang được nhân viên tham chiếu.

### 5.5 Layout dùng chung

- Topbar và sidebar không được phụ thuộc `auth()->user()` hoặc Gate do chúng ta xây dựng.
- Liên kết ba module luôn hiển thị.
- Không tái cấu trúc thiết kế giao diện hoặc sửa menu nghiệp vụ của đồng nghiệp ngoài phần auth/RBAC cần tháo.

### 5.6 Tương thích module đồng nghiệp

`NhanVienDepartmentScope` hiện được chúng ta đưa vào Chấm công và Nghỉ phép. Các hunk này phải được hoàn tác về hành vi trước scope, nhưng không chỉnh nghiệp vụ còn lại của hai controller. Nếu lớp scope không còn tham chiếu sau khi hoàn tác, lớp và test của nó được xóa.

Các route/controller khác được giữ nguyên contract. Việc chúng tạm thời không còn lớp `auth` chung là hệ quả trực tiếp của yêu cầu tháo đăng nhập hiện tại; khi code auth/RBAC của đồng nghiệp được merge sau này, phần của họ được ưu tiên.

## 6. Xử lý lỗi và an toàn dữ liệu

- Giữ validation server-side và thông báo lỗi tiếng Việt hiện có.
- Không trả raw exception, SQL hoặc stack trace ra UI.
- Giữ transaction và filesystem compensation của ảnh đại diện.
- Không nới lỏng khóa ngoại hoặc dùng `FOREIGN_KEY_CHECKS=0` để ép xóa.
- Route không tồn tại tiếp tục trả 404; bản ghi không tồn tại trả 404 hoặc lỗi domain an toàn theo contract CRUD.
- Loại bỏ nhánh lỗi `403` chỉ tồn tại vì actor, Gate hoặc vai trò mục tiêu.

## 7. Comment và tài liệu

- Comment, docblock và ghi chú mới hoặc được sửa trong phạm vi task phải viết đầy đủ bằng tiếng Việt.
- Không thêm comment mô tả điều hiển nhiên; comment phải giải thích ràng buộc nghiệp vụ, transaction hoặc lý do tương thích.
- Không dịch hàng loạt comment của module ngoài phạm vi.
- Cập nhật `docs/PROJECT_STATUS.md`, handoff và hướng dẫn Nhân viên để không còn tuyên bố auth/RBAC hoặc department scope đang hoạt động trên nhánh này.

## 8. Chiến lược kiểm thử

### 8.1 Kiểm thử route và truy cập công khai

- Khách truy cập `/` được chuyển đến danh sách Nhân viên.
- Khách truy cập được index/create/store/edit/update/destroy của ba module.
- Route đăng nhập, đăng xuất và reset mật khẩu không còn tồn tại.
- Route inventory không còn middleware `auth` hoặc `can:*` do chúng ta quản lý.

### 8.2 Kiểm thử CRUD

- Giữ và điều chỉnh test feature/repository/service cho Nhân viên, Phòng ban và Chức vụ để chạy không cần `actingAs` hoặc mock Permission Service.
- Nhân viên mọi `ma_vt` và mọi `ma_pb` đều xuất hiện trong danh sách và có thể xem/sửa.
- Xóa nhân viên vẫn phân biệt xóa cứng và chuyển nghỉ việc theo dependency.
- Địa chỉ và avatar vẫn được tạo, sửa, thay thế hoặc xóa an toàn.
- Phòng ban/Chức vụ vẫn chặn duplicate, missing và in-use.

### 8.3 Kiểm thử hồi quy

- Chạy test tập trung của ba module trước.
- Chạy full Laravel suite và phân loại rõ lỗi module đồng nghiệp nếu baseline của họ đã thay đổi.
- Chạy frontend tests và Vite build.
- Chạy guarded MariaDB fresh suite trên database disposable để chứng minh schema 15 bảng và CRUD trực tiếp vẫn hoạt động.
- Chạy route list, PHP lint các file sửa, `composer validate --no-check-publish`, `git diff --check` và `git status --short`.
- Browser smoke nếu môi trường cho phép: mở trực tiếp ba module không qua login và thực hiện một vòng CRUD trên database disposable.

## 9. Tiêu chí hoàn thành

Thay đổi chỉ được coi là hoàn thành khi:

1. Ba module CRUD hoạt động không cần đăng nhập hoặc quyền.
2. Không còn route hoặc UI đăng nhập/đăng xuất/reset mật khẩu do chúng ta xây dựng.
3. Không còn Gate, permission middleware hoặc department/target scope trong ba module.
4. Không làm thay đổi SQL/schema/database live.
5. Nghiệp vụ module đồng nghiệp không bị sửa ngoài hunk auth/RBAC do chúng ta từng thêm.
6. Các gate kiểm thử phù hợp đã chạy và kết quả được báo cáo trung thực, phân biệt pass, fail và chưa kiểm chứng.
7. Worktree giữ nguyên file người dùng chưa theo dõi `AIAssistantInput-a1d28494-8caf-4d5a-8217-4d71fad94b75.chatInput`.
