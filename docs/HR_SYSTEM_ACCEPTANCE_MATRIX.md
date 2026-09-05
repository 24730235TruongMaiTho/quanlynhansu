# Ma trận nghiệm thu chuẩn hóa hệ thống quản lý nhân sự

> Baseline Task 1 ngày 2026-09-03 được giữ lại để truy nguyên. Mục `Final 2026-09-04` bên dưới là trạng thái hiện hành của worktree sau các checkpoint; browser chỉ có evidence isolated direct CDP trong phạm vi ghi rõ, không có claim production.

## Cách đọc trạng thái

- `planned`: yêu cầu đã có trong thiết kế nhưng chưa có implementation/evidence đủ để kết luận.
- `RED`: baseline có test đỏ hoặc code/route hiện tại mâu thuẫn trực tiếp với yêu cầu.
- `GREEN automated`: chỉ green trong automated test/static contract được nêu ở dòng đó; không suy rộng thành browser.
- `GREEN browser`: chỉ dùng khi đã có evidence runtime browser cụ thể.
- `blocked`: gate cần database/browser/approval hoặc contract chưa tồn tại nên chưa thể nghiệm thu.

Một dòng chỉ được nâng lên `GREEN browser` khi có route, authorization, data state, UI state, network/console và viewport evidence tương ứng. Không coi `200`, Vite build hoặc test fixture SQLite là bằng chứng hoàn tất nghiệp vụ.

## Final 2026-09-04

Các dòng `GREEN automated` dưới đây chỉ dựa trên lệnh/test/static contract được ghi rõ. Browser evidence isolated direct CDP được tách riêng; không thay thế MariaDB/production acceptance.

| Gate | Command/evidence exact | Status |
| --- | --- | --- |
| Laravel | `php artisan test --no-coverage` exit `0`: `436 passed, 3402 assertions` | `GREEN automated` |
| Frontend | `npm run test:frontend` exit `0`: `84/84` pass | `GREEN automated` |
| Build | `npm run build` exit `0`: Vite `28 modules transformed` | `GREEN automated` |
| Composer | `composer validate --no-check-publish` exit `0`: `composer.json is valid` | `GREEN automated` |
| PHP lint | `142` existing changed/untracked PHP files, `0` failures | `GREEN automated` |
| Route inventory | `route:list --except-vendor` shows `94` app routes; `route:list --json` totals `97` including vendor routes; duplicate app name/signature `0` | `GREEN automated` |
| Diff hygiene | `git diff --check` exit `0` | `GREEN automated` |
| MariaDB read-only audit | MariaDB `10.4.32`, database `quan_ly_nhan_su`, `15 tables + 1 view`; current counts `nhan_vien=20`, `phong_ban=5`, `chuc_vu=6`, `hop_dong=19`, `cham_cong=1`, `nghi_phep=8`, `luong=20`, `he_so=20`, `quyen=42` (`41` was the pre-alignment baseline) | `Verified live read-only` |
| Permission alignment | `NhanVien.ResetPassword=42`; `quyen_count=42`, `role1_42=1`, role `2 -> [38,39,40,41,42]` | `Verified live/contract` |
| `php artisan db:show --counts` | Blocked: `performance_schema.session_status` is unavailable | `blocked` |
| Official browser plugin | `node_repl` points to missing `browser/26.825.51511`; current cache is `26.831.21537` | `blocked` |
| Isolated browser acceptance | Direct-CDP headless fallback: 14 desktop routes, 8 responsive checks at 768/375, all-four-role menu/403 smoke plus chart; `docOverflow=false` for all measured routes/checks (`753/753` at 768, `375/375` mobile), 0 native date input, 0 action select, 0 unexpected network error, 0 JS exception after fix | `GREEN browser narrow` |

### Final acceptance reconciliation

| Requirement rows | Exact current evidence | Status |
| --- | --- | --- |
| `FND-01`, `ACT-01..03`, `FIL-01..04`, `PAG-01..04`, `DAT-01..05`, `VAL-01..04`, `TAB-01..03` | Shared/module Laravel tests, `DateFieldBoundaryTest`, frontend suites and clean row-action consumer/static contracts pass in the final gate | `GREEN automated` |
| `NAV-01..05`, `PROF-01..02`, `DASH-01..06` | Sidebar/topbar/profile/dashboard/approval route and service tests pass in full Laravel suite | `GREEN automated` |
| `EMP-01..03`, `DEPT-01`, `POS-01`, `RBAC-01..07`, `HD-01..05` | Focused module feature/unit/frontend tests pass and are included in full Laravel/frontend totals | `GREEN automated` |
| `MOD-01..07` | Full module regression: `436 tests, 3402 assertions`; frontend `84/84` | `GREEN automated` |
| `GATE-01` | RED evidence was recorded for implementation/review fixes; final assertions retained and full suite passes | `GREEN automated` |
| `GATE-02` | Laravel/frontend/build/Composer/PHP lint/routes/diff commands above all pass | `GREEN automated` |
| `GATE-03` | Isolated direct CDP: 14 desktop routes and 8 responsive checks at 768/375 for Dashboard, Nhân viên, Nghỉ phép and Lương; `docOverflow=false` (`753/753` at 768, `375/375` mobile), meaning no horizontal overflow of the whole document in the measured scope; table-internal scroll and production-wide overflow are unclaimed; console/network smoke clean after fix | `GREEN browser narrow` |
| `GATE-05` | Isolated direct CDP verified all four roles for role/menu/403 smoke; 404 was not run and remains automated-only; profile/password rendered for the tested roles | `GREEN browser role/menu/403; 404 automated-only` |
| `GATE-04` | Recorded live transactional Nghỉ phép evidence: same-department approval, cross-department fail-closed, and rollback restored the count; no browser claim | `Verified narrow live transactional` |
| `GATE-06` | Automated requirements and narrow live/browser evidence are reconciled above; official plugin and browser 404 evidence remain explicitly limited | `GREEN automated/browser narrow with limits` |

### Live DB and transaction evidence 2026-09-04

| Evidence | Exact result | Status |
| --- | --- | --- |
| Service-list counts | `nhan_vien=20`, `phong_ban=5`, `chuc_vu=6`, `hop_dong=19`, `vai_tro=6`, `phan_quyen=20`, Chấm công employees `20`, Nghỉ phép `8` | `Verified recorded live read-only` |
| Salary listing | `21` rows because of the current join shape | `Verified recorded live read-only` |
| Same-department leave transaction | Dashboard/approval flow: `pending=1`, visible, approval state `1` | `Verified recorded transactional` |
| Cross-department leave transaction | Invisible, safe result `NGHI_PHEP_NOT_FOUND`, data unchanged | `Verified recorded transactional` |
| Rollback | Nghỉ phép total restored to `8` | `Verified recorded transactional` |

These are recorded runtime/DB results and supplement the automated matrix. They
do not imply production acceptance or official-plugin coverage.

### Isolated browser acceptance evidence 2026-09-04

The official Chrome DevTools plugin remains blocked because its configured
`browser/26.825.51511` cache is missing. An isolated headless Chrome direct-CDP
fallback completed the scoped browser checks below; this is not an existing user
Chrome session and is not a production claim.

- Admin desktop smoke covered 14 routes. The approval route returned expected
  `403` for Admin because Admin is not a manager.
- Eight responsive checks ran at `768` and `375` for Dashboard, Nhân viên, Nghỉ
  phép and Lương. `docOverflow=false` for all measured routes/checks
  (`scrollWidth=753/clientWidth=753` at 768 and `375/375` at mobile), meaning no
  horizontal overflow of the whole document in the measured scope. This does not
  claim table-internal scroll behavior or production-wide overflow.
- Observed `0` native date inputs, `0` action selects, `0` unexpected network
  errors and `0` JavaScript exceptions after the shared-script fixes.
- Click smoke passed: mobile sidebar; create leave → `/tao-nghi-phep`; salary
  coefficient → `/luong#salary-coefficient-card`; topbar role/profile/password/
  logout; employee edit modal; centered delete dialog in the client viewport;
  and reset button presence.
- Manager role 4 showed `Trưởng phòng`; Dashboard pending count `0` linked to
  `/duyet-nghi-phep`; approval sidebar link existed; the approval page title was
  `Duyệt nghỉ phép`; unrelated forbidden routes returned expected `403`.
- Role 2 showed topbar `Nhân sự`; its sidebar exposed Nhân viên, Phòng ban,
  Chức vụ, Hợp đồng, Chấm công, Nghỉ phép (Tạo + Danh sách), Lương and Hệ số;
  those module routes rendered, while `/duyet-nghi-phep`, `/vai-tro` and
  `/tai-khoan` returned expected `403`; profile/password rendered with `0` JS
  exception/`5xx`.
- Role 5 showed topbar `Nhân viên`; because the active/default role intentionally
  has no module permission, only `Tổng quan` appeared in the sidebar. Business
  and RBAC routes returned expected `403`; profile/password rendered with `0` JS
  exception/`5xx`.
- Together with Admin and Trưởng phòng, this completes isolated browser
  role/menu/403 coverage for all four roles. Browser 404 was not run and remains
  automated-only.
- After five seconds, the pie Chart existed with labels
  `Đại học/Thạc sĩ/Cao đẳng`, values `12/5/4`, and `166426` colored pixels; no
  console messages were observed.
- QA employee `00022` was created only for isolated acceptance and exact-cleaned;
  dependency counts were zero, employee count returned to baseline `20`, and
  remaining QA identifiers were `0`. Generated scripts/reports were removed;
  PNG screenshots remain under `storage/app/qa-browser-260904` because the
  removal command was blocked. The directory itself is not claimed deleted.

The active seed source `database/sql/du_lieu_mau.sql` was audited separately: it
contains exactly `42` permission rows with contiguous IDs `1..42`, including
`NhanVien.ResetPassword=42`. The live audit after canonical alignment confirms
`quyen_count=42`, permission `42` present, `role1_42=1`, and role 2 exactly
`[38,39,40,41,42]`; `41` is only the pre-alignment baseline.

## Identity và phạm vi baseline (lịch sử)

| Mục | Evidence |
| --- | --- |
| Branch/HEAD | `main`, `5e19a5100de48b33fcda4dcc3e598c2f9128d7a0` (`5e19a51`, `origin/main`) |
| Dirty worktree trước Task 1 | Các file đã modified: `AGENTS.md`, `routes/web.php`, 6 Blade/view, 2 shared/module JS, 4 Backend feature test và 2 Frontend test; untracked `AIAssistantInput-…chatInput`, design spec và implementation plan. Các thay đổi này thuộc worktree có sẵn và được giữ nguyên. |
| SQL contract | `database/sql/tao_bang.sql` có 15 `CREATE TABLE`; `database/sql/du_lieu_mau.sql` seed 19 nhân viên và 42 quyền với ID `1..42` (gồm `NhanVien.ResetPassword=42`); `database/sql/quyen_vai_tro.sql` có 12 procedure RBAC. Đây là static/source evidence, không import hoặc mutate DB trong Task 1. |
| Design source | [`docs/superpowers/specs/2026-09-03-hr-system-consistency-design.md`](superpowers/specs/2026-09-03-hr-system-consistency-design.md) |
| Task boundary | Chỉ inventory, matrix, cập nhật status và report; không sửa code nghiệp vụ, không tạo bảng/cột, không sửa/xóa dữ liệu, không commit/push. |

## Baseline commands (lịch sử)

Các lệnh dưới đây đã chạy đúng theo brief. Exit code là exit code thực tế của từng lệnh.

| Command | Exit | Kết quả exact |
| --- | ---: | --- |
| `git status --short --branch` | `0` | `## main...origin/main`; worktree dirty như identity ở trên. |
| `php artisan route:list --except-vendor` | `0` | `Showing [90] routes`; route inventory có canonical employee/department/position/contract screens, API v1 và route legacy. Có route name `backend.backend.nghiphep.duyet-nghi-phep`; chưa có profile/password/reset-password/coefficient DELETE route. |
| `php artisan test --compact` | `1` | `304 passed, 12 failed, 2364 assertions`; failure cụ thể ở `ContentFourManagementTest` (middleware `ChamCong.Read`), `DashboardFeatureTest` (dashboard source contract), 8 failure `ChamCongEmployeeLookupSecurityTest` (expected 403/200/500/422 nhưng baseline nhận khác hoặc DB/procedure contract lỗi), và 2 failure `NghiPhepEmployeeLookupTest` (lookup view/procedure contract và safe error). Chi tiết test names ở [Baseline failures](#baseline-failures). |
| `npm run test:frontend` | `1` | Node `40 tests`: `39 pass`, `1 fail`; `tests/Frontend/nghiphep/employee-response.test.js:47`, expected `—`, actual `-`. Có npm warning `Unknown env config "min-release-age"`. |
| `npm run build` | `0` | Vite `27 modules transformed`, build thành công. Đây chỉ là asset/build evidence. |
| `rg --files resources/views/backend resources/js/frontend app/Http/Controllers/Backend app/Http/Requests tests/Feature/Backend tests/Frontend` | `0` | Inventory exact: views `39`, frontend JS `24`, Backend controllers `19`, Requests `24`, Backend feature files `16`, Frontend test files `12`; danh sách đầy đủ ở [Inventory](#inventory-module-va-man-hinh). |
| `git diff --check` | `0` | Không phát hiện whitespace error tại baseline. Sẽ chạy lại sau khi ghi tài liệu. |

### Baseline failures (lịch sử)

| Test | Baseline failure/effect |
| --- | --- |
| `Tests\\Feature\\Backend\\ContentFourManagementTest::test_work_module_routes_use_their_own_exact_permission_catalog` | Route `api.v1.cham-cong.nhan-vien` không chứa middleware `can:ChamCong.Read`. |
| `Tests\\Feature\\Backend\\Dashboard\\DashboardFeatureTest::test_dashboard_escapes_database_values_before_inserting_contract_rows_as_html` | Dashboard source contract assertion fail. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_zero_permission_actor_cannot_read_or_update_attendance_api` | Expected 403, received validation response. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_zero_permission_actor_cannot_read_attendance_department_lookup` | Expected 403, received validation response. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_xem_only_actor_cannot_update_attendance_api` | Expected 403, received validation response. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_permission_middleware_remains_authoritative_without_rollout_switch` | Expected 403, received validation response. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_permissioned_lookup_maps_filters_and_preserves_attendance_aggregates` | Expected 200, received validation/SQLite schema response. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_any_lookup_failure_returns_only_the_stable_public_error` | Expected stable 500, received validation response. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_exists_validation_database_failure_returns_only_the_stable_public_error` | Expected stable 500, received validation response. |
| `Tests\\Feature\\Compatibility\\ChamCongEmployeeLookupSecurityTest::test_ordinary_invalid_filter_still_returns_laravel_validation_errors` | Expected 422, received database error response. |
| `Tests\\Feature\\Compatibility\\NghiPhepEmployeeLookupTest::test_permissioned_lookup_maps_to_the_canonical_employee_service` | Expected 200; SQLite lacks `vw_danh_sach_nhan_vien_chi_tiet`. |
| `Tests\\Feature\\Compatibility\\NghiPhepEmployeeLookupTest::test_permissioned_lookup_returns_a_stable_error_without_internal_details` | Expected safe public error; raw database detail is returned. |

## Requirement matrix

Evidence links name the exact current route/view/file/test. A source match is not treated as passing behavior unless the row says `GREEN automated` and gives the test/contract that passed.

### Foundation, actions, filters, pagination, dates, validation and tables

| ID | Requirement | Exact current evidence | Status | Next gate |
| --- | --- | --- | --- | --- |
| FND-01 | Giữ Bootstrap, màu sắc, sidebar và bố cục hiện tại; không thay shell trong workstream này. | Runtime layout [`resources/views/backend/layouts/app.blade.php`](../resources/views/backend/layouts/app.blade.php), sidebar [`resources/views/backend/layouts/sidebar.blade.php`](../resources/views/backend/layouts/sidebar.blade.php); chưa có browser snapshot của toàn shell. | `planned` | Foundation implementation + browser 1440/768/375. |
| FND-02 | Không tạo bảng/cột; bám schema active 15 bảng; permission mới chỉ sau catalog preflight. | Static SQL markers trong `database/sql/tao_bang.sql`, `du_lieu_mau.sql`, `quyen_vai_tro.sql`; Task 1 không chạy DDL/DB. | `planned` | Read-only catalog preflight trước reset-password, không import dump. |
| ACT-01 | Bỏ dropdown/select `Thao tác` ở mọi danh sách. | `data-row-action-select` còn trong `resources/views/backend/nhanvien/index.blade.php`, `phongban/index.blade.php`, `chucvu/index.blade.php`, `hopdong/index.blade.php`; consumers còn ở `resources/js/frontend/shared/row-action-select.js`, `nhanvien/employee-page.js`, `phongban/phongban.js`, `chucvu/chucvu.js`, `hopdong/hopdong.js`. | `RED` | Task 3 RED/GREEN theo từng module. |
| ACT-02 | Xem/Sửa/Xóa/Reset mật khẩu/Phân quyền là nút riêng trong cột thao tác. | Nhân viên/Phòng ban/Chức vụ/Hợp đồng vẫn serialize action bằng `<select>`; tài khoản còn form gán role theo từng dòng trong [`resources/views/backend/taikhoan/index.blade.php`](../resources/views/backend/taikhoan/index.blade.php). | `RED` | Action partial + per-module markup tests. |
| ACT-03 | Link dùng `<a class="btn">`; mutation dùng button, CSRF, Gate và confirm phù hợp. | Automated hẹp: `NhanVienIndexTest`, `NhanVienLifecycleTest`, `NhanVienUpdateTest`, `PhongBanFeatureTest`, `ChucVuFeatureTest` kiểm tra một phần href/CSRF/Gate/confirm. Global action select và các module còn lại chưa đạt. | `RED` | Chốt contract shared rồi test tất cả list. |
| ACT-04 | Desktop cùng hàng; mobile wrap; icon-only có accessible name. | Hẹp: `tests/Frontend/nhanvien/responsive-layout.test.js`, `confirm-actions.test.js`, và `NhanVienIndexTest` có fallback/link; chưa bao phủ mọi module. Chưa có browser đa màn hình. | `planned` | Browser responsive matrix vẫn bắt buộc. |
| FIL-01 | Mọi filter có label hiển thị, không chỉ placeholder/aria-label. | Nhân viên/Phòng ban/Chức vụ/Hợp đồng có label; Chấm công/Nghỉ phép/Lương còn một số control chỉ `aria-label` hoặc dynamic control trong `chamcong/index.blade.php`, `nghiphep/index.blade.php`, `luong/index.blade.php`. | `RED` | Shared filter partial + markup contract. |
| FIL-02 | Filter chỉ gửi khi bấm `Áp dụng bộ lọc`; không auto-fetch theo từng phím/select. | `resources/js/frontend/shared/list-filter.js` chỉ có submit helper hẹp; page copy hiện dùng `Lọc`, `Tìm`, `Xóa lọc`, và module JS có filter state riêng. | `RED` | Explicit apply/reset tests từng module. |
| FIL-03 | Có `Đặt lại`, query whitelist và `per_page` hợp lệ. | `ListNhanVienRequest` cho `so_dong` `5,10,20,50,100`; `ListHopDongRequest` cho `per_page` `5,10,20,50,100`; account filter không có paginator. | `RED` | Chuẩn hóa query contract `[10,20,50]`. |
| FIL-04 | Thứ tự filter: tiêu chí → page size → áp dụng → đặt lại. | Không có shared filter partial; thứ tự/copy không đồng nhất giữa các Blade. | `planned` | Task 2/4 shared partial. |
| PAG-01 | Pagination áp dụng cho Nhân viên, Phòng ban, Chức vụ, Hợp đồng, Chấm công, Nghỉ phép, Lương, Vai trò và Phân quyền tài khoản. | Employee/PB/CV/contract có paginator hẹp; `PhanQuyenController::accounts()` gọi `accounts()` trả array và view không render pagination; JS pages có paginator riêng, chưa cùng contract. | `RED` | Server/JSON paginator tests cho 9 nhóm màn. |
| PAG-02 | Dùng `backend.partials.pagination` và `pagination-summary`; JS dùng cùng class/ARIA/copy. | Partials hiện có: `resources/views/backend/partials/pagination.blade.php`, `pagination-summary.blade.php`; chưa có shared JSON/JS contract và các page chưa áp dụng toàn bộ. | `planned` | Shared paginator contract + all-list regression. |
| PAG-03 | Page size chỉ 10/20/50, mặc định 10; Chấm công có thể mặc định 31. | Current request/JS defaults include 5/15/20/25/100; `ListNhanVienRequest` mặc định 20; `ChamCongController` và `NghiPhepController` nhận range rộng. | `RED` | Normalize defaults and invalid fallback tests. |
| PAG-04 | Giữ filter khi đổi trang; filter mới về trang 1. | Automated hẹp: `NhanVienIndexTest`, `PhongBanFeatureTest`, `ChucVuFeatureTest` assert query context/pagination links. Chưa bao phủ toàn bộ module và reset-to-page-1. | `planned` | Expand to all list/API modules. |
| DAT-01 | Mọi ngày hiển thị `dd/mm/yyyy`. | Các Blade còn `type="date"` và in ISO, ví dụ `hopdong/form.blade.php`, `nghiphep/create.blade.php`, `nghiphep/duyet-nghi-phep.blade.php`, `luong/index.blade.php`. | `RED` | Date foundation + search gate. |
| DAT-02 | Date input có label, placeholder `dd/mm/yyyy`, `inputmode="numeric"`, lỗi nhất quán. | Current date inputs là native `type="date"`; chưa có shared display-date attributes. | `RED` | Form markup tests. |
| DAT-03 | Có `parseDisplayDate`, `formatDisplayDate`, `toIsoDate` strict; reject `31/02/2026`. | Chưa có `resources/js/frontend/shared/date-field.js`; frontend baseline chỉ fail gender fallback `—`/`-`, chưa có date helper test. | `planned` | RED tests rồi implement strict round-trip. |
| DAT-04 | FormRequest normalize `dd/mm/yyyy` về ISO trong `prepareForValidation`. | `StoreNhanVienRequest` dùng `date_format:Y-m-d`; `StoreHopDongRequest`, `StoreNghiPhepRequest`, `StoreChamCongRequest`, `StoreLuongRequest` còn `date`/ISO rules; chưa có trait normalize. | `RED` | Request tests valid/invalid display date. |
| DAT-05 | DB/API tiếp tục lưu ISO sau khi UI đổi display format. | Current requests/API đang dùng ISO nhưng chưa có unified display-to-API contract hoặc test bao phủ tất cả module. | `planned` | Verify together with DAT-03/04. |
| VAL-01 | Mỗi field có label, `.invalid-feedback`, `aria-describedby`. | Automated hẹp: employee partials và `tests/Frontend/shared/edit-modal.test.js`, `tests/Frontend/nhanvien/edit-modal.test.js`; các form ngoài employee chưa đồng nhất. | `RED` | Shared contract test tất cả form. |
| VAL-02 | Form alert tổng quát; success dùng `role="status"`/`aria-live="polite"`. | Employee/PB/CV tests kiểm tra alert/status; Chấm công/Nghỉ phép/Lương dùng toast/dynamic states khác nhau, chưa có shared contract. | `RED` | Shared feedback partial + global test. |
| VAL-03 | Copy chuẩn required/date/unique và nghiệp vụ bằng tiếng Việt. | `StoreNghiPhepRequest` hiện có copy cũ; `StoreHopDongRequest` thiếu `attributes/messages`; request rules giữa module không đồng nhất. | `RED` | Vietnamese attributes/messages test. |
| VAL-04 | Không trả raw exception, SQLSTATE, hash hoặc dữ liệu nhạy cảm. | Employee/Dashboard có safe tests hẹp; full baseline failure ở `NghiPhepEmployeeLookupTest` trả raw database detail; `NghiPhepController` còn `$e->getMessage()` ở lookup/mutation. | `RED` | Localize safe-error tests then fix per module. |
| TAB-01 | Table dùng `table table-hover align-middle mb-0`, `thead.table-light`, caption ẩn, scope và responsive wrapper. | Automated hẹp: `NhanVienIndexTest`, `PhongBanFeatureTest`, `ChucVuFeatureTest` kiểm tra table/caption/filter; `chamcong`, `nghiphep`, `luong` có table nhưng chưa shared caption/scope contract. | `RED` | Global table contract tests. |
| TAB-02 | Loading/empty/error cùng cấu trúc và đúng colspan. | Employee/PB tests có distinct empty/error states; attendance/leave/salary dynamic states chưa có exact cross-module contract. | `RED` | Add `table-state` partial and state tests. |
| TAB-03 | Mã và số nhân viên là text/bold thường; không badge/background cho mã phòng ban và số nhân viên. | Current `phongban/index.blade.php:117,119` uses `badge`; `chucvu/index.blade.php:116` uses badge; account page also badge for code. | `RED` | Task 8 markup RED/GREEN. |

### Sidebar, topbar, hồ sơ, dashboard và nghỉ phép

| ID | Requirement | Exact current evidence | Status | Next gate |
| --- | --- | --- | --- | --- |
| NAV-01 | Chức vụ là group có submenu `Danh sách chức vụ`. | Sidebar hiện render Chức vụ như một link đơn tới `backend.chucvu.index` trong `resources/views/backend/layouts/sidebar.blade.php`. | `RED` | Sidebar route/visibility test. |
| NAV-02 | Nghỉ phép có submenu tạo/danh sách/duyệt; duyệt chỉ hiện actor phù hợp và Trưởng phòng. | Sidebar có tạo/danh sách nhưng không có duyệt; route `/duyet-nghi-phep` không có `auth`/permission trong route list. | `RED` | Exact manager policy + menu test. |
| NAV-03 | Hệ số lương trỏ section `#salary-coefficient-card`, không `href="#"`. | Sidebar `Danh sách hệ số lương` còn `href="#"`; view salary đã có `id="salary-coefficient-card"` ở `resources/views/backend/luong/index.blade.php`. | `RED` | Link/hash behavior test. |
| NAV-04 | Topbar lấy tên vai trò thật, không hard-code. | `resources/views/backend/layouts/topbar.blade.php` còn text `Quản trị viên`. | `RED` | Topbar actor projection test. |
| NAV-05 | Account dropdown có Hồ sơ cá nhân, Đổi mật khẩu, Đăng xuất. | Topbar có placeholder `href="#"` cho Hồ sơ, Cài đặt tài khoản, Bảo mật; chỉ logout có route thật. | `RED` | Profile/password routes and view test. |
| PROF-01 | Hồ sơ cá nhân sửa allowlist, khóa mã/phòng/chức vụ/vai trò/trạng thái; email/CCCD unique. | Không có `ProfileController`, `UpdateProfileRequest`, `backend.profile.*` route hoặc `resources/views/backend/profile/*` trong inventory. | `planned` | Task 6 RED/GREEN + auth role checks. |
| PROF-02 | Đổi mật khẩu yêu cầu current/new/confirmation, hash server-side, giữ session hiện tại và invalidate session khác nếu hạ tầng hỗ trợ. | Không có route/controller/request/view change-password trong current inventory. | `planned` | Task 6 password feature test and browser. |
| DASH-01 | `#statsCards` có thẻ `Nghỉ phép chờ duyệt` chỉ cho Trưởng phòng. | View dùng `id="statCards"` (singular), không có pending leave card; `TongQuanController`/Dashboard payload chưa có field theo spec. | `RED` | Dashboard manager role RED/GREEN. |
| DASH-02 | Count là mọi đơn pending của cùng `ma_pb`, không lẫn phòng khác. | Chưa có dashboard pending query/payload; leave approval service có query scope riêng nhưng chưa nối dashboard. | `RED` | Server-side scoped count test. |
| DASH-03 | Click card mở `backend.nghiphep.duyet-nghi-phep`. | Current route list chứa `backend.backend.nghiphep.duyet-nghi-phep` do nested name prefix; không có card link. | `RED` | Canonical route rename + click test. |
| DASH-04 | Trang duyệt chỉ Trưởng phòng có `NghiPhep.Update`; query scope backend, không tin filter client. | `routes/web.php` approval view route không middleware; API approval list chỉ có `auth`; controller có comment/set `ma_pb` và service `getApprovalList()` join scope nhưng role/permission contract chưa đủ. | `RED` | Manager policy, route middleware, query test. |
| DASH-05 | Non-manager 403; target khác phòng không xuất hiện; mutation trả 404/403 an toàn. | Không có web approval authorization test; current `duyet()` nhận `ma_nv` từ request và gọi procedure. | `RED` | Security tests before mutation. |
| DASH-06 | Sau duyệt/từ chối badge/card/list cập nhật số lượng. | `NghiPhepController::duyet()` gọi `sp_nghi_phep_duyet_phep` không thuộc active SQL contract; no end-to-end evidence. | `blocked` | Active data contract + approved disposable DB + browser. |

### Nhân viên, Phòng ban, Chức vụ

| ID | Requirement | Exact current evidence | Status | Next gate |
| --- | --- | --- | --- | --- |
| EMP-01 | Bốn địa chỉ nullable ở create/update, không đổi schema. | `StoreNhanVienRequest.php:70-73` đã dùng nullable; automated `NhanVienValidationTest::test_all_four_address_parts_may_be_omitted_together` và `test_update_requires_all_address_parts_or_none` không nằm trong 12 failure của full baseline. | `GREEN automated` | Re-run focused after each implementation task. |
| EMP-02 | Bỏ border khối `Bước 1: Hồ sơ liên hệ`, giữ heading/khoảng cách. | `create.blade.php:89` và `partials/edit-form.blade.php:33` dùng `fieldset ... border-0`; chưa có test assertion riêng cho border contract. | `planned` | Add explicit Blade/CSS contract test. |
| EMP-03 | Dialog xóa căn giữa, focus trap, Escape/cancel và khôi phục focus. | `tests/Frontend/nhanvien/confirm-actions.test.js` pass các flow Escape/cancel/duplicate submit; responsive test pass centered dialog cho employee/shared CSS. | `GREEN automated` | Browser keyboard/focus evidence. |
| DEPT-01 | Phòng ban không badge/background cho mã và số nhân viên. | `resources/views/backend/phongban/index.blade.php:117,119` còn `badge bg-primary`/`badge bg-info`. | `RED` | Task 8 markup test. |
| POS-01 | Chức vụ thêm submenu và áp chuẩn list/action/filter/table/pagination. | Sidebar chưa submenu; `chucvu/index.blade.php` còn action select; feature tests chỉ cover narrow list/filter/modal contract. | `RED` | Task 3/4/8 position tests. |

### Vai trò, Phân quyền và reset mật khẩu

| ID | Requirement | Exact current evidence | Status | Next gate |
| --- | --- | --- | --- | --- |
| RBAC-01 | Màn quyền bỏ mã kỹ thuật màu xám; giữ nhãn Đọc/Thêm/Sửa/Xóa/Reset mật khẩu. | `resources/views/backend/vaitro/permissions.blade.php` vẫn render `<small>{{ $permission->ky_hieu_quyen }}</small>`; active catalog hiện không có Reset mật khẩu. | `RED` | Permission view contract test. |
| RBAC-02 | Bổ sung `NhanVien.ResetPassword` từ active catalog, không đoán ID lịch sử. | Historical baseline recorded 37 permissions and no Reset symbol; current active seed has 42 rows with `NhanVien.ResetPassword` at verified ID `42`, covered by current registry/seed tests and live alignment evidence. | `GREEN automated` | Keep seed and enum IDs aligned; current live count is `quyen_count=42`. |
| RBAC-03 | Reset chỉ actor có quyền; chặn self-reset và target đặc quyền ngoài policy. | Không có route `backend.nhanvien.reset-password`, request hoặc service method trong inventory. | `planned` | Security RED/GREEN tests. |
| RBAC-04 | Mật khẩu reset `nhom3@{year(config timezone)}`, hash server-side, response chỉ nêu quy ước. | Chưa có reset flow; không có runtime evidence. | `planned` | Service + no-secret response test. |
| RBAC-05 | Đổi tên màn `Gán vai trò tài khoản` thành `Phân Quyền`. | `resources/views/backend/taikhoan/index.blade.php` title/breadcrumb/header và sidebar vẫn `Gán vai trò tài khoản`; `ContentFourFeedbackUiTest` còn assert copy cũ. | `RED` | Copy + navigation test. |
| RBAC-06 | Danh sách tài khoản có filter và pagination chung. | `PhanQuyenController::accounts()` gọi `accounts()` array; view chỉ filter `keyword`, không có paginator/`per_page`. | `RED` | Repository paginator and list tests. |
| RBAC-07 | Bulk assignment map `assignments[ma_nv]=ma_vt`, một nút lưu, transaction all-or-nothing. | Current route `backend.taikhoan.assign-role` nhận từng `{ma_nv}`; view có form/nút `Lưu vai trò` từng dòng; service/repository `assignRole()` đơn lẻ. | `RED` | Bulk request/service atomicity tests. |

### Hợp đồng

| ID | Requirement | Exact current evidence | Status | Next gate |
| --- | --- | --- | --- | --- |
| HD-01 | Form date display `dd/mm/yyyy`, label/error chuẩn; lương hiển thị dấu chấm vi-VN nhưng payload integer. | `resources/views/backend/hopdong/form.blade.php:48,53` còn `type="date"`; `luong_co_ban` chưa có shared currency formatter. | `RED` | Task 5/9 date/currency tests. |
| HD-02 | Loại vô thời hạn tự xóa/khóa ngày hết hạn và lưu `NULL`. | `StoreHopDongRequest` cho `ngay_het_han` nullable; chưa có rule/UI/service resolve loại vô thời hạn. | `planned` | DB-resolved type matrix create/update. |
| HD-03 | Bốn loại còn lại bắt buộc ngày hết hạn và phải strictly after ngày ký. | `StoreHopDongRequest.php:10` hiện `nullable`, `date`, `after_or_equal:ngay_ky`; đây không đạt required/strict-after. | `RED` | RED contract-type tests, then request/service fix. |
| HD-04 | Backend resolve loại từ DB theo `ma_lhd`, không tin tên/type client gửi. | `HopDongRepository` joins catalog nhưng current request chỉ `exists`; chưa có server business rule resolve/enforce theo loại. | `planned` | Service transaction test. |
| HD-05 | Create/update dùng cùng rule; UI chỉ hỗ trợ, backend quyết định. | `UpdateHopDongRequest` kế thừa `StoreHopDongRequest`, nhưng chưa có type-aware rule/service enforcement; no mutation workflow evidence. | `planned` | Create/update parity tests. |

### Phạm vi module và điều kiện hoàn thành

| ID | Requirement/scope | Exact current evidence | Status | Next gate |
| --- | --- | --- | --- | --- |
| MOD-01 | Dashboard: pending card và link approval. | `backend.tongquan.index`, `resources/views/backend/tongquan/index.blade.php`; pending card/link absent. | `RED` | Task 7. |
| MOD-02 | Nhân viên/Phòng ban/Chức vụ: action/filter/pagination/table/date/validation chuẩn. | Current employee/PB/CV narrow feature/frontend tests pass many existing contracts, nhưng action select/page-size/badge/date gaps còn. | `RED` | Tasks 3–5/8 and browser. |
| MOD-03 | Hợp đồng: list/form và date/salary business rules. | `backend.hopdong.index/create/edit` routes and scaffold tests exist; contract rules remain RED/planned above. | `planned` | Task 9. |
| MOD-04 | Chấm công: apply filter, paginator/table/action/date, giữ kỳ tháng/năm. | `backend.chamcong.index`, API `api.v1.cham-cong.*`, view/JS exist; baseline has 8 Chấm công lookup/security failures and missing active procedure caller contract. | `blocked` | Task 12 active Query Builder + security tests. |
| MOD-05 | Nghỉ phép: create/list/approve, submenu, filter/pagination/table/date, department scope. | `backend.nghiphep.index/create`, approval route currently double-prefixed/unguarded; baseline frontend and compatibility failures; approval mutation calls missing active procedure. | `blocked` | Task 7/12 with approved DB/browser gates. |
| MOD-06 | Lương/Hệ số lương: action/filter/pagination/table/date, coefficient section and working route/CSRF. | `backend.luong.index`, API salary/coefficient routes and JS exist; view has coefficient card but sidebar href is `#`; `LuongRepository@all` missing active procedure contract. | `blocked` | Task 12 direct Query Builder + coefficient DELETE/CSRF. |
| MOD-07 | Vai trò/Phân quyền: actions, human labels, reset, bulk assignment, pagination. | `backend.vaitro.index`, `backend.vaitro.permissions.edit`, `backend.taikhoan.index`; narrow catalog/middleware tests exist, UI mutation remains RED/planned. | `planned` | Tasks 10–11. |
| GATE-01 | Mỗi phase phải có RED trước sửa và GREEN sau sửa; không xóa assertion để xanh. | Task 1 only records existing baseline; no new implementation phase started and no assertion removed. | `planned` | Apply per Task 2–12. |
| GATE-02 | Route, full Laravel, frontend, build, Composer, PHP lint và diff check đều phải pass cuối cùng. | Current route/build/diff check pass; Laravel/frontend fail as baseline; Composer/PHP lint not part of Task 1 command set and not run here. | `RED` | Re-run full gate after all tasks. |
| GATE-03 | Browser desktop 1440, tablet 768, mobile 375; console/network/focus/overflow/state. | No browser session/evidence run in Task 1. Existing historical employee browser notes are not current whole-system proof. | `blocked` | Task 13 browser matrix. |
| GATE-04 | E2E DB local chỉ khi được phép, QA prefix/exact key/count/cleanup; không chạm record có sẵn. | Task 1 (historical) explicitly did not mutate DB and did not run local E2E. | `blocked` | User-approved local target + guarded QA flow. |
| GATE-05 | Đăng nhập và kiểm tra menu/route/403/404/action/profile/password với Quản trị, Nhân sự, Trưởng phòng, Nhân viên. | No current four-role browser/runtime evidence; automated auth/permission tests are narrower and SQLite-based. | `blocked` | Task 13 role matrix. |
| GATE-06 | Không gọi hoàn thành khi còn requirement thiếu test/browser/runtime evidence. | Matrix intentionally leaves RED/planned/blocked rows and does not claim system completion; this is a documentation guard, not an automated application test. | `planned` | Re-audit every row in Task 13/14. |

## Inventory module và màn hình (baseline Task 1, lịch sử)

### Web screens

| Module/screen | Canonical route(s) hiện có | View(s) | Controller/request/JS liên quan | Test evidence hiện có |
| --- | --- | --- | --- | --- |
| Dashboard | `backend.tongquan.index` → `/tong-quan` | `backend/tongquan/index.blade.php` | `TongQuanController`, `DashboardController`; inline dashboard JS | `tests/Feature/Backend/Dashboard/DashboardFeatureTest.php`, `tests/Unit/Services/DashboardServiceTest.php` |
| Nhân viên | `backend.nhanvien.index/create/show/edit/store/update/destroy` → `/nhan-vien`, `/nhan-vien/create`, `/nhan-vien/{ma_nv}`, `/nhan-vien/{ma_nv}/edit`; legacy redirects `/admin/nhan-vien/...` | `backend/nhanvien/{index,create,show,edit}.blade.php`, 7 partials | `NhanVienController`; `List/Store/UpdateNhanVienRequest`; 7 employee JS modules | 7 `tests/Feature/Backend/NhanVien/*`; 6 `tests/Frontend/nhanvien/*`; auth/unit tests |
| Phòng ban | `backend.phongban.index/create/edit/store/update/destroy` → `/phong-ban*` | `backend/phongban/{index,create,edit}.blade.php`, 2 partials | `PhongBanController`; `List/Store/UpdatePhongBanRequest`; `phongban.js` | `PhongBanFeatureTest.php`, `phongban.test.js` |
| Chức vụ | `backend.chucvu.index/create/edit/store/update/destroy` → `/chuc-vu*` | `backend/chucvu/{index,create,edit}.blade.php`, 2 partials | `ChucVuController`; `List/Store/UpdateChucVuRequest`; `chucvu.js` | `ChucVuFeatureTest.php`, `chucvu.test.js` |
| Hợp đồng | `backend.hopdong.index/create/edit/store/update/destroy` → `/hop-dong*` | `backend/hopdong/index.blade.php`, `form.blade.php` | `HopDongController`; `List/Store/UpdateHopDongRequest`; `hopdong.js` | `HopDongScaffoldTest.php`, shared route/catalog assertions |
| Chấm công | `backend.chamcong.index` → `/cham-cong` | `backend/chamcong/index.blade.php` | `ChamCongController`; `Store/Update/BatchSaveChamCongRequest`; `chamcong.js` | `ChamCongEmployeeLookupSecurityTest.php`, canonical-code tests |
| Nghỉ phép list/create | `backend.nghiphep.index` → `/nghi-phep`; `backend.nghiphep.create` → `/tao-nghi-phep` | `backend/nghiphep/index.blade.php`, `create.blade.php` | `NghiPhepController`; `Store/UpdateNghiPhepRequest`; `nghiphep.js`, `create.js`, `employee-response.js` | `NghiPhepEmployeeLookupTest.php`, `employee-response.test.js` |
| Nghỉ phép duyệt | Route hiện in inventory `backend.backend.nghiphep.duyet-nghi-phep` → `/duyet-nghi-phep` | `backend/nghiphep/duyet-nghi-phep.blade.php` | `NghiPhepController::approvalList/duyet`; `duyet-nghi-phep.js` | No dedicated approval feature/browser evidence; route/security gap is RED. |
| Lương/Hệ số | `backend.luong.index` → `/luong`; API salary/coefficient resources dưới `/api/v1/luong` | `backend/luong/index.blade.php` | `LuongController`, `LuongHeSoLuongController`, `LuongChucVuController`, `LuongPhongBanController`; 5 salary JS modules; requests | No dedicated salary feature suite; route/catalog/compatibility coverage only. |
| Vai trò | `backend.vaitro.index/data/search/show/store/update/destroy`, `backend.vaitro.permissions.edit/update` | `backend/vaitro/index.blade.php`, `permissions.blade.php` | `VaiTroController`, `PhanQuyenController`; `Store/UpdateVaiTroRequest`, `SyncVaiTroQuyenRequest`; `vaitro.js` | `VaiTroScaffoldTest.php`, `PhanQuyenScaffoldTest.php`, `ContentFourManagementTest.php`, `vaitro.test.js` |
| Phân quyền tài khoản | `backend.taikhoan.index/assign-role` → `/tai-khoan*` | `backend/taikhoan/index.blade.php` | `PhanQuyenController::accounts/assignRole`; `AssignVaiTroRequest`; no bulk request | Narrow route/catalog tests only; bulk/pagination requirement RED. |

### Shared views and assets

| Group | Files hiện có |
| --- | --- |
| Shell | `resources/views/backend/layouts/app.blade.php`, `sidebar.blade.php`, `topbar.blade.php` |
| Shared server partials | `resources/views/backend/partials/pagination.blade.php`, `pagination-summary.blade.php`, `simple-edit-modal.blade.php` |
| Shared JS | `resources/js/frontend/shared/row-action-select.js`, `list-filter.js`, `edit-modal.js` |
| Missing foundation requested by design | `shared/date-field.js`, `shared/form-feedback.js`, `partials/filter-actions.blade.php`, `action-buttons.blade.php`, `table-state.blade.php` chưa tồn tại. |

## Đường đi tiếp theo

1. Giữ `Final 2026-09-04` làm mốc automation hiện hành; không dùng các số liệu baseline lịch sử ở dưới để mô tả trạng thái hiện tại.
2. Khắc phục môi trường `node_repl`/browser và chạy browser matrix desktop 1440, tablet 768, mobile 375 với console/network/focus/overflow/state evidence.
3. Nếu được phê duyệt, chạy guarded MariaDB/QA E2E theo exact key/count/cleanup; cập nhật matrix chỉ từ evidence runtime thực tế.
