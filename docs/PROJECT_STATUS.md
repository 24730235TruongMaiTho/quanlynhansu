# Trạng thái dự án

## Full module/role audit mới nhất — 2026-09-06

Audit toàn bộ route web/API trong phạm vi đã giao được đối chiếu với năm role
seed active trên HEAD `074d65eba9f8653aa2c849d58746f056518da068`, branch
`main`. Đã sửa middleware authorization thiếu ở các endpoint Chấm công/Lương
và các contract date, filter/pagination/delete, approval của các module
prototype; không mutation database live, không thêm procedure/view. Tài liệu
chi tiết và ma trận quyền: [FULL_MODULE_ROLE_AUDIT_2026-09-05.md](FULL_MODULE_ROLE_AUDIT_2026-09-05.md).

Evidence hiện tại: full Laravel `470 passed, 3758 assertions`; toàn bộ
frontend `112/112` và package `npm run test:frontend` `55/55`; Vite build pass
`31 modules transformed`; route inventory `98` với duplicate name/signature
`0`; Composer, PHP lint và `git diff --check` pass.
Chrome/CUA fresh desktop read-only đã smoke đủ 5 role, các route ngoài quyền
trả 403 và console quan sát được không có lỗi; `/luong`, `/cham-cong`,
`/nghi-phep`, `/hop-dong` đã re-open bằng loading/visibility thật. Mobile
representative `375x812` tại `/duyet-nghi-phep` là snapshot lịch sử trước khi
canonical hóa route; không còn là route hiện hành. Network waterfall và browser
mutation vẫn unverified vì chưa có evidence/disposable browser DB guard.

SQL active được đọc theo thứ tự ba file nguồn và hiện có 15 bảng, 42 quyền,
12 routine. Live DB read-only cho thấy 6 role (một role legacy ngoài fresh
matrix) và `db:show --counts` bị chặn bởi thiếu
`performance_schema.session_status`; không dùng live DB để sửa hoặc tạo routine.
Các giới hạn này thay thế số liệu lịch sử bên dưới, không xóa lịch sử.
Ma trận quyền tách riêng `Luong.*` và `HeSoLuong.*`: role Kế toán có
`Luong.Read` nhưng không có `HeSoLuong.Read`; Quản trị và Nhân sự mới có các
quyền hệ số lương active. Batch Chấm công yêu cầu đồng thời Insert, Update và
Delete vì `so_gio_lam=-1` có semantics xóa.

## Cập nhật canonical Nghỉ phép/sidebar — 2026-09-06

Trang chuẩn của luồng duyệt là `/nghi-phep#leave-table-card`; card Dashboard
chỉ hiện cho actor đủ `NghiPhep.Read`, `NghiPhep.Update` và Gate
`department-manager`. Route web `/duyet-nghi-phep` và navigation item riêng đã
được gỡ. Nút Duyệt không render cho admin/HR chỉ có `NghiPhep.Update`; PATCH
không nhận `ma_nv`/`ma_pb` từ client và server scope theo `ma_pb` của actor,
chỉ xử lý đơn pending cùng phòng ban.

`DashboardService` gọi count contract chung của `NghiPhepService`, còn badge
tab dùng tổng `counts.pending` từ API (có fallback paginator), nên count không
bị giới hạn bởi số dòng trang hiện tại. Sidebar active/restored submenu được
hiển thị ngay first paint, không replay animation khi reload; click accordion
vẫn animate. Browser CUA read-only xác nhận card Trưởng phòng `0` khớp badge
pending `0`, click tới section đang visible, submenu active mở sẵn và console
sạch; Nhân viên không thấy card nhưng Dashboard vẫn tải không lỗi. Mobile
`375x812` tại route canonical không document overflow và bảng tự cuộn ngang.
Không chạy browser mutation hoặc database production.

Generic leave update đã bị khóa status bằng validation `prohibited` và service
allowlist; edit đơn đã xử lý không thể mở lại pending. Dedicated approval là
đường duy nhất đổi status, với row lock + conditional update trong transaction.
Pending/history loaders gửi tab cố định, nên refresh từ History vẫn giữ đúng
pending badge. Fragment anchor được restore lại sau khi employee/leave data
render xong để tránh layout shift đẩy section khỏi viewport.

## Bằng chứng hòa giải/UI mới nhất — 2026-09-05

HEAD hiện tại là `ce22524ef245ea24e4365ef830d822a1a247d9a6`. Fresh full Laravel `456 passed, 3655 assertions`; targeted PHP root rerun `19 tests, 249 assertions` pass; shared pagination Node `4/4` pass; `npm run test:frontend`: `38/38` pass; `npm run build`: `29 modules transformed`; route inventory `96`, duplicate signature/name `0`; `composer validate --no-check-publish`, PHP lint và `git diff --check` pass.

Chrome fresh read-only ngày 2026-09-05: `/vai-tro` có main `1320` và card `1296` theo module chuẩn; `/luong` chỉ còn một nút `Đặt lại`; `/phong-ban` có `ma_pb` và `so_nhan_vien` transparent, không nền; paginator tại `/vai-tro`, `/luong`, `/nghi-phep`, `/cham-cong` có center delta `0`, page-link `44x44`, radius `10px`, active `rgb(233,69,96)`, không document overflow ở viewport desktop hiện tại; screenshot visual review pass. Không claim responsive browser mới hoặc mọi interaction; không có browser mutation/DB write.

> Cập nhật 2026-08-27 trên local `main` (HEAD `f71c0b20a4e04e8e2ec32cdad2a68722e4aaa0b7`). Các số liệu trong tài liệu này chỉ là bằng chứng của đúng lệnh/ môi trường được ghi; không suy rộng thành production hoặc browser acceptance.

## Nguồn và phạm vi

SQL fresh active phải chạy theo thứ tự `database/sql/tao_bang.sql` → `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql` trên database rỗng/disposable đã được phê duyệt. Hợp đồng có đúng 15 bảng, 19 nhân viên, 37 quyền và 12 thủ tục RBAC. `quan_ly_nhan_su.session.sql`, `LocalDemoSeeder` và script SQL employee cũ chỉ để đối chiếu lịch sử.

Ownership hiện tại chỉ gồm code Nhân viên, Phòng ban và Chức vụ. Lỗi hoặc thiếu hợp đồng của Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC và API của đồng nghiệp được ghi chú trong tài liệu; không sửa code ngoài scope nếu chưa được giao rõ.

## Bằng chứng phiên hiện tại

| Kiểm tra | Kết quả |
| --- | --- |
| Git | `main`, HEAD `f71c0b2`, chỉ có thay đổi local của task và file untracked người dùng |
| Focused scope tests | `14 tests, 66 assertions` pass |
| Employee/auth/regression slice | `110 tests, 1308 assertions` pass |
| Full Laravel | `288 tests, 2222 assertions` pass |
| Phòng ban/Chức vụ | Feature tests pass trong full suite; code không đổi trong phiên này |
| Route inventory | `79` route; `php artisan route:list --except-vendor` pass |
| Frontend/build | `npm run test:frontend`: `18` pass; `npm run build`: `19 modules transformed` |
| Composer/lint | `composer validate --no-check-publish`, PHP lint file sửa và `git diff --check` pass |
| Fresh MariaDB | `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; schema disposable, không phải evidence database live/production |
| Browser | Chưa chạy trong phiên này |

Full suite mặc định dùng SQLite in-memory. Muốn kiểm tra DDL, foreign key và dữ liệu MariaDB phải dùng wrapper guarded disposable; không trỏ vào database live.

## Trạng thái module

| Module | Web/UI | Data/test | Trạng thái |
| --- | --- | --- | --- |
| Nhân viên | List/filter/pagination, create, show, edit, lifecycle; auth/Gate; self-delete hidden | Direct Query Builder trên 15 bảng; auth hydrate `ma_pb`; scope Trưởng phòng; automated tests pass | **Verified hẹp**, browser/production chưa claim |
| Phòng ban | CRUD server-rendered, action gating và lỗi an toàn | Direct Query Builder, transaction/row lock; feature/MariaDB evidence trước đó | **Verified hẹp**, code không đổi phiên này, browser chưa claim |
| Chức vụ | CRUD server-rendered, action gating và lỗi an toàn | Direct Query Builder, transaction/row lock; feature/MariaDB evidence trước đó | **Verified hẹp**, code không đổi phiên này, browser chưa claim |
| Dashboard | Render được, auth/permission riêng | Chưa có nghiệp vụ dữ liệu đầy đủ | **Prototype**; không sửa trong scope |
| Lương | UI/API prototype | `LuongRepository@all` gọi `sp_luong_tim_kiem_phan_trang` thiếu trong ba SQL active | **Prototype — blocked** |
| Chấm công | UI/API prototype | Lookup gọi `sp_phong_ban_danh_sach`; update gọi `sp_cham_cong_cap_nhat`; cả hai thiếu trong ba SQL active | **Prototype — blocked** |
| Nghỉ phép | UI/API prototype | Approve gọi `sp_nghi_phep_duyet_phep`, thiếu trong ba SQL active | **Prototype — blocked** |
| Hợp đồng | Scaffold/model/controller hạn chế | Chưa có workflow mutation và browser evidence đầy đủ | **Planned/scaffold** |
| Vai trò/Phân quyền/RBAC | Một phần UI quản trị | Catalog và 12 procedure RBAC thuộc SQL active; mutation/UI/browser chưa được đóng toàn bộ | **Nền tảng verified hẹp** |

## Module Nhân viên

### Scope Trưởng phòng

`App\Support\NhanVienScope` áp policy tại controller/request, repository vẫn thuần dữ liệu. Actor có `ma_vt = 4` (`NhanVienRole::DepartmentManager`) phải có `ma_pb` là số dương hợp lệ. List ép filter `ma_pb` theo actor và chỉ trả lookup phòng ban tương ứng. Show/edit/update/destroy cross-department trả 404 để không lộ target; actor thiếu/sai `ma_pb` cũng fail closed. `UpdateNhanVienRequest::authorize()` lookup target trước validation.

Destroy chặn mã actor trước khi gọi service bằng lỗi an toàn. Index/show/edit không render action phá hủy cho chính actor. Auth provider và `NhanVien::fromAuthRow()` hydrate `ma_pb` để scope không mất sau login/session restore. Không thay đổi mapping role/Gate/RBAC.

### Data contract

Repository Nhân viên dùng explicit Query Builder trên `nhan_vien` và các bảng liên quan của contract 15 bảng, không gọi procedure employee/auth hay tạo SQL object mới. Avatar/path cleanup, hash và transaction vẫn thuộc service boundary; password/hash không đi ra view/API. Địa chỉ nằm trực tiếp trên `nhan_vien`.

### Giới hạn

Automated tests không thay thế browser. Avatar file chooser/replacement, production rollout, MySQL 8 compatibility và mutation trên database thật chưa được claim. Không gọi local/disposable result là production-ready.

## Lệch hợp đồng ngoài ownership (chỉ ghi chú)

- Dashboard: vấn đề auth/thiếu quyền hoặc dữ liệu riêng cần xử lý bằng task Dashboard; không sửa ở đây.
- Lương: `LuongRepository@all` gọi `sp_luong_tim_kiem_phan_trang`, procedure không tồn tại trong SQL active.
- Chấm công: `ChamCongController` lookup gọi `sp_phong_ban_danh_sach`, update gọi `sp_cham_cong_cap_nhat`; các procedure không tồn tại trong SQL active. Không sửa caller trong task Phòng ban/Nhân viên.
- Nghỉ phép: `NghiPhepController` approve gọi `sp_nghi_phep_duyet_phep`, procedure không tồn tại trong SQL active.
- Model/validation/API/exception của các module legacy còn drift so với schema; phải audit riêng theo module.
- Hợp đồng mới chỉ là scaffold; Vai trò/Phân quyền/RBAC có catalog và procedure nền tảng nhưng UI quản trị, mutation và browser evidence chưa đầy đủ.

## Modal chỉnh sửa Nhân viên 2026-08-29

Trên HEAD `c361b7b`, danh sách Nhân viên có modal native tải form edit on-demand qua route GET hiện hữu khi có `NhanVien.Update`. Form dùng partial chung với trang edit đầy đủ; no-JavaScript/direct-link vẫn dùng `/nhan-vien/{ma_nv}/edit`. Submit dùng `FormData` tới PUT/PATCH hiện hữu, trả JSON success/422 field hoặc form-level errors/lỗi an toàn và reload URL danh sách hiện tại sau thành công. Trong lúc submit, modal khóa đóng/cancel/Escape và không nhận action mở nhân viên khác; sau lỗi vẫn mở lại được. Scope Trưởng phòng, Gate, CSRF, avatar và wizard hiện hữu được giữ nguyên; không thêm schema/API.

Feature modal và frontend controller đã có kiểm thử RED trước implementation rồi GREEN targeted: các hồi quy submit retry, khóa đóng/cancel/Escape, form-level 422 và partial lookup warning đều RED trước sửa; modal/update và index \`32 tests, 309 assertions\` pass; relevant Nhân viên/auth/service/unit \`177 tests, 1518 assertions\` pass; modal/shared/list Node \`16 tests\` pass. Full Laravel hiện \`290 passed, 11 failed, 2252 assertions\` do baseline ngoài ownership ở ContentFour/Chấm công/Nghỉ phép. `npm run test:frontend` hiện \`31 passed, 1 failed\` do đúng lỗi nền tại `tests/Frontend/nghiphep/employee-response.test.js` (\`expected —, actual -\`), không sửa. Build pass \`25 modules transformed\`, route inventory pass \`89 routes\`, Composer/PHP lint/diff hygiene pass. Browser runtime chưa chạy trong phiên; MariaDB không chạy vì không đổi data layer.

## Quy tắc thay đổi

## Modal chỉnh sửa Phòng ban/Chức vụ và Nhân viên 2026-08-29

Danh sách Phòng ban và Chức vụ hiện mở native dialog tải partial form on-demand khi actor có Gate cập nhật; mỗi action vẫn giữ URL edit thật làm fallback. GET edit, PUT/PATCH update, FormRequest, CSRF, Query Builder, transaction/row lock, Gate và delete behavior hiện hữu được giữ nguyên. JSON success/422 và lỗi server có shape an toàn, modal khóa submit/đóng khi request đang chờ, khôi phục focus và reload đúng URL danh sách sau thành công. Trang xem Nhân viên dùng lại shell modal hiện có; nếu không có JavaScript, href edit đầy đủ vẫn hoạt động.

Step 3 form Nhân viên đã nhóm từng cặp dt/dd trong một row có đường phân cách liên tục; ở màn hình hẹp row chuyển một cột. Đây chỉ là thay đổi markup/CSS, không đổi dữ liệu hay contract.

RED/GREEN phiên này: RED feature 8 failure và responsive/shared Node 2 failure trước implementation; sau sửa targeted PB/CV/Nhân viên 51 tests, 487 assertions pass; targeted Node core 25 tests pass. Full Laravel 298 passed, 11 failed, 2321 assertions; 11 failure là baseline ngoài ownership ở ContentFour/Chấm công/Nghỉ phép. npm run test:frontend 36 passed, 1 failed, lỗi nền duy nhất tại tests/Frontend/nghiphep/employee-response.test.js (expected —, actual -), không sửa. Build pass 26 modules transformed; route inventory 89 routes; Composer, PHP lint controller và git diff --check pass. Browser chưa chạy và MariaDB disposable không lặp vì không đổi data layer; không claim browser, database live hoặc production.

Một module chỉ được gọi Done khi route, validation, data contract, UI states, auth/authorization, feature/integration tests, build và browser acceptance phù hợp đều có bằng chứng. Không xóa assertion hoặc đổi tài liệu để che blocker. Chỉ mutation trên database test/disposable; không chạy canonical SQL destructive trên database cần giữ dữ liệu.

Lệnh chuẩn:

```powershell
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
git diff --check
git status --short
```

## Cập nhật feedback giao diện 2026-08-28

Lát cắt feedback 1, 2, 4, 5, 6, 7 và 8 đã được triển khai trong phạm vi Nhân viên, Phòng ban, Chức vụ và shared UI. Danh sách Chức vụ/Phòng ban dùng filter tên, chọn số dòng và pagination số; danh sách Nhân viên dùng summary/pagination/action select thống nhất nhưng vẫn giữ whitelist query string, Gate, scope Trưởng phòng và guard tự xóa. Chi tiết Nhân viên đã nhóm dữ liệu, tăng avatar, tách Tài khoản và hiển thị hệ số chức vụ từ join hiện có.

Verification cuối phiên: focused module/regression `53 tests, 481 assertions` và repository pagination `14 tests, 64 assertions` pass; full Laravel `294 tests, 2287 assertions` pass; frontend `21/21` pass; Vite `21 modules transformed`; route inventory `79`, Composer, PHP lint và `git diff --check` pass. MariaDB disposable: `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; đây không phải database live. Browser acceptance, font/network thật và production chưa được kiểm chứng.

Contract `paginate()` mới không loại bỏ `all()`. Không tạo show route CV/PB, không query lương/hợp đồng, không thêm `noi_sinh` vào schema. Font Be Vietnam Pro và nhãn sidebar đã cập nhật. Chi tiết acceptance/deferred/backlog nằm trong `docs/FEEDBACK_ACTION_PLAN.md`; browser acceptance và database live vẫn chưa claim.
