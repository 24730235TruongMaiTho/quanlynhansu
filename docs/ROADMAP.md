# Roadmap thực hiện tiếp đồ án

Roadmap ưu tiên theo dependency và khả năng kiểm chứng, không theo số lượng file đã tạo.

## Nguyên tắc

- Ổn định nền tảng trước khi mở rộng module.
- Mỗi milestone phải để lại một vertical slice chạy và test được.
- Tách UI render, API wiring và nghiệp vụ thật thành ba mức bằng chứng.
- Không merge hai branch phân kỳ nếu chưa có plan/test cho xung đột.

## Milestone 0 — chốt baseline tích hợp

Mục tiêu: tạo một nền `main` có setup lặp lại được và không còn mâu thuẫn tài liệu/runtime.

- [ ] Chốt MariaDB 10.4 hay MySQL 8 là DBMS chuẩn và timezone chung cho PHP/DB.
- [ ] Đồng bộ `.env.example` với baseline thực tế.
- [ ] Quyết định import dump + migrations theo thứ tự nào.
- [ ] Tạo master-data seed tối thiểu để các FK nghiệp vụ có dữ liệu hợp lệ.
- [ ] Chốt route home: `/`, `/admin` và landing mong muốn.
- [ ] Chuẩn hóa web/API route names và validation/error status.
- [ ] Sửa test mặc định theo contract đã chốt.
- [ ] Lập plan tích hợp shell từ branch `frontend`; không merge tự động.
- [ ] Thêm DB preflight kiểm tra version/object/procedure thiếu.

Điều kiện xong:

- Thành viên mới setup được trên database disposable.
- Route list/build/test baseline có kết quả mong đợi và được ghi lại.

## Milestone 1 — khóa hợp đồng database

- [ ] Bổ sung hoặc thay thế bốn procedure code đang gọi nhưng không tồn tại.
- [ ] Sửa placeholder `sp_phong_ban_sua`.
- [ ] Chuẩn hóa model table/key/timestamps.
- [ ] Thống nhất kiểu `ma_nv`, ngày và hệ số giữa request/model/SQL/JSON.
- [ ] Không trả raw DB exception hoặc password field ra API.
- [ ] Viết integration test trên MariaDB disposable.

Điều kiện xong:

- Mọi procedure PHP gọi đều tồn tại, đúng chữ ký và có test contract.

## Milestone 2 — auth và phân quyền

Quyết định cần chốt trước:

- `nhan_vien` hay `users` là identity chính.
- Cách liên kết identity với `vai_tro/quyen`.
- Chuyển SHA-256 sang Laravel hashing như thế nào.

Thực hiện:

- [ ] Model/provider/guard hoặc mô hình auth Laravel đã chọn.
- [ ] Login/logout/session.
- [ ] Middleware auth cho `/admin` và API.
- [ ] Permission checks cho action nhạy cảm.
- [ ] Test guest/authenticated/forbidden.

## Milestone 3 — shell quản trị thống nhất

- [ ] Port contract Header + Sidebar + Main + Footer từ `frontend`.
- [ ] Dùng một navigation config có named route hợp lệ.
- [ ] Hợp nhất asset strategy, loại tải chéo landing/admin.
- [ ] Chuyển dashboard và các page dữ liệu.
- [ ] Test desktop rail/accordion/flyout và mobile drawer.
- [ ] Browser acceptance + accessibility.

Điều kiện xong:

- Chỉ còn một layout/admin shell canonical; page mới không lặp wrapper.

## Milestone 4 — module mẫu phòng ban

Chọn phòng ban làm vertical slice nhỏ:

- [ ] CRUD route/action đúng.
- [ ] Form Request.
- [ ] Model/procedure/query đúng schema.
- [ ] Blade index/create/edit.
- [ ] Flash/error/empty/confirm.
- [ ] Auth/permission.
- [ ] Feature + DB integration test.

Module này trở thành mẫu cho chức vụ và các danh mục.

## Milestone 5 — nhân viên

- [ ] Thay dữ liệu hard-code bằng contract thật.
- [ ] Hoàn thiện create/edit/update/delete/detail.
- [ ] Chốt identity/password handling.
- [ ] Validation CCCD/email/ngày/phòng ban/chức vụ.
- [ ] Test upload/avatar nếu được đưa vào scope.
- [ ] Test quyền và dữ liệu nhạy cảm.

## Milestone 6 — lương, chấm công, nghỉ phép

Làm từng module độc lập:

### Lương

- [ ] Contract pagination.
- [ ] Hệ số lương và kỳ lương.
- [ ] Unique kỳ lương.
- [ ] Công thức lương được chốt và test.
- [ ] Báo cáo/đối soát chỉ sau khi CRUD đúng.

### Chấm công

- [ ] Thay hai procedure phân trang thiếu.
- [ ] Chuẩn hóa `ngay_lam` và model.
- [ ] Cập nhật record có concurrency/error handling.
- [ ] Import/export thiết kế riêng; không bật button giả.

### Nghỉ phép

- [ ] Validation ngày và loại phép.
- [ ] Duyệt có actor/quyền/audit.
- [ ] Sửa trigger giao nhau với chấm công.
- [ ] Test create/update/delete/approve với fixture.

## Milestone 7 — module còn lại

Theo dependency:

1. Chức vụ, trạng thái làm việc.
2. Vai trò và quyền.
3. Hợp đồng.
4. Báo cáo.
5. Backup/restore ngoài web request.

## Hạng mục nộp hai môn

### Web Application

- [ ] Kiến trúc và database được giải thích.
- [ ] Auth/RBAC.
- [ ] CRUD có validation.
- [ ] Test nghiệp vụ.
- [ ] Xử lý lỗi và bảo mật.

### UI/UX

- [ ] Sitemap và user flow.
- [ ] Design tokens/component inventory.
- [ ] Responsive/browser matrix.
- [ ] Accessibility checklist.
- [ ] Ảnh/video demo và slide.

## Cách chọn task tiếp theo

Trước khi bắt đầu, task phải trả lời được:

1. Blocker nào đang được gỡ?
2. Input/output contract là gì?
3. Database test nào được phép dùng?
4. Bằng chứng hoàn thành là gì?
5. Tài liệu nào phải cập nhật?
