# HR System Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuẩn hóa UI toàn hệ thống và hoàn thiện hồ sơ, nghỉ phép, hợp đồng, reset mật khẩu, phân quyền hàng loạt, sau đó chứng minh bằng full tests và browser đa vai trò.

**Architecture:** Foundation-first trên Bootstrap/Blade/JavaScript hiện có. Shared formatter/component tạo contract nhỏ, sau đó từng vertical slice áp dụng bằng TDD; business authorization luôn đặt ở server và query scope, không dựa vào UI.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, JavaScript ES modules, Vite 7, Bootstrap, MariaDB 10.4.

**Spec:** `docs/superpowers/specs/2026-09-03-hr-system-consistency-design.md`

## Global Constraints

- Giữ giao diện/shell hiện tại; không port branch `frontend` và không thêm UI framework.
- Không tạo bảng/cột; chỉ bổ sung permission catalog khi preflight chứng minh ID/symbol an toàn.
- Không sửa/xóa dữ liệu có sẵn khi E2E; fixture QA phải có tag và exact-key cleanup.
- Không trả raw SQL exception, password/hash hoặc credential.
- Mỗi task: RED → minimal GREEN → focused regression → diff check.
- Bảo toàn toàn bộ thay đổi đang có trong worktree; không commit/push nếu user chưa yêu cầu.

---

### Task 1: Baseline inventory và requirement matrix

**Files:**
- Create: `docs/HR_SYSTEM_ACCEPTANCE_MATRIX.md`
- Modify: `docs/PROJECT_STATUS.md`

**Interfaces:**
- Consumes: route inventory, current Blade/JS/tests, design spec.
- Produces: bảng requirement → file → test → browser evidence dùng cho completion audit.

- [ ] **Step 1: Chụp baseline**

```powershell
git status --short --branch
php artisan route:list --except-vendor
php artisan test --compact
npm run test:frontend
npm run build
```

- [ ] **Step 2: Lập matrix có dòng riêng cho từng yêu cầu**

Mỗi dòng dùng trạng thái `planned`, `RED`, `GREEN automated`, `GREEN browser`, hoặc `blocked`, và ghi exact test/route/view chứng minh.

- [ ] **Step 3: Khóa danh sách module và màn hình**

```powershell
rg --files resources/views/backend resources/js/frontend app/Http/Controllers/Backend app/Http/Requests tests/Feature/Backend tests/Frontend
```

- [ ] **Step 4: Kiểm tra hygiene**

```powershell
git diff --check
```

### Task 2: Shared date, filter, table, action và feedback foundation

**Files:**
- Create: `resources/js/frontend/shared/date-field.js`
- Create: `resources/js/frontend/shared/form-feedback.js`
- Create: `resources/views/backend/partials/filter-actions.blade.php`
- Create: `resources/views/backend/partials/action-buttons.blade.php`
- Create: `resources/views/backend/partials/table-state.blade.php`
- Modify: `resources/views/backend/layouts/app.blade.php`
- Create: `tests/Frontend/shared/date-field.test.js`
- Create: `tests/Frontend/shared/form-feedback.test.js`
- Create: `tests/Feature/Backend/SharedUiContractTest.php`
- Modify: `package.json`

**Interfaces:**
- Produces: `formatDisplayDate(iso): string`, `parseDisplayDate(display): DateParts|null`, `toIsoDate(display): string|null`, `applyFieldErrors(form, errors, labels): void`; Blade partial props `view/edit/delete/reset/permission` URLs and labels.

- [ ] **Step 1: RED date parsing/formatting tests**

```js
assert.equal(formatDisplayDate('2026-09-03'), '03/09/2026');
assert.equal(toIsoDate('03/09/2026'), '2026-09-03');
assert.equal(toIsoDate('31/02/2026'), null);
```

- [ ] **Step 2: RED Blade contract tests**

Assert action partial renders distinct controls without `<select>`, filter partial contains labels and exact buttons `Áp dụng bộ lọc`/`Đặt lại`, table state uses `role="status|alert"`.

- [ ] **Step 3: Implement strict date helpers**

Use anchored regex `^(\d{2})\/(\d{2})\/(\d{4})$`, construct UTC date and round-trip year/month/day before returning ISO.

- [ ] **Step 4: Implement feedback helper and Blade partials**

Field error IDs are `${field.id}-error`; add `is-invalid`, `aria-invalid="true"`, and merge `aria-describedby` without deleting help text IDs.

- [ ] **Step 5: Add shared CSS contract to existing layout**

Use existing Bootstrap variables/classes; define only scoped `.filter-bar`, `.table-actions`, `.table-state`, `.date-field` rules and responsive wrap.

- [ ] **Step 6: GREEN tests and build**

```powershell
node --test tests/Frontend/shared/date-field.test.js tests/Frontend/shared/form-feedback.test.js
php artisan test tests/Feature/Backend/SharedUiContractTest.php
npm run build
```

### Task 3: Convert list action selects to visible buttons

**Files:**
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/phongban/index.blade.php`
- Modify: `resources/views/backend/chucvu/index.blade.php`
- Modify: `resources/views/backend/hopdong/index.blade.php`
- Modify: `resources/views/backend/chamcong/index.blade.php`
- Modify: `resources/views/backend/nghiphep/index.blade.php`
- Modify: `resources/views/backend/luong/index.blade.php`
- Modify: `resources/views/backend/vaitro/index.blade.php`
- Modify/remove consumers of: `resources/js/frontend/shared/row-action-select.js`
- Test: corresponding Feature and Frontend suites.

**Interfaces:**
- Consumes: Task 2 action partial.
- Produces: direct `data-action` buttons preserving current modal callbacks and server-rendered href fallbacks.

- [ ] **Step 1: RED per-module markup tests**

Assert no action `<select>` exists and authorized actions render as distinct buttons; unauthorized actions are absent.

- [ ] **Step 2: Replace one module at a time**

Order: Nhân viên → Phòng ban → Chức vụ → Hợp đồng → Nghỉ phép → Lương → Vai trò → Chấm công. After each replacement run its focused PHP/Node test before continuing.

- [ ] **Step 3: Preserve progressive fallback**

`Xem`/`Sửa` keep canonical href; JS intercepts only modal-enhanced edit. Delete submits exact form after centered confirm.

- [ ] **Step 4: Remove dead shared select code only after zero consumers**

```powershell
rg -n "row-action-select|data-row-action" resources tests
```

- [ ] **Step 5: Regression gate**

```powershell
php artisan test tests/Feature/Backend
npm run test:frontend
npm run build
```

### Task 4: Standardize filters, pagination and tables across modules

**Files:**
- Modify: all list Blade/JS/controllers/repositories named in Task 3.
- Modify: `resources/views/backend/partials/pagination.blade.php`
- Modify: `resources/views/backend/partials/pagination-summary.blade.php`
- Test: module Feature/Frontend tests.

**Interfaces:**
- Query contract: `page` positive integer; `per_page` in `[10,20,50]`; module filter keys allowlisted.
- JSON paginator contract: `{data,current_page,last_page,per_page,total,from,to}`.

- [ ] **Step 1: RED explicit-apply filter tests**

Assert typing/changing controls does not fetch/submit; clicking `Áp dụng bộ lọc` sends current values and resets page to 1; `Đặt lại` clears allowlisted filters.

- [ ] **Step 2: RED pagination tests for every module**

Create enough in-memory records for page 2 and assert filter/query preservation, total/from/to, and invalid `per_page` fallback.

- [ ] **Step 3: Implement server-rendered module pagination**

Use Query Builder `paginate($perPage)->withQueryString()` for Nhân viên, Phòng ban, Chức vụ, Hợp đồng and account permissions.

- [ ] **Step 4: Implement JSON paginator modules**

Normalize existing Chấm công, Nghỉ phép, Lương and Vai trò clients to the same shape without auto-fetch on individual filter changes.

- [ ] **Step 5: Apply table contract**

Use shared header/state/action classes, caption, scoped headers, responsive wrapper and exact colspan per table.

- [ ] **Step 6: GREEN all list tests**

```powershell
php artisan test tests/Feature/Backend
npm run test:frontend
npm run build
```

### Task 5: Normalize all dates and validation labels

**Files:**
- Modify: all Blade forms/tables containing date fields.
- Modify: `StoreNhanVienRequest`, `UpdateNhanVienRequest`, `StoreHopDongRequest`, `UpdateHopDongRequest`, `StoreNghiPhepRequest`, `UpdateNghiPhepRequest` and date-bearing Chấm công/Lương requests.
- Modify: JS API clients rendering/sending dates.
- Create: `app/Support/NormalizesDisplayDates.php`
- Test: Feature request tests and shared frontend date tests.

**Interfaces:**
- Trait method `normalizeDisplayDateFields(array $fields): void` called from `prepareForValidation()`.
- Server/API storage remains `Y-m-d`; user-visible text/input is `d/m/Y`.

- [ ] **Step 1: RED request tests for valid/invalid display dates**

Assert `03/09/2026` validates and reaches service as `2026-09-03`; `31/02/2026`, ISO typed into display field and ambiguous `3/9/26` return 422 with Vietnamese field label.

- [ ] **Step 2: Implement normalization trait**

Use `CarbonImmutable::createFromFormat('!d/m/Y', $value)` and strict format round-trip; leave invalid original value so validator rejects it.

- [ ] **Step 3: Convert Blade date inputs and JS payloads**

Use text input with `placeholder="dd/mm/yyyy"`, `inputmode="numeric"`, maxlength 10; convert only at API boundary.

- [ ] **Step 4: Standardize attributes/messages**

Each FormRequest returns Vietnamese `attributes()` and stable `messages()` for required/date/after/unique/exists.

- [ ] **Step 5: Search gate**

```powershell
rg -n 'type="date"|toLocaleDateString|split\("-"\)' resources/views/backend resources/js/frontend
```

Every remaining match must be an internal hidden canonical field or explicitly documented test fixture.

### Task 6: Sidebar, topbar, profile and change password

**Files:**
- Modify: `resources/views/backend/layouts/sidebar.blade.php`
- Modify: `resources/views/backend/layouts/topbar.blade.php`
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/Backend/ProfileController.php`
- Create: `app/Http/Requests/UpdateProfileRequest.php`
- Create: `app/Http/Requests/ChangePasswordRequest.php`
- Create: `resources/views/backend/profile/edit.blade.php`
- Create: `resources/views/backend/profile/password.blade.php`
- Modify: `app/Repositories/NhanVienRepository.php`, `app/Services/NhanVienService.php`
- Create: `tests/Feature/Backend/ProfileFeatureTest.php`

**Interfaces:**
- Routes: `backend.profile.edit`, `backend.profile.update`, `backend.profile.password.edit`, `backend.profile.password.update`.
- Service: `updateOwnProfile(string $maNv, array $profile): void`, `changeOwnPassword(string $maNv, string $current, string $new): void`.

- [ ] **Step 1: RED topbar and route tests**

Assert topbar displays actor `ten_vt`, profile/password/logout actions and never hard-codes `Quản trị viên`.

- [ ] **Step 2: RED profile allowlist tests**

Allowed: name, birth, gender, phone, email, ethnicity, CCCD/place, education, four address fields, avatar. Prohibited: `ma_nv`, `ma_pb`, `ma_cv`, `ma_vt`, `ma_tt`, `mat_khau`.

- [ ] **Step 3: Implement profile/password vertical slice**

Lookup current actor server-side; update in transaction; verify current password with Laravel Hash, require confirmed new password, hash before repository update and never return it.

- [ ] **Step 4: Sidebar submenu changes**

Chức vụ becomes submenu group. Nghỉ phép includes create/list/approve. Hệ số lương uses `/luong#salary-coefficient-card` and salary JS opens the section on matching hash.

- [ ] **Step 5: GREEN auth/profile tests and build**

```powershell
php artisan test tests/Feature/Auth tests/Feature/Backend/ProfileFeatureTest.php
npm run build
```

### Task 7: Dashboard pending leave card and department-scoped approval

**Files:**
- Modify: `app/Services/DashboardService.php`
- Modify: `app/Http/Controllers/Backend/TongQuanController.php`
- Modify: `resources/views/backend/tongquan/index.blade.php`
- Modify: `routes/web.php`, `routes/api.php`
- Modify: `app/Http/Controllers/Backend/NghiPhepController.php`
- Modify: `app/Repositories/NghiPhepRepository.php`, `app/Services/NghiPhepService.php`
- Modify: `resources/js/frontend/nghiphep/duyet-nghi-phep.js`
- Test: Dashboard/NghiPhep Feature and Frontend tests.

**Interfaces:**
- Dashboard payload: `pending_department_leave_count: int|null`; null for non-manager.
- Approval queries require actor `ma_nv`, `ma_pb`, role and permission context resolved server-side.

- [ ] **Step 1: RED role/scope tests**

Manager sees only same-department pending leaves and count; admin/HR/employee do not see manager card unless policy explicitly grants manager role; cross-department approval fails before mutation.

- [ ] **Step 2: Replace client-trusted scope with repository query scope**

Join `nhan_vien` on `nghi_phep.ma_nv`, constrain `nhan_vien.ma_pb = actor.ma_pb` and pending status. Lock exact target row on approval mutation.

- [ ] **Step 3: Secure routes**

Apply `auth` plus exact `NghiPhep.Read/Update` and manager policy to create/list/approve web/API routes as specified.

- [ ] **Step 4: Render dashboard card and approval nav**

Place card inside `#statsCards`, use canonical approval route, visible count and keyboard-focusable link.

- [ ] **Step 5: GREEN focused and security tests**

```powershell
php artisan test tests/Feature/Backend/Dashboard tests/Feature/Compatibility --filter=NghiPhep
npm run test:frontend
```

### Task 8: Employee, department, position and centered delete dialog polish

**Files:**
- Modify: `StoreNhanVienRequest.php`, `UpdateNhanVienRequest.php`
- Modify: employee address/form/dialog Blade and JS.
- Modify: department/position index Blade.
- Test: respective Feature/Frontend suites.

**Interfaces:**
- Address fields nullable strings with existing max lengths; empty normalized to null.
- Centered delete dialog owns exact target ID and restores trigger focus.

- [ ] **Step 1: RED optional address tests**

Create/update without all four address fields must pass and preserve unrelated fields; crafted protected fields remain rejected.

- [ ] **Step 2: Remove required attributes/rules and normalize blanks**

Use `nullable|string|max:*`; repository writes nullable values without changing table shape.

- [ ] **Step 3: Remove Step 1 border and center delete dialog**

Keep heading/spacing; use Bootstrap `modal-dialog-centered` or native dialog flex centering, focus trap and exact target text.

- [ ] **Step 4: Remove department number backgrounds**

Render `ma_pb` and `so_nhan_vien` as text, not badge/background; keep accessible column alignment.

- [ ] **Step 5: GREEN module tests**

```powershell
php artisan test tests/Feature/Backend/NhanVien tests/Feature/Backend/PhongBan tests/Feature/Backend/ChucVu
npm run test:frontend
```

### Task 9: Contract rules and Vietnamese currency input

**Files:**
- Modify: `app/Http/Requests/StoreHopDongRequest.php`, `UpdateHopDongRequest.php`
- Modify: `app/Services/HopDongService.php`, `app/Repositories/HopDongRepository.php`
- Modify: `resources/views/backend/hopdong/form.blade.php`, `index.blade.php`
- Modify: `resources/js/frontend/hopdong/hopdong.js`
- Test: `tests/Feature/Backend/HopDongScaffoldTest.php`, new frontend test.

**Interfaces:**
- Indefinite contract (`ma_lhd=1` resolved from current catalog name/key): `ngay_het_han=null`.
- Other types: `ngay_het_han` required and strictly after `ngay_ky`.
- Currency display accepts digits/dots, submits integer canonical value.

- [ ] **Step 1: RED contract-type matrix tests**

Test create and update for indefinite null expiry; reject supplied expiry by normalizing to null; require/validate expiry for types 2–5; reject equal/before date.

- [ ] **Step 2: Implement server rule from DB-resolved type**

Use `Rule::requiredIf` after loading `ma_lhd`; service enforces again inside transaction so UI/request drift cannot bypass it.

- [ ] **Step 3: Implement expiry UI behavior**

When type 1 selected, clear/disable expiry and submit empty canonical value; otherwise enable and require display date.

- [ ] **Step 4: Implement salary formatter**

Shared formatter displays `13.000.000`; on submit remove dots and validate integer >= 0. Preserve caret during typing where practical.

- [ ] **Step 5: GREEN focused tests/build**

```powershell
php artisan test tests/Feature/Backend/HopDongScaffoldTest.php
node --test tests/Frontend/hopdong/hopdong.test.js
npm run build
```

### Task 10: Reset-password permission and action

**Files:**
- Modify: `app/Enums/NhanVienPermission.php`
- Modify: `database/sql/du_lieu_mau.sql`, `database/sql/quyen_vai_tro.sql` only as required by active catalog.
- Modify: employee controller/service/repository/routes/views.
- Create: `app/Http/Requests/ResetNhanVienPasswordRequest.php`
- Test: employee lifecycle/auth/schema contract tests.

**Interfaces:**
- Permission symbol: `NhanVien.ResetPassword`.
- Route: `POST /nhan-vien/{ma_nv}/reset-mat-khau`, name `backend.nhanvien.reset-password`.
- Service: `resetPassword(string $targetMaNv, string $actorMaNv): void` using `nhom3@{currentYear}`.

- [ ] **Step 1: Read-only catalog preflight**

Verify active SQL inserts and current enum IDs; choose the next non-conflicting ID deterministically and add replay assertions. Do not infer from historical root dumps.

- [ ] **Step 2: RED permission/authorization tests**

Without permission: 403 and no repository call. With permission: hash changes. Self-reset and protected target policy: rejected safely. Response never includes plaintext/hash.

- [ ] **Step 3: Implement enum/catalog/Gate and server flow**

Compute password in service as `'nhom3@'.now(config('app.timezone'))->year`, hash, unset plaintext, update exact locked row.

- [ ] **Step 4: Add visible action button**

Render only when Gate and target guard allow. Confirm dialog states exact employee and default-password convention, not hash.

- [ ] **Step 5: GREEN focused tests and SQL replay gate**

```powershell
php artisan test tests/Feature/Backend/NhanVien tests/Unit/Services/NhanVienServiceCreateTest.php
php artisan test tests/Unit/Database
```

### Task 11: Paginated bulk account-role assignment

**Files:**
- Modify: `routes/web.php`
- Modify: `TaiKhoanController.php`, `PhanQuyenService.php`, `PhanQuyenRepository.php`
- Create: `app/Http/Requests/BulkAssignVaiTroRequest.php`
- Modify: `resources/views/backend/taikhoan/index.blade.php`
- Test: `tests/Feature/Backend/PhanQuyenScaffoldTest.php`

**Interfaces:**
- GET query: `keyword`, `page`, `per_page`.
- PATCH payload: `assignments: array<string ma_nv,int ma_vt>`.
- Service: `assignRoles(array $assignments, string $actorMaNv): void` all-or-nothing transaction.

- [ ] **Step 1: RED pagination and bulk atomicity tests**

Assert two pages, filter preservation, single save button, multi-row success, invalid employee/role rolls back all, actor self-role change forbidden where policy requires.

- [ ] **Step 2: Implement repository paginator and locked bulk update**

Resolve all employee/role IDs before updates, lock rows in sorted employee order, perform authorization checks, then update inside one transaction.

- [ ] **Step 3: Rename UI copy to `Phân Quyền`**

Update sidebar, breadcrumb, title, table heading and button copy; remove per-row forms/buttons.

- [ ] **Step 4: Remove technical permission symbols from role view**

Keep accessible action label; omit visible `<small>{{ ky_hieu_quyen }}</small>` while retaining value in checkbox submission.

- [ ] **Step 5: GREEN RBAC tests**

```powershell
php artisan test tests/Feature/Backend/PhanQuyenScaffoldTest.php tests/Feature/Backend/VaiTroScaffoldTest.php
```

### Task 12: Close Chấm công, Lương and Hệ số lương blockers

**Files:**
- Modify: corresponding controllers/repositories/requests/routes/Blade/JS/tests.

**Interfaces:**
- Direct Query Builder against active tables replaces calls to routines absent from active SQL.
- Hệ số lương gains complete create/read/update/delete route and CSRF behavior matching UI.

- [ ] **Step 1: Turn existing failing tests into localized RED evidence**

Run Chấm công/Lương/Nghỉ phép failing tests individually; preserve assertions and identify exact route/validation/data mismatch.

- [ ] **Step 2: Replace legacy procedure dependencies**

Implement explicit column projections and pagination on `cham_cong`, `phong_ban`, `luong`, `lich_su_he_so_luong`; transactions and row locks for mutation.

- [ ] **Step 3: Fix route/CSRF/action completeness**

Align Chấm công template URL, enable batch save only for dirty selected QA rows, wire export; add coefficient DELETE route/request/Gate and use the page CSRF meta token.

- [ ] **Step 4: Apply shared UI standards**

Explicit filter apply, standard table/pagination/date/action buttons and validation feedback across the three modules.

- [ ] **Step 5: GREEN focused suites**

```powershell
php artisan test --filter=ChamCong
php artisan test --filter=Luong
php artisan test --filter=NghiPhep
npm run test:frontend
npm run build
```

### Task 13: Full automated regression and browser multi-role acceptance

**Files:**
- Modify: `docs/HR_SYSTEM_ACCEPTANCE_MATRIX.md`
- Modify: `docs/PROJECT_STATUS.md`, `docs/CODEX_NEXT_HANDOFF.md`

**Interfaces:**
- QA fixture prefix: `QA_E2E_<date>_<nonce>`; exact keys recorded before mutation and cleanup.
- Roles: Quản trị, Nhân sự, Trưởng phòng, Nhân viên.

- [ ] **Step 1: Full automated gate**

```powershell
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
git diff --check
```

Expected: every command exit 0; no skipped/removed assertion used to hide failures.

- [ ] **Step 2: Start local server and preflight current DB**

Verify `APP_ENV=local`, host `127.0.0.1`, exact DB/server identity and counts. Use only user-approved current DB mutations and exact QA records.

- [ ] **Step 3: Browser role matrix**

For each role log in independently and verify sidebar/topbar, canonical routes, action visibility, direct unauthorized URLs, filters, pagination, date/validation, profile and password. Trưởng phòng additionally verifies same-department pending leave and cross-department denial.

- [ ] **Step 4: Browser responsive matrix**

At 1440, 768 and 375 pixels verify representative Dashboard/list/form/dialog pages, no page overflow, table scroll containment, keyboard focus, console warnings/errors and network statuses.

- [ ] **Step 5: Mutation E2E and exact cleanup**

Create/update/delete QA department, position, employee, contract, leave, attendance, salary, coefficient and QA role. Confirm exact keys/count immediately before destructive cleanup, then verify all QA counts zero and baseline records unchanged.

- [ ] **Step 6: Completion audit**

Walk every design-spec requirement and mark evidence in the matrix. Any missing browser/test evidence keeps the goal incomplete.

### Task 14: Documentation and final handoff

**Files:**
- Modify: `docs/PROJECT_STATUS.md`
- Modify: `docs/CODEX_NEXT_HANDOFF.md`
- Modify: `docs/FRONTEND_GUIDE.md`, `docs/DATABASE.md` when contracts changed.
- Modify: `docs/HR_SYSTEM_ACCEPTANCE_MATRIX.md`

- [ ] **Step 1: Update only claims proven by Task 13**

Record exact commands, counts, environment, browser roles/viewports and remaining limits.

- [ ] **Step 2: Search stale ownership/status statements**

```powershell
rg -n "ngoài ownership|chỉ gồm code Nhân viên|backend\.backend|Prototype — blocked" AGENTS.md docs
```

Update stale current-status text without rewriting historical decision records.

- [ ] **Step 3: Final hygiene**

```powershell
git diff --check
git status --short --branch
```

- [ ] **Step 4: Hand off**

Report changed files by phase, verification actually run, DB cleanup evidence, multi-role browser evidence and any truly unverified boundary. Do not commit/push unless separately authorized.
