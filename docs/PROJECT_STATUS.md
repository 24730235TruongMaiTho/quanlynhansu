# Trạng thái dự án

## Current verified UI slice: Dọn action header Nghỉ phép và audit avatar — 2026-09-09

Header `/nghi-phep` đã bỏ đúng hai action `Lịch nghỉ` (`#calendar-btn`) và
`Thêm nghỉ phép` (`#create-btn`); không đổi tab `Lịch sử nghỉ phép`, bộ lọc
Từ ngày/Đến ngày hoặc `Xem tất cả lịch nghỉ` (`#all-leaves-btn`).
`nghiphep.js` đã bỏ lookup/listener tương ứng và dòng cập nhật
`elements.createButton.disabled`; modal Sửa, quyền Insert và trang tạo
self-service vẫn giữ nguyên.

RED trước implementation: frontend leave contract `1 failed, 13 passed` vì
DOM/JS còn hai action header. GREEN: targeted leave `30/30`, toàn bộ
`tests/Frontend` enumeration `145/145`, `npm run test:frontend` `91/91`; full
Laravel `506 passed, 4006 assertions`; Vite `31 modules`; route except-vendor
`95`, duplicate name/signature `0`; Composer, PHP lint và `git diff --check`
pass. Chrome read-only xác nhận `/nghi-phep` header chỉ còn title/description,
không có `Lịch nghỉ`/`Thêm nghỉ phép`; tab `Lịch sử nghỉ phép` vẫn mở, filter
Từ ngày/Đến ngày là `2026-06-09` đến `2026-09-09`, nút `Xem tất cả lịch nghỉ`
vẫn còn. Console không có error/warning, chỉ informational Employee paging
API log.

Avatar audit read-only xác nhận DB lưu relative owned path; public disk mặc định
URL `/storage` và hỗ trợ `PUBLIC_STORAGE_URL`; auth projection, topbar, list và
show dùng `Storage::disk('public')->url`. Test avatar hiện có `4 passed,
12 assertions`. Local `public/storage` là junction tới
`storage/app/public`, không tracked. Fresh clone/pull cần chạy
`php artisan storage:link`; nếu `.env` hoặc config cache cũ, chạy thêm
`php artisan config:clear`. Chrome read-only avatar DOM/topbar có `src`
`/storage/nhan-vien/avatars/8cdb3974-4702-4427-8053-118818de85d3.jpg`,
`complete=true`, `naturalWidth=2048`, `naturalHeight=1362`. Không submit,
mutation, upload hoặc symlink change; chưa kiểm tra multi-role, responsive và
fresh-clone acceptance.

## Current verified UI slice: Popup tạo Hợp đồng — 2026-09-09

Nút `Thêm hợp đồng` trên `/hop-dong` giờ mở native popup khi JavaScript hoạt
động, vẫn giữ `href` thật tới `/hop-dong/create` làm fallback no-JS. GET có
header modal trả partial form đầy đủ; form dùng lại CSRF, field, ngày hiển thị
native datepicker với value ISO `Y-m-d`, logic thời hạn và formatter lương.
Request Hợp đồng chấp nhận cả ISO native ở POST/PUT thường và JSON lẫn legacy
`dd/mm/yyyy`, đồng thời từ chối ngày không hợp lệ/mơ hồ. POST AJAX trả JSON
`201`, validation `422` hoặc lỗi an toàn `500`, thành công đóng popup và reload
danh sách. Lương lọc chữ số và nhóm dấu chấm permissive khi gõ liên tục,
submit vẫn gửi canonical digits với giới hạn server/DB 18 chữ số. Sửa hợp đồng
cũng mở cùng dialog với `href` thật làm fallback, partial PUT và JSON update;
edit/delete behavior còn lại không đổi. Dialog chỉ render khi có `HopDong.Insert`
hoặc `HopDong.Update`.

RED trước implementation: regression gõ salary tuần tự và edit-modal contract
failed (frontend `2 failed`; feature `6 failed`). GREEN sau implementation:
focused Hợp đồng/date/modal `30 passed, 262 assertions`; toàn bộ frontend
enumeration `144/144`; npm script `90/90`; Vite `31 modules`; full Laravel
`506 passed, 4011 assertions`; PHP lint và `git diff --check` pass. Chrome
read-only xác nhận create modal giữ URL `/hop-dong`; salary gõ tuần tự `1234`
hiển thị `1.234`, thêm `5` thành `12.345`, tiếp tục đủ 18 digit thành
`123.456.789.012.345.678`; `Hủy` đóng mà không submit, direct
`/hop-dong/create` vẫn là full-page fallback. Edit contract 18 click từ list
giữ URL `/hop-dong`, dialog title `Chỉnh sửa hợp đồng`, employee `00018 Hoàng
Đức Long`, loại finite, date pickers/prefill ISO `2019-02-15` và `2028-12-21`,
salary prefill `2.350.000`; select all gõ `1234` rồi `5` thành `12.345`, `Hủy`
đóng dialog. Console logs `[]`; không submit/mutation DB live; chưa kiểm tra
multi-role/responsive.

## Current verified UI slice: Popup recovery controls — 2026-09-09

Đã bỏ hai control popup `Mở trang đầy đủ` và `Thử lại` khỏi modal dùng chung
Phòng ban/Chức vụ và modal Nhân viên. Controller đã dọn recovery/fallback/retry,
nhưng vẫn giữ alert lỗi, nút Đóng và real `href` trên trigger bên ngoài popup.

RED: 25 test, `22 pass / 3 fail`. Fresh verification: targeted Node `25/25`;
targeted Laravel `72/72`, `713 assertions`; all frontend `139/139`;
`npm run test:frontend` `90/90`; full Laravel `492/3906 assertions`; Vite
`31 modules`. Chrome read-only xác nhận create/edit của Nhân viên, Phòng ban,
Chức vụ giữ URL list, hai nhãn recovery không còn, form/title/close vẫn đúng,
console errors `[]`. Không submit form/mutation DB; chưa kiểm tra multi-role/
responsive.

## Current verified UI slice: Lương — 2026-09-09

Lát cắt UI Lương mới dùng `btn-primary` xanh cho Thêm hệ số lương và các nút
Lưu; footer popup icon–chữ dùng `d-inline-flex align-items-center gap-2`.
Bảng chi tiết kỳ lương cho phép click dòng hoặc Enter/Space để chọn nhân viên,
không để các control action kích hoạt chọn dòng. Form tạo/sửa hệ số dùng native
datepicker và ISO; các popup liên quan đã được rà/sửa spacing cùng kiểu.

Evidence fresh: all frontend `138/138`; `npm run test:frontend` `89/89`;
Laravel `492/3906 assertions`; Vite `31 modules`; routes `95`, duplicate
name/signature `0`; Composer, lint và `git diff --check` pass. Chrome
read-only xác nhận click dòng đúng `00002`, keyboard đến `00004`, old
coefficient button count `0`, tạo lương giữ dòng đã chọn, màu add/save
`rgb(13,110,253)`, gap `8px`, native datepicker và prefill
`2019-05-28/2027-05-27`; console errors `[]`. Chưa submit form/mutation DB,
chưa có browser matrix nhiều role/responsive.

## Current verified UI slice: Hợp đồng, Lương và Nghỉ phép — 2026-09-09

Hợp đồng đã bỏ cột `#`; header và mã hiển thị dùng mã nhân viên, giữ leading
zero. Bảng chi tiết kỳ lương chỉ còn tên và mã nhân viên, không render
initials/avatar trong từng dòng. Nghỉ phép đã có lịch sử companywide theo
khoảng ngày native, bộ lọc history chỉ áp dụng cho history, bộ lọc ẩn ở tab
Chờ duyệt, bảng 8 cột với action theo từng dòng, và quyền `NghiPhep.Approve`
được xét độc lập khi xác định chế độ chỉ xem.

Verification hiện tại: Laravel `492 passed, 3905 assertions`; toàn bộ frontend
qua `rg --files` `133/133`; `npm run test:frontend` `85/85`; Vite `31 modules`;
route `95`, duplicate name/signature `0`; Composer, PHP lint và
`git diff --check` pass. Chrome read-only xác nhận `/hop-dong` không còn `#`
và mã đầu `00021`, `/luong` không có initials/avatar ở chi tiết kỳ lương,
`/nghi-phep` history companywide mặc định `2026-06-09..2026-09-09` trả `2`,
lọc `2026-09-05..2026-09-09` trả `1`, và “Xem tất cả lịch nghỉ” bỏ scope nhân
viên nhưng giữ kết quả. Nút Sửa/Xóa theo dòng hiển thị. Lần kiểm tra này không
có dòng pending nên chưa xác nhận nút Duyệt live; contract tests đã bao phủ
permission/status. Browser logs không có lỗi (chỉ log phân trang nhân viên ở
Nghỉ phép; Lương không có dữ liệu). Không mutation.

## Quyền duyệt Nghỉ phép và filter Lương — 2026-09-09

Đã bổ sung quyền canonical `NghiPhep.Approve` (id 43, action custom
`Duyet`) vào registry và fresh seed; role 1 và role 4 được cấp mặc định,
các role khác chỉ nhận quyền khi quản trị viên gán trong trang phân quyền.
Approval list dùng `NghiPhep.Read` + `NghiPhep.Approve`; PATCH chỉ dùng
`NghiPhep.Approve`, không còn phụ
thuộc role Trưởng phòng, `ma_pb` hay Gate `department-manager`. Danh sách,
count pending và xử lý approval chạy companywide; generic Update vẫn cấm
`trang_thai_duyet`, còn transaction/row lock/conflict guard được giữ nguyên.
Dashboard dùng payload `pending_leave_count` khi actor có Read + Approve.

Lát cắt này đã có test route allow/deny, registry/seed/upgrade SQL,
companywide list/count/cross-department approval, processed-row conflict,
Dashboard permission-only và Blade/JS control. Fresh root verification: Laravel
`491 passed, 3902 assertions`; frontend `77/77`; Vite `31 modules`; route
except-vendor `95`, duplicate name/signature `0`; Composer, PHP lint và
`git diff --check` pass. Browser read-only xác nhận filter Lương không còn kính
lúp. SQL upgrade đã chạy trên schema `quan_ly_nhan_su` bằng MariaDB `10.4.32`,
exact execution qua MariaDB client exit `0`, không backup theo chỉ định user.
Postcheck xác nhận `permission_count=43`, `max=43`, đúng một row canonical
id 43/symbol/label/module, grants đúng role 1 và 4, temporary routine count `0`.
Browser read-only xác nhận checkbox permission-43 checked ở role 1 và nút Duyệt
xuất hiện trên `/nghi-phep`.

## Đồng bộ avatar và UI dùng chung — 2026-09-08

Public disk mặc định trả URL tương đối `/storage` với override
`PUBLIC_STORAGE_URL`; auth lookup/model hydrate `anh_dai_dien`; topbar hiển thị
ảnh qua Storage hoặc initials fallback, CSS ảnh tròn cover. README quick start
đã nêu `php artisan storage:link`; Composer `setup` cũng gọi lệnh này, không
thêm symlink/route/schema. Control Sửa
trong các module đã chuẩn hóa thành `btn-outline-primary` +
`bi-pencil-square`; sidebar bỏ Thêm chức vụ và Danh sách hệ số lương, chỉ hiện
group Lương khi actor có `Luong.Read`. Toàn bộ ô ngày Nghỉ phép chuyển native
`type=date` và ISO canonical (edit prefill ISO, bảng hiển thị dd/mm/yyyy); bỏ
kính lúp tại ô tìm nhân viên của Chấm công/Nghỉ phép; header Thêm thông tin
lương dùng `btn-primary`.

RED: PHP `3 failed/4 tests`, frontend `7 failed/16 tests` trước implementation.
GREEN/final evidence: targeted PHP `33 passed, 237 assertions`; frontend
`76/76`; full Laravel `487 passed, 3870 assertions`; Vite `31 modules`; route
inventory `95`; Composer, PHP lint và `git diff --check` pass. Chưa có browser
mutation, live DB mutation hoặc file chooser/clone acceptance.

## Modal CRUD Nhân viên/Phòng ban/Chức vụ — 2026-09-08

Ba trang index hiện có native dialog cho cả Tạo và Sửa khi JavaScript hoạt
động, với href thật và trang đầy đủ làm progressive fallback. Trigger/action
được Gate Create/Edit bảo vệ; direct pencil Nhân viên có hook modal rõ ràng.
Create/edit partial được tải bằng header modal; store/update AJAX dùng JSON
success, lỗi validation 422 và thông báo server an toàn. Wizard Nhân viên vẫn
giữ CSRF, FormData, method spoofing và hỗ trợ avatar; không đổi route,
service/repository hoặc database; validation nghiệp vụ chỉ được điều chỉnh
riêng ở contract địa chỉ bên dưới.

Evidence fresh: RED trước implementation 5 targeted PHP failures; targeted
PHP `73 passed, 711 assertions`; full Laravel `476 passed, 3808 assertions`;
`npm run test:frontend` `57/57`; Vite `31 modules transformed`; route
inventory `95`; Composer, PHP lint và `git diff --check` pass. Chrome/CUA
read-only đã xác nhận sáu trigger Tạo/Sửa mở đúng dialog trên ba trang danh
sách và giữ nguyên URL; chưa submit form, chưa kiểm tra network waterfall hoặc
mutation database live.

## Đồng bộ popup Tạo Nhân viên — 2026-09-08

Popup Tạo truyền `modalOnly` vào partial form nên chỉ ẩn Quận/Huyện trong
dialog; trang `/nhan-vien/create` đầy đủ vẫn giữ trường này làm progressive
fallback. Review Ngày vào làm trong popup hiển thị `dd/mm/yyyy` cho cả giá trị
old ban đầu và giá trị sau input/change; input/submission vẫn là ISO
`yyyy-mm-dd` qua formatter frontend dùng chung. Không có reset-password trong
create và không đổi route/controller/service/repository/DB. Request validation
đổi riêng semantics địa chỉ: ba trường hiển thị all-or-none, `quan_huyen`
nullable tùy chọn, không synthesize giá trị.

RED gồm 2 feature tests và 1 frontend contract test; GREEN focused là PHP
`2 tests, 18 assertions` và Node wizard `6/6`. Verification trên HEAD hiện
tại: full Laravel `480 passed, 3847 assertions`; `npm run test:frontend`
`60/60`; Vite `31 modules transformed`; route inventory `95`; Composer, PHP
lint và `git diff --check` pass. Chưa browser mutation hoặc DB live write.

## Contract địa chỉ cho popup Tạo — 2026-09-08

`StoreNhanVienRequest::after()` chỉ kiểm tra ba trường hiển thị
`dia_chi_cu_the`, `phuong_xa`, `tinh_thanh` theo semantics all-or-none;
`quan_huyen` là nullable tùy chọn. Payload popup không gửi district được chấp
nhận và service nhận address không có key district; không synthesize giá trị.
Trang create đầy đủ vẫn render trường Quận/Huyện để progressive fallback.

RED: regression store/request mới trả 422 vì callback còn đếm đủ bốn trường.
GREEN: Store/Request focused `34 passed, 359 assertions`, gồm create và update
không district, core thiếu trường bị từ chối và message mới được khóa. Full
page UI focused test cũng xác nhận field Quận/Huyện vẫn hiện nhưng không còn
`required`. Full verification hiện tại: Laravel `484 passed, 3861 assertions`; frontend `60/60`;
Vite `31 modules transformed`; route `95`; Composer, PHP lint và
`git diff --check` pass. Chưa browser mutation hoặc DB live write.

## Tinh chỉnh popup Nhân viên — 2026-09-08

Show Nhân viên không còn render reset mật khẩu ngay cả với actor có quyền
`NhanVien.ResetPassword`; route/controller/service vẫn tồn tại, còn các vị trí
index/edit đầy đủ không đổi. Popup edit dùng context `modalOnly` rõ ràng để ẩn
Quận/Huyện riêng trong popup; create và full-page edit vẫn giữ trường. Review
Ngày vào làm của popup dùng `dd/mm/yyyy`, còn input/submission vẫn
`yyyy-mm-dd`; input/change sau khi sửa ngày cũng cập nhật review qua formatter
frontend. Giá trị Quận/Huyện hiện có được bảo toàn trong FormData mà không
render label/input.

Evidence fresh: RED 3 test hành vi; targeted Nhân viên show/update/reset/create
`41 passed, 427 assertions`; Node wizard/modal `17/17`; full Laravel `478
passed, 3829 assertions`; `npm run test:frontend` `59/59`; Vite `31 modules
transformed`; route inventory `95`; Composer, PHP lint và `git diff --check`
pass. Chưa có browser mutation hoặc DB live write.

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

SQL active được đọc theo thứ tự bốn file nguồn và hiện có 15 bảng, 43 quyền,
12 thủ tục RBAC và 4 hàm lương. Live DB read-only cho thấy 6 role (một role legacy ngoài fresh
matrix) và `db:show --counts` bị chặn bởi thiếu
`performance_schema.session_status`; không dùng live DB để sửa hoặc tạo routine.
Các giới hạn này thay thế số liệu lịch sử bên dưới, không xóa lịch sử.
Ma trận quyền tách riêng `Luong.*` và `HeSoLuong.*`: role Kế toán có
`Luong.Read` nhưng không có `HeSoLuong.Read`; Quản trị và Nhân sự mới có các
quyền hệ số lương active. Batch Chấm công yêu cầu đồng thời Insert, Update và
Delete vì `so_gio_lam=-1` có semantics xóa.

## Lịch sử canonical Nghỉ phép/sidebar — 2026-09-06 (trước quyền Approve)

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

SQL fresh active phải chạy theo thứ tự `database/sql/tao_bang.sql` → `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql` → `database/sql/salary/2026_09_09_001_luong_functions.sql` trên database rỗng/disposable đã được phê duyệt, hoặc dùng snapshot destructive `quan_ly_nhan_vien_session_update.sql`. Hợp đồng có đúng 15 bảng, 19 nhân viên, 43 quyền, 12 thủ tục RBAC và 4 hàm lương. `quan_ly_nhan_su.session.sql`, `LocalDemoSeeder` và script SQL employee cũ chỉ để đối chiếu lịch sử.

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
| Lương | UI/API prototype | 4 hàm lương tương thích đã có trong nguồn fresh/additive; workflow, browser và production evidence còn thiếu | **Prototype** |
| Chấm công | UI/API prototype | Lookup gọi `sp_phong_ban_danh_sach`; update gọi `sp_cham_cong_cap_nhat`; cả hai thiếu trong active SQL sources | **Prototype — blocked** |
| Nghỉ phép | UI/API với approval theo quyền riêng | Query Builder companywide, transaction/row lock/conditional update; MariaDB/browser chưa chạy trong lát cắt này | **Verified hẹp** |
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
- Lương: `LuongRepository@all` hiện dùng Query Builder trực tiếp và gọi bốn hàm
  lương canonical (`fn_so_ngay_cong_chuan`, `fn_so_ngay_cong_thuc_te`,
  `fn_tinh_luong_thuc_nhan`, `fn_thong_bao_tinh_luong`). Salary listing vẫn là
  prototype và cần workflow/browser acceptance riêng; không còn blocker do
  thiếu `sp_luong_tim_kiem_phan_trang`.
- Chấm công: `ChamCongController` lookup gọi `sp_phong_ban_danh_sach`, update gọi `sp_cham_cong_cap_nhat`; các procedure không tồn tại trong SQL active. Không sửa caller trong task Phòng ban/Nhân viên.
- Nghỉ phép: approval hiện dùng Query Builder companywide và quyền `NghiPhep.Approve`; chưa có MariaDB/browser acceptance mới.
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
