# Handoff tiếp tục `quanlynhansu`

## Current verified slice: Self-history Nghỉ phép cho actor Insert — 2026-09-10

Trang `/tao-nghi-phep` tải log cá nhân qua `GET /api/v1/nghi-phep/cua-toi`;
route đứng trước `apiResource`, có `web`, `auth` và `can:NghiPhep.Insert`.
Controller lấy mã nhân viên từ authenticated user, chỉ nhận mã canonical 5 chữ
số, luôn ghi đè `ma_nv` client và fail closed `403` nếu mã không hợp lệ. Endpoint danh sách chung vẫn yêu cầu
`NghiPhep.Read`. Frontend không gửi `ma_nv` khi tải log; Update/Delete vẫn dùng
permission riêng.

RED: self request bị resource `{id}` bắt; frontend dùng Read và query
`ma_nv`; malformed identity còn gọi service. GREEN: targeted PHP `8 tests, 38 assertions`; frontend `5/5`; leave
regression `28 tests, 155 assertions`; full Laravel `519 passed, 4142
assertions`; frontend script `96/96`; Vite `31 modules`; route inventory `98`,
duplicate name/signature `0`; Composer, PHP lint và `git diff --check` pass.
Chưa browser mutation, MariaDB disposable hoặc live DB; role matrix live chưa
xác minh.

## Current verified UI slice: Dọn action header Nghỉ phép và audit avatar — 2026-09-09

Header `/nghi-phep` đã bỏ đúng hai action `Lịch nghỉ` (`#calendar-btn`) và
`Thêm nghỉ phép` (`#create-btn`); tab `Lịch sử nghỉ phép`, bộ lọc Từ ngày/Đến
ngày và nút `Xem tất cả lịch nghỉ` (`#all-leaves-btn`) vẫn giữ. `nghiphep.js`
đã bỏ lookup/listener hai control và dòng `elements.createButton.disabled`; modal
Sửa, quyền Insert và trang tạo self-service không đổi.

RED: leave contract `1 failed, 13 passed` vì DOM/JS còn action header. GREEN:
targeted leave `30/30`, toàn bộ `tests/Frontend` enumeration `145/145`, npm
script `91/91`; full Laravel `506 passed, 4006 assertions`; Vite `31 modules`;
route except-vendor `95`, duplicate name/signature `0`; Composer, PHP lint và
`git diff --check` pass. Chrome read-only xác nhận `/nghi-phep` header chỉ còn
title/description, không có `Lịch nghỉ`/`Thêm nghỉ phép`; tab `Lịch sử nghỉ phép`
vẫn mở, filter Từ ngày/Đến ngày là `2026-06-09` đến `2026-09-09`, nút `Xem tất
cả lịch nghỉ` vẫn còn. Console không có error/warning, chỉ informational
Employee paging API log.

Avatar audit read-only: DB lưu relative owned path; public disk dùng `/storage`
hoặc `PUBLIC_STORAGE_URL`; auth projection/topbar/list/show dùng
`Storage::disk('public')->url`. Avatar tests `4 passed, 12 assertions`.
Local `public/storage` là junction tới `storage/app/public`, không tracked.
Fresh clone/pull cần `php artisan storage:link`; khi `.env` hoặc config cache cũ,
chạy `php artisan config:clear`. Chrome read-only avatar DOM/topbar có `src`
`/storage/nhan-vien/avatars/8cdb3974-4702-4427-8053-118818de85d3.jpg`,
`complete=true`, `naturalWidth=2048`, `naturalHeight=1362`. Không submit,
mutation, upload hoặc symlink change; chưa multi-role, responsive hoặc
fresh-clone acceptance.

## Current verified UI slice: Popup tạo Hợp đồng — 2026-09-09

`Thêm hợp đồng` tại `/hop-dong` dùng trigger modal có `href` fallback thật;
GET `/hop-dong/create` nhận `X-Create-Modal: 1`/`X-Form-Modal: create` để trả
partial form, còn request thường giữ trang đầy đủ. Form động giữ CSRF, field,
native datepicker với value ISO `Y-m-d`, expiry/salary binding; backend vẫn
chấp nhận cả ISO native ở POST/PUT thường và JSON lẫn legacy `dd/mm/yyyy`, đồng
thời từ chối ngày không hợp lệ/mơ hồ. POST AJAX trả success `201`, validation
`422` hoặc lỗi an toàn `500`, rồi reload list khi thành công. Lương lọc chữ số
và nhóm dấu chấm permissive khi gõ liên tục, submit vẫn gửi canonical digits
với giới hạn server/DB 18 chữ số. Sửa hợp đồng cũng mở cùng dialog với `href`
thật làm fallback, partial PUT và JSON update. Không đổi route, SQL/DB hay
delete behavior; modal chỉ render khi có `HopDong.Insert` hoặc `HopDong.Update`.

RED: regression gõ salary tuần tự và edit-modal contract failed (frontend
`2 failed`; feature `6 failed`). GREEN: focused Hợp đồng/date/modal `30 passed,
262 assertions`; frontend enumeration `144/144`; npm script `90/90`; Vite
`31 modules`; full Laravel `506/4011 assertions`; PHP lint và
`git diff --check` pass. Chrome read-only xác nhận create modal giữ URL
`/hop-dong`; salary gõ tuần tự `1234` hiển thị `1.234`, thêm `5` thành
`12.345`, tiếp tục đủ 18 digit thành `123.456.789.012.345.678`; `Hủy` đóng
mà không submit, direct `/hop-dong/create` vẫn là full-page fallback. Edit
contract 18 click từ list giữ URL `/hop-dong`, dialog title `Chỉnh sửa hợp đồng`,
employee `00018 Hoàng Đức Long`, loại finite, date pickers/prefill ISO
`2019-02-15` và `2028-12-21`, salary prefill `2.350.000`; select all gõ `1234`
rồi `5` thành `12.345`, `Hủy` đóng dialog. Console logs `[]`; không
submit/mutation DB live; chưa kiểm tra multi-role/responsive.

## Current verified UI slice: Popup recovery controls — 2026-09-09

Đã xóa `Mở trang đầy đủ` và `Thử lại` khỏi shared modal Phòng ban/Chức vụ và
modal Nhân viên. JS controller không còn recovery/fallback/retry; alert lỗi và
Đóng vẫn hoạt động, còn real `href` của trigger create/edit ngoài popup được
giữ nguyên.

RED: 25 test, `22 pass / 3 fail`. Fresh: targeted Node `25/25`; targeted
Laravel `72/72`, `713 assertions`; all frontend `139/139`; npm script `90/90`;
full Laravel `492/3906 assertions`; Vite `31 modules`. Chrome read-only xác
nhận create/edit Nhân viên/PB/CV giữ URL list, hai nhãn absent, form/title/close
đúng, console errors `[]`. Chưa submit form/mutation DB và chưa có matrix
multi-role/responsive.

## Current verified UI slice: Lương — 2026-09-09

UI Lương dùng màu `btn-primary` cho Thêm hệ số lương và các nút Lưu; footer
popup icon–chữ dùng `d-inline-flex align-items-center gap-2`. Click dòng hoặc
Enter/Space chọn nhân viên để tải hệ số, còn button/link/input không hijack
selection. Form tạo/sửa hệ số dùng native datepicker ISO; popup Nghỉ phép,
Chấm công, modal dùng chung, Chức vụ và form Nhân viên đã được rà/sửa spacing.

Evidence fresh: all frontend `138/138`; `npm run test:frontend` `89/89`;
Laravel `492/3906 assertions`; Vite `31 modules`; routes `95`, duplicate
name/signature `0`; Composer, lint và `git diff --check` pass. Chrome
read-only: click `00002`, keyboard `00004`, coefficient button cũ `0`, tạo
lương giữ dòng đã chọn, add/save `rgb(13,110,253)`, gap `8px`, native picker,
prefill `2019-05-28/2027-05-27`, console errors `[]`. Chưa submit form/mutation
DB và chưa kiểm tra browser matrix nhiều role/responsive.

## Current verified UI slice: Hợp đồng, Lương và Nghỉ phép — 2026-09-09

Hợp đồng hiện hiển thị mã nhân viên (không còn cột `#` và không lộ `ma_hd`);
mã `00021` giữ leading zero. Bảng chi tiết kỳ lương chỉ hiển thị tên + mã
nhân viên, không có initials/avatar theo dòng. Nghỉ phép history mặc định
companywide theo khoảng ngày server-seeded, filter ngày chỉ hiện ở tab history,
request pending không nhận history filters, bảng có 8 cột và action theo dòng;
`NghiPhep.Approve` không làm actor Read+Approve thành chỉ xem.

Verification hiện tại: Laravel `492 passed, 3905 assertions`; frontend qua
`rg --files` `133/133`; `npm run test:frontend` `85/85`; Vite `31 modules`;
route `95`, duplicate name/signature `0`; Composer, PHP lint và
`git diff --check` pass. Chrome read-only: `/hop-dong` header không có `#`, mã
đầu là `00021`; `/luong` salary detail không có initials/avatar; `/nghi-phep`
history không chọn nhân viên mặc định `2026-06-09..2026-09-09` trả `2`, lọc
`2026-09-05..2026-09-09` trả `1`, “Xem tất cả lịch nghỉ” bỏ scope nhân viên
nhưng giữ kết quả; Sửa/Xóa theo dòng hiển thị. Pending total `0` nên chưa có
approve row để xác minh live ở lượt này; contract tests bao phủ permission/
status. Browser không có error (chỉ informational employee paging logs ở
Nghỉ phép; salary empty). Không mutation.

## Quyền duyệt Nghỉ phép và filter Lương — 2026-09-09

Lát cắt mới thêm `NghiPhep.Approve` id 43 (`Duyet`, custom action), cập nhật
fresh `du_lieu_mau.sql`/AUTO_INCREMENT 44 và grant mặc định role 1, 4. Script
additive `database/sql/rbac/2026_09_09_001_add_nghiphep_approve_permission.sql`
idempotent, dừng fail-closed khi collision id/symbol và chỉ grant role nếu role
tồn tại. Đã chạy trên schema `quan_ly_nhan_su` bằng MariaDB `10.4.32`, exact
execution qua MariaDB client exit `0`, không backup theo chỉ định user.

Approval API list cần Read + Approve, PATCH chỉ cần Approve; không còn
department-manager/role/phòng ban, list/count/duyet companywide. Dashboard
dùng `pending_leave_count`; UI Nghỉ phép kiểm tra Approve độc lập với Update
để radio/nút Duyệt hoạt động cho actor Read + Approve. Generic Update vẫn
prohibit trạng thái duyệt và service giữ transaction + lock + conditional update.
Icon kính lúp cạnh input tìm nhân viên của Lương đã bỏ, label/input vẫn giữ.

Fresh root verification: Laravel `491 passed, 3902 assertions`; frontend
`77/77`; Vite `31 modules`; route except-vendor `95`, duplicate
name/signature `0`; Composer, PHP lint và `git diff --check` pass. Browser
read-only xác nhận filter Lương không còn kính lúp. Postcheck sau rollout xác
nhận `permission_count=43`, `max=43`, đúng một row canonical id 43/symbol/label/
module, grants đúng role 1 và 4, temporary routine count `0`. Browser
read-only xác nhận checkbox permission-43 checked ở role 1 và nút Duyệt xuất
hiện trên `/nghi-phep`.

## Đồng bộ avatar và UI dùng chung — 2026-09-08

Đã hoàn tất lát cắt avatar/UI: public disk mặc định dùng URL tương đối
`/storage` và hỗ trợ `PUBLIC_STORAGE_URL`; auth projection hydrate
`anh_dai_dien`; topbar dùng `Storage::disk('public')->url()` với fallback
initials, ảnh tròn `object-fit: cover`; README quick start yêu cầu
`php artisan storage:link`; Composer `setup` cũng gọi lệnh này nhưng không
track symlink. Mọi control Sửa trong
phạm vi các module đã dùng `btn-outline-primary` + `bi-pencil-square`; sidebar
đã bỏ riêng Thêm chức vụ và Danh sách hệ số lương, group Lương chỉ hiện khi có
`Luong.Read`. Nghỉ phép dùng native `type=date`, payload/edit prefill ISO,
bảng vẫn hiển thị dd/mm/yyyy; kính lúp tìm nhân viên đã bỏ ở Chấm công/Nghỉ
phép; nút header Thêm thông tin lương dùng `btn-primary`.

RED trước implementation: targeted PHP `3 failed/4 tests` (avatar URL,
projection/topbar) và frontend `7 failed/16 tests` (date/edit/sidebar/filter/
salary contracts). GREEN targeted: PHP `33 passed, 237 assertions`, frontend
`76/76`; full Laravel `487 passed, 3870 assertions`; Vite build `31 modules`;
route inventory `95`; Composer, PHP lint và `git diff --check` pass. Không có
DB/live browser mutation; browser/clone mới chỉ có hướng dẫn storage:link,
chưa claim acceptance file chooser.

## Modal CRUD Nhân viên/Phòng ban/Chức vụ — 2026-09-08

Đã chuyển nút Tạo và Sửa trên ba trang index sang native dialog khi JavaScript
hoạt động, đồng thời giữ nguyên href và các trang create/edit đầy đủ làm
fallback. Nút bút chì trực tiếp của Nhân viên có `data-employee-edit-trigger`;
các Gate Create/Edit quyết định trigger và dialog được render. Controller create
trả partial khi có modal header; store AJAX trả JSON success/422/lỗi server an
toàn, còn request thường vẫn redirect như trước. Form Nhân viên giữ wizard,
CSRF, method spoofing, FormData và avatar; Phòng ban/Chức vụ dùng shared simple
modal. Không đổi route, service/repository, DB hoặc Gate; validation nghiệp vụ
chỉ được điều chỉnh riêng ở contract địa chỉ bên dưới.

RED trước implementation: 5 targeted PHP test failures vì thiếu create hook,
partial và AJAX store contract. GREEN hiện tại: targeted PHP ba module
`73 passed, 711 assertions`; full Laravel `476 passed, 3808 assertions`;
`npm run test:frontend` `57/57`; Vite `31 modules transformed`; route list
`95` routes; Composer, PHP lint và `git diff --check` pass. Chrome/CUA read-only
đã xác nhận cả sáu trigger Tạo/Sửa mở đúng dialog trên `/nhan-vien`,
`/phong-ban` và `/chuc-vu` mà không đổi URL danh sách; không submit form hoặc
mutation DB live. File untracked của người dùng vẫn được giữ.

## Tinh chỉnh popup Nhân viên — 2026-09-08

Trang xem chi tiết không còn render trigger reset mật khẩu kể cả khi actor có
`NhanVien.ResetPassword`; route/controller/service reset vẫn giữ nguyên, còn
index và trang edit đầy đủ vẫn dùng action hiện hành. Popup edit truyền context
`modalOnly` tường minh: ẩn riêng trường Quận/Huyện, trong khi create và full-page
edit vẫn giữ trường; review Ngày vào làm hiển thị `dd/mm/yyyy` nhưng input và
submission vẫn ISO. District hiện có được bảo toàn trong FormData mà không
render label/input. Formatter frontend chạy lại trên input/change nên ngày vừa
đổi cũng cập nhật đúng review.

RED mới: 3 test hành vi fail (show còn reset, modal còn Quận/Huyện/ISO, Node
thiếu formatter). GREEN: targeted show/update/reset/create `41 passed, 427
assertions`; Node wizard/modal `17/17`; full Laravel `478 passed, 3829
assertions`; `npm run test:frontend` `59/59`; Vite `31 modules transformed`;
route `95`; Composer, PHP lint và `git diff --check` pass. Chưa chạy browser
mutation; không đổi route, service/repository hay database.

## Đồng bộ popup Tạo Nhân viên — 2026-09-08

Popup Tạo truyền context `modalOnly` vào `create-form`: chỉ popup ẩn trường
Quận/Huyện, còn `/nhan-vien/create` đầy đủ vẫn render trường này để fallback.
Review Ngày vào làm trong popup hiển thị `dd/mm/yyyy` cả giá trị old ban đầu
và sau khi người dùng đổi input; input/submission vẫn giữ ISO `yyyy-mm-dd` nhờ
formatter dùng chung trên input/change. Create không có reset-password nên
không thêm action mới; route/controller/service/repository/DB không đổi. Request
validation đổi riêng semantics địa chỉ: ba trường hiển thị all-or-none,
`quan_huyen` nullable tùy chọn, không synthesize giá trị.

RED: 2 feature tests và 1 frontend contract test fail trước sửa đúng vì create
chưa truyền context/formatter. GREEN focused: PHP create behavior `2 tests,
18 assertions` và Node wizard `6/6`. Verification trên HEAD hiện tại: full
Laravel `480 passed, 3847 assertions`; `npm run test:frontend` `60/60`; Vite
`31 modules transformed`; route inventory `95`; Composer, PHP lint và
`git diff --check` pass. Không browser mutation hoặc DB write.

## Contract địa chỉ cho popup Tạo — 2026-09-08

`StoreNhanVienRequest::after()` hiện chỉ kiểm tra ba trường hiển thị
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
`git diff --check` pass. Không browser mutation hoặc DB write.

## Full module/role audit handoff — 2026-09-06

Đã hoàn tất audit các module/routes được giao trên HEAD
`074d65eba9f8653aa2c849d58746f056518da068` (branch `main`), giữ nguyên file
untracked người dùng và không commit/push/fetch/merge/rebase. Đã sửa exact
middleware cho API Chấm công (export/template/import/batch) và Lương
(phòng-ban/chức-vụ/export), đồng thời sửa contract date, filter explicit
submit, paginator/delete, leave approval PATCH và sidebar state. Regression
tests hiện có bao phủ các behavior này; route permission map được mở rộng
trong `ContentFourManagementTest`.

Verification mới nhất: `php artisan test` `470 passed, 3758 assertions`; all
frontend tests `112/112`; `npm run test:frontend` `55/55`; `npm run build` pass
với 31 modules transformed; route `98`, duplicate name/signature `0`; Composer,
PHP lint và `git diff --check` pass. Browser CUA fresh desktop read-only đã
smoke đủ role `00001` Quản trị, `00004` Nhân sự, `00006` Kế toán, `00005`
Trưởng phòng, `00007` Nhân viên; allow/deny và console đã được kiểm tra.
Mobile representative `375x812` tại `/duyet-nghi-phep` là snapshot lịch sử
trước khi canonical hóa route; không còn là route hiện hành. Network waterfall
và browser mutation vẫn `unverified`; mutation không chạy để tránh ghi DB hiện
hữu.

DB evidence: bốn SQL active theo thứ tự `tao_bang.sql` → `du_lieu_mau.sql` →
`quyen_vai_tro.sql` → `salary/2026_09_09_001_luong_functions.sql` có contract
15 bảng/43 quyền/12 thủ tục RBAC + 4 hàm lương. Live DB được đọc
role/permission metadata; có 6 role do legacy drift và
`php artisan db:show --counts` bị thiếu `performance_schema.session_status`.
Không thêm routine/view và chưa có MariaDB disposable guard để chạy mutation.
Xem [FULL_MODULE_ROLE_AUDIT_2026-09-05.md](FULL_MODULE_ROLE_AUDIT_2026-09-05.md)
cho role matrix, issue severity/root cause, browser evidence và giới hạn.
Lưu ý authorization: `Luong.*` chỉ bảo vệ lương; các route hệ số dùng
`HeSoLuong.Read/Insert/Update/Delete`. Kế toán chỉ có `Luong.Read`, không có
`HeSoLuong.Read`. Batch Chấm công yêu cầu thêm `ChamCong.Delete` vì giá trị
`so_gio_lam=-1` xóa bản ghi.

## Lịch sử canonical nghỉ phép và sidebar — 2026-09-06 (trước quyền Approve)

Luồng duyệt nghỉ phép hiện dùng bảng trong `/nghi-phep`, section ổn định
`#leave-table-card`; card Dashboard của Trưởng phòng đủ `NghiPhep.Read` và
`NghiPhep.Update` trỏ tới anchor này. Route web độc lập
`/duyet-nghi-phep` và item sidebar riêng đã bị gỡ khỏi route/navigation
canonical; view/JS legacy vẫn giữ để không mở rộng phạm vi xóa.

Approval action chỉ render cho Gate `department-manager` (role 4 có `ma_pb`)
và `NghiPhep.Update`; PATCH nhận duy nhất `trang_thai_duyet`, còn controller
lấy phòng ban từ actor và service khóa đơn pending cùng phòng ban. Listing
manager ép scope server-side và trả 403 khi thiếu phòng ban hợp lệ. Dashboard
dùng `NghiPhepService::countPendingForDepartment()` cùng semantics pending của
bảng, không lọc terminal riêng; badge bảng dùng `counts.pending` server-side
thay vì số dòng của trang hiện tại.

Generic PUT/PATCH không còn nhận `trang_thai_duyet`: request dùng rule
`prohibited`, service chỉ allowlist các field chỉnh sửa và không bao giờ ghi
trạng thái. Trạng thái chỉ đổi qua PATCH `/{ma_np}/duyet`; select
`lockForUpdate()` và conditional update chạy chung trong transaction. Luồng
refresh khi đang xem History vẫn tải pending bằng `tab=pending` riêng để badge
không bị lệch theo tab hiện tại; anchor được cuộn lại sau khi dữ liệu làm đầy
bảng.

Sidebar active server-render có `data-submenu-ready="initial"`; state session
được áp dụng instant trước frame đầu, chỉ click accordion của người dùng mới
animate. Fragment `#leave-table-card` được khôi phục sau khi section được
reveal bởi auth/permission.

Browser CUA fresh read-only đã xác nhận Trưởng phòng nhìn thấy card count `0`,
click tới đúng `/nghi-phep#leave-table-card`, badge pending cũng `0`, section
nằm trong viewport, nút Duyệt hiện và submenu Nghỉ phép mở sẵn; console sạch.
Nhân viên không thấy card/pending-count element nhưng Dashboard vẫn tải `20`
nhân viên, không alert hoặc console error. Viewport `375x812` tại route
canonical không overflow document; table giữ horizontal scroll trong card.
Không chạy approval mutation trên database hiện hữu.

## Handoff hòa giải/UI 2026-09-05

Đã hòa giải các tính năng incoming và hồi quy UI trên HEAD `ce22524ef245ea24e4365ef830d822a1a247d9a6` mà không đổi route/controller nghiệp vụ ngoài phạm vi cần thiết, không tạo procedure/view và không mutation DB. Employee attendance lookup dùng `NhanVienServiceContract::paginateForAttendance` với Query Builder active, lọc `so_dong` và lỗi public an toàn; import/date, Hợp đồng, Lương, Dashboard, Vai trò, pagination/shared UI, action trực tiếp CV/PB/HĐ, date-field hệ số, guard script, auth role hydration và dashboard display dates đã có targeted contract xanh.

Verification mới: full Laravel `456 passed, 3655 assertions`; targeted PHP root rerun `19 tests, 249 assertions` pass; shared pagination Node `4/4` pass; frontend `38/38` pass; Vite `29 modules transformed`; route inventory `96`, duplicate signature/name `0`; Composer validate, PHP lint và `git diff --check` pass. Chrome fresh read-only ngày 2026-09-05 đã xác nhận `/vai-tro` main `1320` và card `1296` theo module chuẩn, `/luong` chỉ một nút `Đặt lại`, identifiers `ma_pb`/`so_nhan_vien` ở `/phong-ban` transparent không nền, paginator tại `/vai-tro`, `/luong`, `/nghi-phep`, `/cham-cong` center delta `0` với page-link `44x44`, radius `10px`, active `rgb(233,69,96)` và không document overflow ở viewport desktop hiện tại; screenshot visual review pass. Không claim responsive browser mới hoặc mọi interaction; không có browser mutation/DB write.

> Cập nhật 2026-08-27 trên local `main`, HEAD `f71c0b20a4e04e8e2ec32cdad2a68722e4aaa0b7`. Chỉ có thay đổi local của lát cắt Nhân viên và tài liệu; file `AIAssistantInput-a1d28494-8caf-4d5a-8217-4d71fad94b75.chatInput` là untracked của người dùng và phải giữ nguyên.

## Nguồn sự thật và ownership

- SQL fresh active là `database/sql/tao_bang.sql` → `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql` → `database/sql/salary/2026_09_09_001_luong_functions.sql`. Hợp đồng gồm 15 bảng, 19 nhân viên, 43 quyền, 12 thủ tục RBAC và 4 hàm lương; snapshot root `quan_ly_nhan_vien_session_update.sql` là artifact destructive, còn các dump/script khác là lịch sử.
- Nhóm hiện chỉ sở hữu code Nhân viên, Phòng ban và Chức vụ. Code Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC và API của đồng nghiệp chỉ được note, không tự sửa nếu chưa có task giao rõ.
- Không fetch, merge, rebase, cherry-pick, push, commit hoặc mutation database live trong phiên này. Không stage `docs/CODEX_FRONTEND_HANDOFF.md`.
- Khi tài liệu khác code hoặc database live, ưu tiên bằng chứng live rồi cập nhật tài liệu.

## Thay đổi Nhân viên trong phiên này

`App\Support\NhanVienScope` giữ policy tại ranh giới HTTP, không nhúng auth vào repository:

- Role `NhanVienRole::DepartmentManager` phải có `ma_pb` là số dương hợp lệ; thiếu/sai thì list rỗng an toàn và target trả 404.
- List luôn ép `ma_pb` bằng phòng ban actor, bỏ qua filter phòng ban do client gửi; lookup phòng ban trên UI chỉ còn phòng ban của actor.
- Show/edit/destroy kiểm tra target sau khi lookup; cross-department trả 404, không gọi mutation. `UpdateNhanVienRequest::authorize()` cũng kiểm tra target trước validation/mutation.
- Destroy chặn tự xóa trước khi gọi service bằng lỗi ổn định `Không thể tự xóa tài khoản đang đăng nhập.`. Index/show/edit và partial action không render nút phá hủy cho chính actor.
- Auth projection/repository hydrate `ma_pb`, giúp department scope tồn tại sau login/session restore.

## Bằng chứng đã chạy

- RED trước implementation: test scope mới fail vì thiếu `NhanVienScope`, auth model chưa hydrate `ma_pb`, manager list vẫn nhận `ma_pb` client và các target/self guards chưa có.
- Focused GREEN: `php artisan test tests/Unit/Support/NhanVienScopeTest.php tests/Feature/Backend/NhanVien/NhanVienDepartmentScopeTest.php tests/Unit/Models/NhanVienTest.php` → `14 tests, 66 assertions` pass.
- Employee/auth/regression slice → `110 tests, 1308 assertions` pass.
- Full Laravel sau thay đổi → `288 tests, 2222 assertions` pass.
- Phòng ban và Chức vụ không sửa code; các feature tests của hai module pass trong full suite.
- Route inventory → `79` route, command `php artisan route:list --except-vendor` pass.
- Frontend → `18` Node tests pass; Vite build pass với `19 modules transformed`.
- Composer → `composer validate --no-check-publish` pass; PHP lint các file sửa và `git diff --check` pass.
- Guarded MariaDB trên schema disposable → `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests, 422 assertions`, `10.797s`, exit `0`.
- Chưa chạy browser acceptance trong phiên này; MariaDB disposable không phải bằng chứng cho database live hoặc production.

## Trạng thái module

| Module | Trạng thái | Ghi chú |
| --- | --- | --- |
| Nhân viên | Verified hẹp | CRUD/lifecycle/auth/RBAC và scope Trưởng phòng có automated evidence; browser/production chưa claim |
| Phòng ban | Verified hẹp | Direct Query Builder, transaction/row lock và Gate canonical; code không đổi phiên này, browser chưa claim |
| Chức vụ | Verified hẹp | Direct Query Builder, transaction/row lock và Gate canonical; code không đổi phiên này, browser chưa claim |
| Dashboard | Prototype | Chỉ ghi nhận auth/permission riêng; không sửa trong scope |
| Lương | Prototype | Fresh/additive salary source cung cấp 4 function tương thích; workflow/browser/production evidence còn thiếu |
| Chấm công | Prototype/blocked | Lookup gọi `sp_phong_ban_danh_sach`, update gọi `sp_cham_cong_cap_nhat`; không có trong active SQL sources |
| Nghỉ phép | Verified hẹp | Approval dùng Query Builder companywide; không gọi `sp_nghi_phep_duyet_phep` |
| Hợp đồng | Planned/scaffold | Chưa có mutation/browser evidence đủ |
| Vai trò/Phân quyền/RBAC | Nền tảng verified hẹp | Catalog và 12 RBAC procedure active có test hẹp; UI quản trị/mutation/browser chưa đóng |

Model/validation legacy drift, API naming và exception contract của module ngoài ownership vẫn là backlog; không sửa để làm sạch ngoài phạm vi.

## Lệnh tiếp tục

```powershell
git status --short --branch
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
git diff --check
```

MariaDB chỉ chạy bằng wrapper guarded trên disposable target:

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
```

Không claim MySQL 8, production rollout, browser acceptance hoặc DB live mutation nếu chưa có bằng chứng và approval riêng.

## Handoff modal sửa Nhân viên 2026-08-29

`/nhan-vien` hiện render một native dialog duy nhất khi actor có `NhanVien.Update`; mỗi action `Chỉnh sửa` giữ href edit thật làm progressive fallback và chỉ tải partial form khi mở. GET edit partial vẫn qua Gate và `NhanVienScope`; trang edit đầy đủ không đổi contract. Submit modal gửi `FormData` tới PUT/PATCH hiện hữu, hỗ trợ avatar, khóa nút khi đang gửi, khóa nút đóng/cancel/Escape để không nhận response lệch modal, hiển thị lỗi 422 theo field hoặc form-level khi khóa lỗi không map được, lỗi mạng/server an toàn, khôi phục focus khi đóng và reload đúng URL list hiện tại sau JSON success. Shared `row-action-select` chỉ thêm callback `modal`, nên Xem/Xóa và CV/PB giữ nguyên.

RED/GREEN: feature modal/update ban đầu fail vì thiếu shell/partial/JSON; các hồi quy listener submit, khóa đóng/cancel/Escape và form-level 422 cũng đã RED trước khi sửa, sau triển khai targeted Nhân viên `32 tests, 309 assertions` pass và Node modal/shared/list `16 tests` pass. Relevant Nhân viên/auth/service/unit suite pass `177 tests, 1518 assertions`; full Laravel hiện `290 passed, 11 failed, 2252 assertions` do các failure baseline ngoài ownership ở ContentFour/Chấm công/Nghỉ phép. Full frontend `31 passed, 1 failed` do đúng baseline Nghỉ phép `expected —, actual -`; build pass `25 modules transformed`, route inventory pass `89 routes`, Composer/PHP lint/diff hygiene pass. Browser chưa chạy; không sửa lỗi ngoài ownership.

## Handoff feedback UI 2026-08-28

Đã triển khai feedback thuộc ownership trong `docs/FEEDBACK_ACTION_PLAN.md`: Be Vietnam Pro, nhãn sidebar, pagination/filter/action select cho Chức vụ/Phòng ban/Nhân viên, grouped employee detail với avatar lớn và form edit accessible. CV/PB dùng Query Builder `paginate()` nhưng giữ `all()`; delete action chỉ submit sau confirm và Gate/guard hiện hữu. Không sửa Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, RBAC hoặc schema.

Verification cuối phiên: focused module/regression `53 tests, 481 assertions` và repository pagination `14 tests, 64 assertions` pass; full Laravel `294 tests, 2287 assertions` pass; frontend `21/21` pass; Vite `21 modules transformed`; route inventory `79`, Composer, PHP lint và `git diff --check` pass. MariaDB disposable: `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; đây không phải database live. Browser acceptance, font/network thật và production chưa được kiểm chứng.

## Handoff modal Phòng ban/Chức vụ và liên kết edit Nhân viên 2026-08-29

Phòng ban và Chức vụ dùng native dialog chung, chỉ tải partial form khi chọn Sửa; href edit thật vẫn là progressive fallback. Controller nhận header modal để trả partial, còn direct GET trả trang đầy đủ; update JSON success/422/lỗi server chỉ dùng thông báo an toàn. Gate canonical, CSRF, FormRequest, Query Builder, transaction/row lock và delete/view/filter behavior không đổi. row-action-select chỉ gọi callback modal, không điều hướng khi mở modal.

Trang xem Nhân viên thêm trigger có href thật và dùng lại shell data-employee-edit-modal hiện có khi actor có Gate cập nhật; no-JavaScript/direct-link vẫn dùng edit page. Step 3 form bọc từng cặp dữ liệu trong employee-review-row để border liên tục và responsive theo một cột ở màn hình hẹp.

TDD/verification: RED feature 8 failure và Node 2 failure trước implementation; GREEN targeted PB/CV/Nhân viên 51 tests, 487 assertions, targeted Node core 25 tests pass. Full Laravel hiện 298 passed, 11 failed, 2321 assertions do baseline ngoài ownership; frontend 36 passed, 1 failed do tests/Frontend/nghiphep/employee-response.test.js expected —, actual -. Vite build 26 modules transformed; route inventory 89 routes; Composer, PHP lint controller và git diff --check pass. Browser chưa kiểm chứng, MariaDB không chạy vì data layer không đổi; không claim database live/production.
