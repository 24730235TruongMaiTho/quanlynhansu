# Lỗi tiềm ẩn

> Snapshot này ghi nhận trạng thái tại HEAD `a96860e` ngày 2026-08-31. Đây là báo cáo rủi ro theo bằng chứng đã có, không phải tuyên bố toàn bộ dự án đã hoàn thành.

## Phân loại bằng chứng

### Verified

- PHP suite thuộc phạm vi sở hữu (Nhân viên, Phòng ban, Chức vụ): `131 pass / 1196 assertions`.
- Full Laravel: `301 pass / 11 fail / 2340 assertions`.
- Frontend: `37 pass / 1 fail`.
- Vite build: pass, `27 modules transformed`.
- Route inventory: `89 routes`.

Các số liệu trên là kết quả của các lệnh và môi trường tương ứng. Không gọi toàn bộ dự án là pass.

Nhân viên, Phòng ban và Chức vụ hiện **verified hẹp** theo test/contract hiện có. Chưa có đủ bằng chứng browser acceptance, database live hoặc production; không claim các gate đó.

### Blocked / known failures

Full Laravel còn 11 failure ngoài phạm vi implementation của task này:

- 1 test ContentFour: nguyên nhân thực chất là route `api.v1.cham-cong.nhan-vien` thiếu middleware `can:ChamCong.Read`.
- 8 test `ChamCongEmployeeLookupSecurity`.
- 2 test `NghiPhepEmployeeLookup`.

Frontend còn 1 failure tại Nghỉ phép: test kỳ vọng ký hiệu `—` nhưng dữ liệu/hiện trạng trả `-`.

### Unverified

- Browser/runtime acceptance và production rollout chưa được kiểm chứng.
- MariaDB live chưa được kiểm chứng; test MariaDB disposable (nếu có) không thay thế database live.
- Tương thích DBMS ngoài MariaDB 10.4.32, đặc biệt MySQL 8, chưa được claim.

## Contract gaps ngoài ownership

Các mục sau chỉ ghi nhận để xử lý bằng task riêng; không tự sửa trong lát cắt Nhân viên/Phòng ban/Chức vụ:

- Chấm công: lookup gọi `sp_phong_ban_danh_sach`, update gọi `sp_cham_cong_cap_nhat`; hai routine này không nằm trong ba SQL active.
- Nghỉ phép: employee lookup gọi `sp_nhan_vien_danh_sach_phan_trang`; paging nghỉ phép gọi `sp_nghi_phep_danh_sach_phan_trang` (nguồn supplemental), approve gọi `sp_nghi_phep_duyet_phep`; các routine này không nằm trong ba SQL active.
- Lương: paging gọi `sp_luong_tim_kiem_phan_trang`, không nằm trong ba SQL active.
- Một số controller/module legacy còn rủi ro trả raw exception/SQL message (ví dụ dùng trực tiếp exception message) ra response; cần audit và chuẩn hóa contract lỗi an toàn.
- API có rủi ro thiếu middleware `can` ở cấp route, như failure ContentFour nêu trên; cần rà từng endpoint theo catalog quyền.
- Dashboard vẫn là prototype, chưa có đủ nghiệp vụ dữ liệu và boundary auth/permission.
- Tài liệu hiện còn đánh dấu Hợp đồng scaffold/planned; dù code HEAD đã có CRUD/UI, browser acceptance và MariaDB mutation chưa đủ evidence. Quản trị Vai trò/Phân quyền/RBAC mới verified hẹp, chưa đủ mutation/browser evidence.

## SQL snapshot và nguồn sự thật

`quan_ly_nhan_su.session.sql` là dump historical để đối chiếu, không phải nguồn active. File `quan_ly_nhan_vien_session_update.sql` là bản gộp canonical tại HEAD này, có bootstrap destructive và ba section nguồn theo đúng thứ tự; tuy vậy ba file `database/sql/tao_bang.sql`, `database/sql/du_lieu_mau.sql`, `database/sql/quyen_vai_tro.sql` vẫn là source of truth.

Snapshot không được chạy vào database cần giữ dữ liệu. Trước khi chạy phải xác nhận đúng target disposable/rỗng, backup khi cần và có approval.

## Việc cần theo dõi

- Khóa contract routine/paging và shape response cho Lương, Chấm công, Nghỉ phép trước khi bổ sung hoặc thay caller.
- Sửa/kiểm thử middleware quyền và thông báo lỗi an toàn ở API ngoài ownership.
- Chạy lại route, test, build, browser và DB disposable/live gates sau mỗi thay đổi HEAD; cập nhật tài liệu khi số liệu thay đổi.
