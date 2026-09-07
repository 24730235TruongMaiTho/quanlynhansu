# Full module và role audit — 2026-09-05

## Cập nhật canonical Nghỉ phép/sidebar — 2026-09-06

Sau audit, route web độc lập `/duyet-nghi-phep` đã bị gỡ khỏi route map và
sidebar. Bảng duyệt canonical nằm trong `/nghi-phep#leave-table-card`; card
Dashboard chỉ hiện cho actor đủ `NghiPhep.Read` + `NghiPhep.Update` và Gate
`department-manager`. Nút Duyệt là manager-only, PATCH chỉ gửi trạng thái;
controller/service lấy và kiểm tra phòng ban từ authenticated actor, không tin
`ma_nv`/`ma_pb` client. Count Dashboard gọi
`NghiPhepService::countPendingForDepartment()` cùng semantics pending của bảng,
badge dùng tổng server trả về thay vì số dòng trang hiện tại.

Các dòng browser evidence `/duyet-nghi-phep` bên dưới là snapshot lịch sử trước
việc canonical hóa; không còn là route hiện hành. Sidebar active/restored
submenu hiện visible ngay first paint, chỉ thao tác accordion của người dùng
mới chạy animation; fragment anchor được khôi phục sau khi bảng reveal.

Fresh CUA read-only trên route canonical xác nhận card Trưởng phòng `0` khớp
badge pending `0`, click đưa section vào viewport, nút Duyệt hiện, submenu mở
sẵn và console sạch. Nhân viên không thấy card nhưng Dashboard vẫn tải bình
thường, không alert/console error. Mobile `375x812` không document overflow và
table giữ horizontal scroll nội bộ. Không thực hiện approval mutation.

Generic update status bị cấm ở FormRequest và bị loại khỏi allowlist service;
edit processed không thể reopen. PATCH duyệt là đường duy nhất đổi trạng thái,
với `lockForUpdate()` và conditional update trong cùng transaction. Pending và
history loader gửi tab cố định; fragment anchor restore chạy lại sau khi data
render để bù layout shift. Dashboard inline renderer null-safe khi card bị
omit theo quyền.

## Kết luận điều hành

Audit được thực hiện trên worktree hiện tại của `main`, HEAD
`074d65eba9f8653aa2c849d58746f056518da068`. Các lỗ hổng authorization ở
API Chấm công và Lương, cùng các contract UI/API của Chấm công, Lương, Nghỉ
phép và Hợp đồng đã được sửa và có regression test. Full Laravel hiện xanh
`470 passed, 3758 assertions`; frontend package `55/55` và toàn bộ test file
frontend `112/112` đều pass. Không mutation database live và không commit,
push, fetch, merge, rebase hoặc tạo worktree.

## Phạm vi, nguồn sự thật và môi trường

- Repository: `C:\Users\Aster\IdeaProjects\quanlynhansu`; branch `main`;
  HEAD như trên; chỉ có file untracked người dùng
  `AIAssistantInput-a1d28494-8caf-4d5a-8217-4d71fad94b75.chatInput` ngoài các
  file sửa của audit.
- SQL active được đọc đúng thứ tự: `database/sql/tao_bang.sql` →
  `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql`. Contract
  gồm 15 bảng, 42 quyền active và 12 routine được khai báo trong nguồn fresh.
  Không tạo routine/view mới.
- Tài khoản role smoke: `00001` Quản trị, `00004` Nhân sự, `00006` Kế toán,
  `00005` Trưởng phòng, `00007` Nhân viên. Password chỉ được dùng trong form
  đăng nhập kiểm thử, không ghi vào tài liệu/log.
- Server local read-only/browser: `http://127.0.0.1:8000`. Test DB SQLite in
  memory; mọi test mutation chạy trên test database. DB live chỉ được đọc
  role/permission/schema metadata. `php artisan db:show --counts` bị chặn bởi
  live server thiếu `performance_schema.session_status`; không dùng thao tác
  đó làm bằng chứng schema ứng dụng.
- Live DB có 42 permission và 6 role, gồm một role legacy ngoài năm role
  fresh; đây là drift quan sát được, không sửa dữ liệu live.

## Ma trận module × vai trò

Ký hiệu: `✓` = route/flow được phép theo catalog `vai_tro_quyen` active;
`—` = bị deny (thường HTTP 403); `auth` = chức năng chung sau đăng nhập.
Các ô ghi read/write khi quyền khác nhau.

| Module/flow | Quản trị | Nhân sự | Kế toán | Trưởng phòng | Nhân viên |
| --- | --- | --- | --- | --- | --- |
| Auth, logout, profile, password | auth | auth | auth | auth | auth |
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ |
| Phòng ban | read/write | read/write | — | — | — |
| Chức vụ | read/write | read/write | — | — | — |
| Nhân viên | read/write | read/write | read | read | — |
| Hợp đồng | read/write | read/write | read | read | — |
| Vai trò / Phân quyền / Tài khoản | read/write | — | — | — | — |
| Lương | read/write | read/write | read | — | — |
| Hệ số lương | read/write | read/write | — | — | — |
| Chấm công | read/write | read/write | read/export | read/export | — |
| Nghỉ phép — danh sách/lịch sử | read/write | read/create/delete | read | read | — |
| Nghỉ phép — duyệt | —¹ | — | — | ✓ | — |

¹ Route duyệt web được bảo vệ bởi middleware Trưởng phòng nên Quản trị bị
deny dù role Quản trị có quyền catalog rộng; đây là policy boundary hiện tại,
không suy diễn thành lỗi quyền trong DB.

### Browser evidence

Fresh reload/tab desktop smoke đã đăng nhập lần lượt cả năm tài khoản và
kiểm tra navigation, visibility, HTTP deny/allow theo ma trận. Mỗi role có
dashboard/profile/password; các module ngoài quyền hiển thị 403, không có
console error/warning quan sát được. Các route representative:

- Quản trị: `/vai-tro`, `/luong`, `/cham-cong`, `/nghi-phep`, `/hop-dong`,
  `/duyet-nghi-phep` (403 theo policy).
- Nhân sự: CRUD entry points của Phòng ban/Chức vụ/Nhân viên/Hợp đồng và read
  các module được cấp; RBAC/duyệt bị deny.
- Kế toán: read Nhân viên/Hợp đồng/Lương/Chấm công/Nghỉ phép; writes, catalog
  Phòng ban/Chức vụ và RBAC bị deny.
- Trưởng phòng: read Nhân viên/Hợp đồng/Chấm công/Nghỉ phép và duyệt nghỉ phép;
  writes catalog/Lương/RBAC bị deny.
- Nhân viên: chỉ flow chung; module nghiệp vụ bị deny.

Sau sửa, fresh UI của `/luong`, `/cham-cong`, `/nghi-phep`, `/hop-dong` đã
được mở lại bằng trạng thái loading/visibility thực tế, không fixed sleep;
console quan sát được rỗng. Không đọc cookie/localStorage/token và không dùng
JS để mutation.

CUA đã xác minh thêm viewport đại diện `375x812` trên `/duyet-nghi-phep` của
Trưởng phòng: main rộng đúng `375px`, nút mở sidebar hiển thị, không document
overflow và console không có warn/error; viewport được reset sau kiểm tra.
Network waterfall/devtools protocol vẫn không có evidence đủ để claim.
Browser mutation (create/update/delete/
approve/import/batch) cố ý không chạy vì không có disposable browser DB guard;
contract tương ứng được bù bằng feature/unit/frontend tests.

## Checklist flow

- [x] Đọc route web/API, middleware, controller/client contract và ba SQL active.
- [x] Kiểm tra route inventory, duplicate name/signature và quyền theo
  `vai_tro_quyen`, không theo tên role.
- [x] Auth/session, profile/password/logout smoke cho 5 role.
- [x] Dashboard và navigation/deny boundary smoke cho 5 role.
- [x] List/filter/pagination/loading/empty/error UI cho các module có prototype.
- [x] Kiểm tra contract date, payload, route URL, action disabled/retry và
  button/accessibility static tests.
- [x] Browser read-only desktop acceptance cho 5 role.
- [x] Browser mobile representative `375x812` cho trang duyệt nghỉ phép của
  Trưởng phòng, không overflow và console sạch.
- [ ] Network waterfall và browser mutation trên DB disposable — giới hạn môi
  trường, không tự mở rộng quyền DB.

## Issues, root cause, sửa và trạng thái

| ID / severity | Vấn đề và root cause | Sửa / regression | Trạng thái |
| --- | --- | --- | --- |
| RBAC-01 / High | API export/template/import/batch Chấm công và lookup/export Lương chỉ có `auth`, thiếu permission middleware | Gắn exact `ChamCong.Read/Insert/Update` và `Luong.Read` trong `routes/api.php`; mở rộng `ContentFourManagementTest` | Fixed, test xanh |
| RBAC-02 / High | Batch Chấm công dùng `so_gio_lam=-1` để xóa nhưng chỉ yêu cầu Insert+Update; route hệ số dùng nhầm catalog `Luong.*` | Batch yêu cầu thêm `ChamCong.Delete`; GET/POST/PUT hệ số dùng `HeSoLuong.Read/Insert/Update`, Delete giữ `HeSoLuong.Delete`; thêm HTTP authorization tests | Fixed; 27 PHP tests/284 assertions targeted |
| DATA-01 / High | Client template Chấm công dùng URL lệch route canonical | Đổi sang `/api/v1/cham-cong/template` và giữ permission contract | Fixed |
| DATA-02 / Medium | Date UI dùng parse trình duyệt/local `new Date`, payload leave không ổn định | Dùng `formatDisplayDate`, `toIsoDate`, `daysBetweenIsoDates`, validate field-level và payload ISO | Fixed |
| FLOW-01 / Medium | Filter tự fetch theo input/change và paginator/delete contract không nhất quán | Submit explicit, normalize paginator, page sizes 10/20/50, shared guarded delete/retry và disabled state | Fixed; 104 frontend tests pass |
| FLOW-02 / Medium | Duyệt nghỉ phép gửi method/full payload không khớp route PATCH/status | Gửi PATCH chỉ với `trang_thai_duyet`, map rõ 403/404/409 | Fixed |
| DATA-03 / High | Client Nghỉ phép gửi page/per_page/tu_khoa/ma_pb/ma_cv nhưng `getAll()` bỏ qua và dùng `get()` | Allowlist controller, Query Builder 15-table joins, `JsonPaginator`, tab pending/history counts và scope phòng ban Trưởng phòng; thêm HTTP pagination/filter tests | Fixed |
| UI-01 / Low | Sidebar submenu max-height cố định, dropdown/aria/state có thể lệch | CSS variable theo scrollHeight, state session an toàn, aria-expanded sync, reduced-motion | Fixed; build pass |
| UI-02 / Medium | Blade đánh dấu group active nhưng JS tìm active route bên trong `.sub-menu`, khiến submenu initial có `open` nhưng vẫn hidden | Tách resolver DOM dùng `data-route-active`, set readiness/height trên submenu direct child; executable VM/fake-DOM regression | Fixed |
| DB-01 / Medium | Live DB có routine legacy drift (82 routine quan sát được) khác 12 routine active; `db:show` phụ thuộc bảng performance schema thiếu | Không thêm/xóa routine; bám Query Builder/contracts hiện có và ghi rõ evidence blocker | Deferred / risk |
| ENV-01 / Medium | Chưa có network waterfall evidence và browser DB mutation không có disposable guard | Giữ desktop + mobile representative read-only, bù tests, ghi limitation | Deferred / unverified |

## Kế hoạch sửa và kết quả thực hiện

1. **Baseline route/schema/role:** hoàn tất; route web/API, bootstrap và ba SQL
   active đã được đọc, role/permission được đối chiếu theo mã quyền.
2. **Security/RBAC và broken network contract:** hoàn tất; bổ sung middleware
   exact cho endpoint thiếu và khóa bằng regression route test.
3. **Mutation/data contract:** hoàn tất trong phạm vi test-safe; chuẩn hóa URL,
   date ISO/display, approval PATCH, pagination và guarded delete. Không chạy
   mutation qua browser/live DB.
4. **UI/browser role acceptance:** hoàn tất verified desktop read-only cho 5
   role và các route đại diện; loading/empty/403/console đã được kiểm tra.
   Mobile representative `375x812` đã pass; network waterfall vẫn unverified.
5. **Full verification/docs:** hoàn tất; full Laravel, toàn bộ frontend, build,
   route duplicate, Composer, lint và diff-check đều xanh; tài liệu status,
   handoff và audit này đã cập nhật, không xóa lịch sử.

## Files đã sửa trong audit

Authorization/test: `routes/api.php`, `package.json`,
`app/Http/Controllers/Backend/NghiPhepController.php`,
`app/Services/NghiPhepService.php`,
`tests/Feature/Backend/ContentFourManagementTest.php`,
`tests/Feature/Backend/HeSoLuongAuthorizationTest.php`,
`tests/Feature/Compatibility/ChamCongBatchAuthorizationTest.php`,
`tests/Feature/Backend/NghiPhepPaginationTest.php`,
`tests/Feature/Backend/NghiPhepJsonListContractTest.php`,
`tests/Feature/Backend/LuongHeSoLuongTask12BContractTest.php`.

Frontend/backend contract: `resources/js/frontend/chamcong/chamcong.js`,
`resources/js/frontend/luong/luong.js`, `resources/js/frontend/luong/luongHeSo.js`,
`resources/js/frontend/luong/luongHeSoCreateUpdate.js`,
`resources/js/frontend/nghiphep/create.js`,
`resources/js/frontend/nghiphep/duyet-nghi-phep.js`,
`resources/js/frontend/nghiphep/nghiphep.js`,
`resources/views/backend/chamcong/index.blade.php`,
`resources/views/backend/hopdong/form.blade.php`,
`resources/views/backend/hopdong/index.blade.php`,
`resources/views/backend/luong/index.blade.php`,
`resources/views/backend/nghiphep/duyet-nghi-phep.blade.php`,
`resources/views/backend/nghiphep/index.blade.php`,
`public/backend/js/script.js`, `public/backend/js/sidebar-state.js`,
`public/backend/css/style.css`, `resources/views/backend/layouts/app.blade.php`,
`tests/Frontend/shared/backend-script.test.js`,
`tests/Frontend/shared/sidebar-state.test.js`,
`docs/PROJECT_STATUS.md`, `docs/CODEX_NEXT_HANDOFF.md`.

## Verification cuối

| Lệnh | Kết quả |
| --- | --- |
| `php artisan route:list --except-vendor` | exit 0; 96 routes |
| route duplicate audit (all routes) | 99 routes; duplicate name `[]`; duplicate signature `[]` |
| `php artisan test` | 464 passed, 3732 assertions |
| `npm run test:frontend` | 40/40 pass |
| all `tests/Frontend/**/*.test.js` via `node --test` | 104/104 pass |
| `npm run build` | pass; Vite 7.3.6, 31 modules transformed |
| `composer validate --no-check-publish` | valid |
| PHP lint changed PHP files | no syntax errors |
| `git diff --check` | pass |

## Giới hạn và rủi ro còn lại

Đây là evidence local/test và browser desktop read-only, không phải production
certification. Contract của routine legacy/live DB cần một MariaDB disposable
được guard đúng để kiểm tra thêm; không được dùng live DB. Mobile evidence mới
chỉ đại diện cho `/duyet-nghi-phep` ở `375x812`; network waterfall, browser
mutation và toàn bộ workflow Hợp đồng/RBAC mutation chưa được claim đóng. Role
legacy thứ sáu trong live DB không thuộc fresh
five-role matrix. Handoff kế tiếp phải giữ nguyên các giới hạn này và không
đổi shell/layout ngoài bug thực tế.
