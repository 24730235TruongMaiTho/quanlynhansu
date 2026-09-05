# Backend UI Consistency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuẩn hóa biểu tượng nút, page header, bộ lọc và khả năng ghi nhớ trạng thái sidebar trên toàn bộ backend mà không thay đổi nghiệp vụ hiện có.

**Architecture:** Giữ layout `backend.layouts.app` và Bootstrap hiện hành. Tạo một anonymous Blade component cho page header, mở rộng hai partial dùng chung cho nút/bộ lọc, gom quy tắc kích thước vào CSS chung của layout, rồi chuyển từng trang sang contract đó. Sidebar dùng trạng thái active do Blade render và `sessionStorage` do JavaScript quản lý để khôi phục submenu cùng vị trí cuộn sau điều hướng.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, Bootstrap 5.3, Bootstrap Icons 1.11, JavaScript thuần, Node test runner, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-04-backend-ui-consistency-design.md`

## Global Constraints

- Giữ nguyên Bootstrap, màu sắc, typography, sidebar/topbar và bố cục tổng thể hiện tại.
- Không đổi business logic, database, dữ liệu, API contract, route name hoặc permission catalog.
- Nút người dùng nhìn thấy dùng biểu tượng Bootstrap Icons và giữ nhãn chữ; `btn-close` kỹ thuật được giữ nguyên.
- Cùng một hành động phải dùng đúng icon mapping trong đặc tả.
- Bộ lọc chỉ chạy khi bấm `Áp dụng bộ lọc`; `Đặt lại` quay về URL canonical.
- Sidebar dùng `sessionStorage`, route active có ưu tiên cao hơn trạng thái đã lưu, mobile drawer không tự mở lại.
- Bảo toàn toàn bộ thay đổi đang có trong worktree; không hoàn nguyên hoặc định dạng lại file ngoài phạm vi.
- Không commit, merge hoặc push vì người dùng chưa yêu cầu các thao tác Git đó.
- Sau mỗi task, chỉ tạo checkpoint bằng `git diff --check` và test mục tiêu; không tạo commit.

---

## File map

### Thành phần dùng chung

- Create: `resources/views/components/backend/page-header.blade.php` — contract cho `div.page-header`, `div.left`, breadcrumb/title/description và named slot `actions`.
- Modify: `resources/views/backend/layouts/app.blade.php` — CSS dùng chung cho page header, filter grid, field width modifiers, action groups và responsive breakpoints.
- Modify: `resources/views/backend/partials/action-buttons.blade.php` — icon mapping cho Xem/Sửa/Xóa/Reset mật khẩu/Phân quyền.
- Modify: `resources/views/backend/partials/filter-actions.blade.php` — icon mapping cho Áp dụng bộ lọc/Đặt lại và class width modifier.
- Modify: `tests/Feature/Backend/SharedUiContractTest.php` — contract test cho component/partials/CSS.

### Sidebar

- Modify: `resources/views/backend/layouts/sidebar.blade.php` — group id ổn định, trạng thái route active, `aria-current`, `aria-expanded` và class `open` server-rendered.
- Modify: `public/backend/js/script.js` — lưu/khôi phục group cùng `scrollTop`, đồng bộ accordion và ARIA, null guard cho shell tùy trạng thái.
- Modify: `tests/Frontend/shared/backend-script.test.js` — contract test cho storage keys, route-active precedence, restore scroll và ARIA.
- Modify: `tests/Feature/Backend/SharedUiContractTest.php` — source/render contract cho sidebar group metadata.

### Trang danh sách

- Modify: `resources/views/backend/tongquan/index.blade.php`
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/phongban/index.blade.php`
- Modify: `resources/views/backend/chucvu/index.blade.php`
- Modify: `resources/views/backend/hopdong/index.blade.php`
- Modify: `resources/views/backend/chamcong/index.blade.php`
- Modify: `resources/views/backend/nghiphep/index.blade.php`
- Modify: `resources/views/backend/nghiphep/duyet-nghi-phep.blade.php`
- Modify: `resources/views/backend/luong/index.blade.php`
- Modify: `resources/views/backend/vaitro/index.blade.php`
- Modify: `resources/views/backend/taikhoan/index.blade.php`

Các file trên dùng page header chung, filter grid chung và icon mapping nhưng giữ nguyên id/data attribute mà JavaScript hiện tại đang sử dụng.

### Form, modal và trang chi tiết có nút

- Modify: `resources/views/backend/chucvu/create.blade.php`
- Modify: `resources/views/backend/chucvu/edit.blade.php`
- Modify: `resources/views/backend/chucvu/partials/edit-form.blade.php`
- Modify: `resources/views/backend/chucvu/partials/edit-modal-content.blade.php`
- Modify: `resources/views/backend/hopdong/form.blade.php`
- Modify: `resources/views/backend/nghiphep/create.blade.php`
- Modify: `resources/views/backend/nhanvien/create.blade.php`
- Modify: `resources/views/backend/nhanvien/edit.blade.php`
- Modify: `resources/views/backend/nhanvien/partials/action-dialogs.blade.php`
- Modify: `resources/views/backend/nhanvien/partials/edit-form.blade.php`
- Modify: `resources/views/backend/nhanvien/partials/edit-modal.blade.php`
- Modify: `resources/views/backend/nhanvien/show.blade.php`
- Modify: `resources/views/backend/partials/simple-edit-modal.blade.php`
- Modify: `resources/views/backend/phongban/create.blade.php`
- Modify: `resources/views/backend/phongban/edit.blade.php`
- Modify: `resources/views/backend/phongban/partials/edit-form.blade.php`
- Modify: `resources/views/backend/phongban/partials/edit-modal-content.blade.php`
- Modify: `resources/views/backend/profile/edit.blade.php`
- Modify: `resources/views/backend/profile/password.blade.php`
- Modify: `resources/views/backend/vaitro/permissions.blade.php`
- Modify: `resources/views/backend/layouts/topbar.blade.php` — chỉ chuẩn hóa icon nếu còn action button thiếu icon; không đổi dropdown/profile logic.

### Trạng thái dự án

- Modify: `docs/PROJECT_STATUS.md` — ghi bằng chứng test/UI mới, chỉ sau khi các gate thực sự pass.

---

### Task 1: Shared page header, filter và action-button contract

**Files:**
- Create: `resources/views/components/backend/page-header.blade.php`
- Modify: `resources/views/backend/layouts/app.blade.php`
- Modify: `resources/views/backend/partials/action-buttons.blade.php`
- Modify: `resources/views/backend/partials/filter-actions.blade.php`
- Test: `tests/Feature/Backend/SharedUiContractTest.php`

**Interfaces:**
- Produces: `<x-backend.page-header :breadcrumbs="[['label' => 'Nhân sự']]" title="Danh sách nhân viên" description="Tra cứu nhân viên.">` với optional named slot `<x-slot:actions>`.
- Produces: CSS classes `.page-header`, `.page-header__actions`, `.filter-bar__fields`, `.filter-bar__field--wide`, `.filter-bar__field--compact`, `.filter-bar__actions`, `.button-icon`.
- Preserves: các biến đầu vào hiện có của `backend.partials.action-buttons` và `backend.partials.filter-actions`.

- [ ] **Step 1: Viết contract test thất bại cho icon và component**

Thêm các assertion cụ thể vào `SharedUiContractTest`:

```php
public function test_shared_actions_render_the_canonical_icons(): void
{
    $rendered = $this->view('backend.partials.action-buttons', [
        'viewUrl' => '/employees/1',
        'editUrl' => '/employees/1/edit',
        'deleteUrl' => '/employees/1',
        'resetUrl' => '/employees/1/reset-password',
        'permissionUrl' => '/roles/1/permissions',
    ])->render();

    self::assertStringContainsString('bi-eye', $rendered);
    self::assertStringContainsString('bi-pencil-square', $rendered);
    self::assertStringContainsString('bi-trash', $rendered);
    self::assertStringContainsString('bi-key', $rendered);
    self::assertStringContainsString('bi-shield-lock', $rendered);
}

public function test_filter_actions_render_canonical_icons(): void
{
    $rendered = $this->view('backend.partials.filter-actions', [
        'action' => '/employees',
        'resetUrl' => '/employees',
        'filters' => [['name' => 'search', 'label' => 'Tìm kiếm', 'type' => 'search']],
    ])->render();

    self::assertStringContainsString('bi-funnel', $rendered);
    self::assertStringContainsString('bi-arrow-counterclockwise', $rendered);
}
```

Thêm source assertion cho anonymous component có `class="left"`, `page-header__actions`, title id và description.

- [ ] **Step 2: Chạy test để xác nhận RED**

Run:

```powershell
php artisan test tests/Feature/Backend/SharedUiContractTest.php
```

Expected: FAIL vì các icon canonical và component page header chưa tồn tại.

- [ ] **Step 3: Tạo anonymous component page header tối thiểu**

Component dùng props và named slot, không render HTML từ chuỗi:

```blade
@props([
    'title',
    'titleId' => 'page-title',
    'description' => null,
    'breadcrumbs' => [],
])

<header {{ $attributes->class(['page-header']) }}>
    <div class="left">
        <div>
            @if ($breadcrumbs !== [])
                <nav aria-label="Đường dẫn trang">
                    <ol class="breadcrumb mb-1">
                        @foreach ($breadcrumbs as $breadcrumb)
                            <li class="breadcrumb-item {{ empty($breadcrumb['url']) ? 'active' : '' }}"
                                @if (empty($breadcrumb['url'])) aria-current="page" @endif>
                                @if (! empty($breadcrumb['url']))
                                    <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                                @else
                                    {{ $breadcrumb['label'] }}
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            <h1 class="h3 fw-semibold mb-1" id="{{ $titleId }}">{{ $title }}</h1>
            @if ($description)
                <p class="text-secondary mb-0">{{ $description }}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="page-header__actions">{{ $actions }}</div>
    @endisset
</header>
```

- [ ] **Step 4: Chuẩn hóa CSS dùng chung**

Trong style dùng chung của layout, thay flex width tự do bằng contract có giới hạn:

```css
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.page-header .left { min-width: 0; }
.page-header__actions,
.filter-bar__actions,
.table-actions {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
}

.filter-bar__fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 16rem));
    justify-content: start;
    gap: 1rem;
    min-width: 0;
}

.filter-bar__field { min-width: 0; }
.filter-bar__field--wide { grid-column: span 2; max-width: 32rem; }
.filter-bar__field--compact { max-width: 12rem; }
.filter-bar .form-control,
.filter-bar .form-select { min-height: 2.375rem; }

.button-icon { margin-inline-end: .375rem; }

@media (max-width: 767.98px) {
    .page-header { flex-direction: column; }
    .page-header__actions { width: 100%; }
    .filter-bar__fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .filter-bar__field--wide { grid-column: 1 / -1; max-width: none; }
}

@media (max-width: 575.98px) {
    .filter-bar__fields { grid-template-columns: 1fr; width: 100%; }
    .filter-bar__field--wide { grid-column: auto; }
}
```

Không để selector mới bị ghi đè bởi `row`, `col-*` hoặc CSS cục bộ; task 3 sẽ loại các class layout xung đột ở từng filter.

- [ ] **Step 5: Thêm icon vào hai partial dùng chung**

Mẫu bắt buộc:

```blade
<i class="bi bi-eye button-icon" aria-hidden="true"></i>{{ $viewLabel }}
<i class="bi bi-funnel button-icon" aria-hidden="true"></i>{{ $applyLabel }}
```

Áp dụng đủ mapping: eye, pencil-square, trash, key, shield-lock, funnel và arrow-counterclockwise. Giữ nguyên method, CSRF, permission flag, URL và confirm attribute.

- [ ] **Step 6: Chạy test và checkpoint**

Run:

```powershell
php artisan test tests/Feature/Backend/SharedUiContractTest.php
git diff --check -- resources/views/components/backend/page-header.blade.php resources/views/backend/layouts/app.blade.php resources/views/backend/partials/action-buttons.blade.php resources/views/backend/partials/filter-actions.blade.php tests/Feature/Backend/SharedUiContractTest.php
```

Expected: PASS; không có whitespace error. Không commit.

---

### Task 2: Sidebar active route và session persistence

**Files:**
- Modify: `resources/views/backend/layouts/sidebar.blade.php`
- Modify: `public/backend/js/script.js`
- Modify: `tests/Frontend/shared/backend-script.test.js`
- Modify: `tests/Feature/Backend/SharedUiContractTest.php`

**Interfaces:**
- Produces: `data-sidebar-group="employees|departments|positions|contracts|attendance|leave|salary|authorization"` trên group `<li>`.
- Produces: `data-route-active="true|false"` trên group, `aria-expanded` trên trigger và `aria-current="page"` trên child link active.
- Produces: storage keys `qlns.sidebar.openGroup` và `qlns.sidebar.scrollTop`.
- Consumes: `.sidebar-menu-wrapper`, `[data-toggle="submenu"]`, `.sub-menu`, `.menu-arrow` hiện có.

- [ ] **Step 1: Viết test RED cho contract sidebar**

Thêm các assertion frontend cụ thể:

```js
test('sidebar persists the open group and scroll position per session', () => {
    assert.match(source, /qlns\.sidebar\.openGroup/);
    assert.match(source, /qlns\.sidebar\.scrollTop/);
    assert.match(source, /sessionStorage\.setItem/);
    assert.match(source, /sessionStorage\.getItem/);
});

test('active route wins and submenu aria state stays synchronized', () => {
    assert.match(source, /data-route-active/);
    assert.match(source, /setAttribute\(['"]aria-expanded['"]/);
    assert.match(source, /requestAnimationFrame/);
});
```

Thêm PHP source assertion cho tám group id và `aria-current="page"`.

- [ ] **Step 2: Chạy test để xác nhận RED**

Run:

```powershell
node --test tests/Frontend/shared/backend-script.test.js
php artisan test tests/Feature/Backend/SharedUiContractTest.php
```

Expected: FAIL ở storage/group metadata chưa tồn tại.

- [ ] **Step 3: Server-render trạng thái route active**

Ở đầu sidebar, tính boolean bằng route names canonical, ví dụ:

```blade
@php
    $employeeGroupActive = request()->routeIs('backend.nhanvien.*');
    $leaveGroupActive = request()->routeIs('backend.nghiphep.*');
    $salaryGroupActive = request()->routeIs('backend.luong.*');
    $authorizationGroupActive = request()->routeIs('backend.vaitro.*', 'backend.taikhoan.*');
@endphp
```

Mỗi group dùng contract:

```blade
<li class="nav-item" data-sidebar-group="leave" data-route-active="{{ $leaveGroupActive ? 'true' : 'false' }}">
    <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $leaveGroupActive ? 'true' : 'false' }}">
        <i class="bi bi-calendar-x-fill" aria-hidden="true"></i>
        <span class="nav-title">Quản lý nghỉ phép</span>
        <i class="bi bi-chevron-down menu-arrow {{ $leaveGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
    </a>
    <ul class="sub-menu {{ $leaveGroupActive ? 'open' : '' }}">
        <li class="nav-item">
            <a href="{{ route('backend.nghiphep.index') }}" class="nav-link {{ request()->routeIs('backend.nghiphep.index') ? 'active' : '' }}"
               @if (request()->routeIs('backend.nghiphep.index')) aria-current="page" @endif>
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span class="nav-title">Danh sách nghỉ phép</span>
            </a>
        </li>
    </ul>
</li>
```

Áp dụng đủ tám group, không thay permission conditions hoặc URLs.

- [ ] **Step 4: Tách helper JS trạng thái submenu**

Thêm helper an toàn và đồng bộ một nguồn trạng thái:

```js
const SIDEBAR_STORAGE = {
    openGroup: 'qlns.sidebar.openGroup',
    scrollTop: 'qlns.sidebar.scrollTop'
};

function readSessionValue(key) {
    try { return window.sessionStorage.getItem(key); } catch (error) { return null; }
}

function writeSessionValue(key, value) {
    try { window.sessionStorage.setItem(key, String(value)); } catch (error) {}
}

function setSubMenuState(group, expanded) {
    const trigger = group.querySelector('[data-toggle="submenu"]');
    const subMenu = group.querySelector(':scope > .sub-menu');
    const arrow = trigger ? trigger.querySelector('.menu-arrow') : null;
    if (!trigger || !subMenu) return;
    subMenu.classList.toggle('open', expanded);
    arrow?.classList.toggle('rotated', expanded);
    trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
}
```

`toggleSubMenu` gọi `setSubMenuState`, đóng sibling bằng cùng helper và ghi group id khi mở. Không xóa stored group khi desktop sidebar chỉ tạm collapsed.

- [ ] **Step 5: Lưu và khôi phục group/scroll**

Contract restore:

```js
function restoreSidebarState() {
    const activeGroup = document.querySelector('[data-sidebar-group][data-route-active="true"]');
    const storedId = readSessionValue(SIDEBAR_STORAGE.openGroup);
    const storedGroup = storedId
        ? document.querySelector('[data-sidebar-group="' + CSS.escape(storedId) + '"]')
        : null;
    const groupToOpen = activeGroup || storedGroup;

    document.querySelectorAll('[data-sidebar-group]').forEach(function(group) {
        setSubMenuState(group, group === groupToOpen);
    });

    const wrapper = document.querySelector('.sidebar-menu-wrapper');
    const savedScrollTop = Number(readSessionValue(SIDEBAR_STORAGE.scrollTop));
    if (wrapper && Number.isFinite(savedScrollTop)) {
        requestAnimationFrame(function() { wrapper.scrollTop = savedScrollTop; });
    }
}
```

Lưu scroll trên event `scroll` của wrapper và `pagehide`; lưu lại ngay trước khi child `.sub-menu .nav-link` điều hướng. Không khôi phục `.mobile-open`.

- [ ] **Step 6: Chạy targeted tests và checkpoint**

Run:

```powershell
node --test tests/Frontend/shared/backend-script.test.js
php artisan test tests/Feature/Backend/SharedUiContractTest.php
git diff --check -- resources/views/backend/layouts/sidebar.blade.php public/backend/js/script.js tests/Frontend/shared/backend-script.test.js tests/Feature/Backend/SharedUiContractTest.php
```

Expected: PASS và không có whitespace error. Không commit.

---

### Task 3: Migrate page headers, filters và danh sách Vai trò

**Files:**
- Modify: `resources/views/backend/tongquan/index.blade.php`
- Modify: `resources/views/backend/nhanvien/index.blade.php`
- Modify: `resources/views/backend/phongban/index.blade.php`
- Modify: `resources/views/backend/chucvu/index.blade.php`
- Modify: `resources/views/backend/hopdong/index.blade.php`
- Modify: `resources/views/backend/chamcong/index.blade.php`
- Modify: `resources/views/backend/nghiphep/index.blade.php`
- Modify: `resources/views/backend/nghiphep/duyet-nghi-phep.blade.php`
- Modify: `resources/views/backend/luong/index.blade.php`
- Modify: `resources/views/backend/vaitro/index.blade.php`
- Modify: `resources/views/backend/taikhoan/index.blade.php`
- Modify: `tests/Feature/Backend/SharedUiContractTest.php`
- Preserve and run: module Feature/Frontend tests already targeting these pages.

**Interfaces:**
- Consumes: `<x-backend.page-header>`, `.filter-bar__field--wide`, `.filter-bar__field--compact`, icon mapping from Task 1.
- Preserves: every existing element id, `data-*`, route/action/method/name/value, permission guard and JavaScript hook.
- Produces: role filter in its own card below page header; all list headers use `left` structure through the component.

- [ ] **Step 1: Viết RED source contract cho danh sách**

Trong `SharedUiContractTest`, kiểm tra từng path trong một data provider:

```php
yield 'employees' => ['resources/views/backend/nhanvien/index.blade.php'];
yield 'departments' => ['resources/views/backend/phongban/index.blade.php'];
yield 'positions' => ['resources/views/backend/chucvu/index.blade.php'];
yield 'contracts' => ['resources/views/backend/hopdong/index.blade.php'];
yield 'attendance' => ['resources/views/backend/chamcong/index.blade.php'];
yield 'leave' => ['resources/views/backend/nghiphep/index.blade.php'];
yield 'leave approval' => ['resources/views/backend/nghiphep/duyet-nghi-phep.blade.php'];
yield 'salary' => ['resources/views/backend/luong/index.blade.php'];
yield 'roles' => ['resources/views/backend/vaitro/index.blade.php'];
yield 'accounts' => ['resources/views/backend/taikhoan/index.blade.php'];
```

Mỗi file phải chứa `<x-backend.page-header` và không còn `col-lg-7` trong filter. Riêng Vai trò phải có filter card tách khỏi page header.

- [ ] **Step 2: Chạy test để xác nhận RED**

Run:

```powershell
php artisan test tests/Feature/Backend/SharedUiContractTest.php
```

Expected: FAIL cho các trang chưa dùng component hoặc filter còn class width cũ.

- [ ] **Step 3: Chuyển từng page header sang component**

Mẫu áp dụng, giữ nguyên tiêu đề/mô tả hiện có:

```blade
<x-backend.page-header
    title="Danh sách nhân viên"
    title-id="page-title"
    description="Tra cứu thông tin nhân viên theo phòng ban, chức vụ và trạng thái làm việc."
    :breadcrumbs="[
        ['label' => 'Nhân sự'],
        ['label' => 'Danh sách nhân viên'],
    ]"
>
    <x-slot:actions>
        @if ($canCreate)
            <a class="btn btn-primary" href="{{ route('backend.nhanvien.create') }}">
                <i class="bi bi-plus-circle button-icon" aria-hidden="true"></i>Thêm nhân viên
            </a>
        @endif
    </x-slot:actions>
</x-backend.page-header>
```

Không thêm slot rỗng. Dashboard giữ nút Làm mới; Duyệt nghỉ phép giữ hộp phòng ban trong actions; Lương giữ badge read-only gần title và hai action hiện tại trong actions mà không đổi `hidden`/permission attributes.

- [ ] **Step 4: Chuẩn hóa filter markup**

Ở mỗi form filter:

- Bỏ `row`, `col-*`, `flex-fill` đang điều khiển độ rộng trái contract.
- Gắn `filter-bar__field--wide` cho keyword/search.
- Gắn `filter-bar__field--compact` cho `per_page`, tháng/năm hoặc select ngắn.
- Dùng một `.filter-bar__actions` ở cuối form.
- Giữ nguyên ids và data hooks: `apply-filter-btn`, `clear-filter-btn`, `leave-filter-apply`, `role-reset`, các form ids và filter names.
- Thêm `bi-funnel` và `bi-arrow-counterclockwise`; thay ký tự `↻`/`⌕` và inline SVG tương đương bằng Bootstrap Icon canonical.

Mẫu:

```blade
<form class="filter-bar filter-bar--embedded" method="GET" action="{{ route('backend.nhanvien.index') }}" data-employee-filter>
    <div class="filter-bar__fields">
        <div class="filter-bar__field filter-bar__field--wide">
            <label class="form-label" for="tu_khoa">Từ khóa</label>
            <input class="form-control" id="tu_khoa" name="tu_khoa" type="search" value="{{ $filters['tu_khoa'] }}">
        </div>
        <div class="filter-bar__field filter-bar__field--compact">
            <label class="form-label" for="per_page">Số dòng mỗi trang</label>
            <select class="form-select" id="per_page" name="per_page">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>
    <div class="filter-bar__actions">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-funnel button-icon" aria-hidden="true"></i>Áp dụng bộ lọc
        </button>
        <a class="btn btn-outline-secondary" href="{{ route('backend.nhanvien.index') }}">
            <i class="bi bi-arrow-counterclockwise button-icon" aria-hidden="true"></i>Đặt lại
        </a>
    </div>
</form>
```

- [ ] **Step 5: Sửa riêng danh sách Vai trò**

- Header chỉ chứa left content và action `Thêm vai trò`.
- Tạo card `Bộ lọc vai trò` ngay sau feedback/header, giữ `role-search-form`, `role-search`, `role-page-size`, `role-reset`.
- Danh sách/table/pagination vẫn ở card riêng và giữ toàn bộ id cho `resources/js/frontend/vaitro/vaitro.js`.
- Icon thêm mới là `bi-plus-circle`; modal save là `bi-floppy`; cancel là `bi-x-circle`; row action do JS render dùng đúng eye/edit/trash/shield-lock mapping.

- [ ] **Step 6: Chạy targeted module tests**

Run:

```powershell
php artisan test tests/Feature/Backend/SharedUiContractTest.php tests/Feature/Backend/NhanVien tests/Feature/Backend/PhongBan tests/Feature/Backend/ChucVu tests/Feature/Backend/HopDongScaffoldTest.php tests/Feature/Backend/VaiTroScaffoldTest.php tests/Feature/Backend/VaiTroListContractTest.php tests/Feature/Backend/ManagerLeaveApprovalTest.php
node --test tests/Frontend/vaitro/vaitro.test.js tests/Frontend/nghiphep/approval-filter.test.js tests/Frontend/shared/backend-script.test.js
git diff --check -- resources/views/backend tests/Feature/Backend/SharedUiContractTest.php
```

Expected: PASS. Không commit.

---

### Task 4: Icon audit cho form, modal, detail và các nút sinh bằng JavaScript

**Files:**
- Modify: toàn bộ file form/modal/detail đã liệt kê trong File map.
- Modify when an action label is generated dynamically: `resources/js/frontend/chamcong/chamcong.js`, `resources/js/frontend/chucvu/chucvu.js`, `resources/js/frontend/hopdong/hopdong.js`, `resources/js/frontend/luong/luong.js`, `resources/js/frontend/luong/luongHeSo.js`, `resources/js/frontend/nghiphep/duyet-nghi-phep.js`, `resources/js/frontend/nghiphep/nghiphep.js`, `resources/js/frontend/nhanvien/employee-page.js`, `resources/js/frontend/phongban/phongban.js`, `resources/js/frontend/vaitro/vaitro.js`.
- Test: existing frontend tests beside each modified JS module.
- Test: `tests/Feature/Backend/SharedUiContractTest.php` for static Blade audit.

**Interfaces:**
- Consumes: icon table from the spec and `.button-icon` from Task 1.
- Preserves: button ids, types, names, form ownership, Bootstrap modal attributes, permissions, confirm behavior and visible Vietnamese labels.

- [ ] **Step 1: Tạo audit RED có whitelist rõ ràng**

Thêm test đọc Blade button markup và cho phép duy nhất các control kỹ thuật:

```php
$technicalButtonClasses = ['btn-close', 'toggle-btn', 'nav-link'];
```

Test phải phát hiện thẻ `<button>` hoặc `<a>` có class chứa `btn`, có nhãn hành động nhưng không chứa thẻ `<i>` mang class Bootstrap Icon. Không bắt spinner, tab button, pagination link hoặc sidebar collapse button phải có text icon mới nếu chúng đã có visual/accessibility riêng.

Với JS, bổ sung assertion trực tiếp ở test module rằng template action chứa icon canonical, ví dụ:

```js
assert.match(source, /bi-pencil-square/);
assert.match(source, /bi-trash/);
```

- [ ] **Step 2: Chạy audit để xác nhận RED**

Run:

```powershell
php artisan test tests/Feature/Backend/SharedUiContractTest.php
npm run test:frontend
```

Expected: FAIL ở các action button chưa có icon; ghi lại danh sách file fail để sửa đúng phạm vi.

- [ ] **Step 3: Sửa nút tĩnh trong Blade**

Áp dụng mapping chính xác:

```blade
<button class="btn btn-primary" type="submit">
    <i class="bi bi-floppy button-icon" aria-hidden="true"></i>Lưu thay đổi
</button>
<a class="btn btn-outline-secondary" href="{{ route('backend.nhanvien.index') }}">
    <i class="bi bi-arrow-left button-icon" aria-hidden="true"></i>Quay lại
</a>
<button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
    <i class="bi bi-x-circle button-icon" aria-hidden="true"></i>Hủy
</button>
```

Không thay ký hiệu của input, status badge, navigation tabs hoặc icon trang trí không phải nút. `btn-close` giữ nguyên và phải có `aria-label="Đóng"`.

- [ ] **Step 4: Sửa nút sinh động trong JavaScript**

Trong các template string, giữ data attribute/callback hiện hữu và đổi phần hiển thị:

```js
button.innerHTML = '<i class="bi bi-pencil-square button-icon" aria-hidden="true"></i>Sửa';
```

Không dùng `innerHTML` với dữ liệu người dùng; chỉ icon và nhãn cố định được phép nằm trong literal. Text dữ liệu tiếp tục đi qua `textContent`/escape helper hiện hữu.

- [ ] **Step 5: Chạy PHP/Frontend targeted tests và audit lần hai**

Run:

```powershell
php artisan test tests/Feature/Backend/SharedUiContractTest.php
npm run test:frontend
git diff --check -- resources/views/backend resources/js/frontend tests/Feature/Backend/SharedUiContractTest.php tests/Frontend
```

Expected: PASS, audit không còn button hành động thiếu icon ngoài whitelist. Không commit.

---

### Task 5: Full regression, browser acceptance và cập nhật trạng thái

**Files:**
- Modify after evidence: `docs/PROJECT_STATUS.md`
- Verify only: all modified files from Tasks 1–4.

**Interfaces:**
- Consumes: toàn bộ UI contract và tests từ Tasks 1–4.
- Produces: bằng chứng tự động và browser acceptance có thể báo cáo; không tạo dữ liệu DB mới.

- [ ] **Step 1: Kiểm tra PHP syntax, route và Composer**

Run:

```powershell
$phpFiles = git diff --name-only -- '*.php'; foreach ($file in $phpFiles) { php -l $file }
php artisan route:list --except-vendor
composer validate --no-check-publish
```

Expected: mọi lint `No syntax errors detected`, route list không lỗi/không trùng tên, Composer valid.

- [ ] **Step 2: Chạy full automated suite**

Run:

```powershell
php artisan test
npm run test:frontend
npm run build
git diff --check
```

Expected: tất cả test pass, Vite build pass, không có whitespace error. Không diễn giải build pass thành browser pass.

- [ ] **Step 3: Nghiệm thu browser desktop**

Ở viewport 1440 px, đăng nhập bằng tài khoản đã có và kiểm tra:

1. Nhân viên: header, năm filter fields, Apply/Reset icons, row action icons.
2. Phòng ban và Chức vụ: keyword không kéo dài hết card; page-size và actions thẳng hàng.
3. Vai trò: header tách khỏi filter card; create/filter/modal/table action icons đúng.
4. Chấm công, Nghỉ phép, Duyệt nghỉ phép và Lương: filter IDs vẫn hoạt động; submit/reset thay đổi kết quả như trước.
5. Console không có JavaScript error; network không có request 4xx/5xx ngoài validation được chủ động tạo.

- [ ] **Step 4: Nghiệm thu sidebar persistence**

1. Mở group Nghỉ phép.
2. Cuộn sidebar đến vị trí group.
3. Chọn `Danh sách nghỉ phép`, xác minh group vẫn mở và link có `aria-current="page"`.
4. Chọn `Duyệt nghỉ phép`, xác minh scroll không nhảy về đầu.
5. Mở group Vai trò và phân quyền, chọn Vai trò rồi Phân Quyền; mỗi trang mở đúng group active.
6. Thu nhỏ xuống 375 px; điều hướng không tự mở mobile drawer nhưng submenu active và scroll được khôi phục khi drawer được mở thủ công.

- [ ] **Step 5: Nghiệm thu responsive filter**

Kiểm tra ở 768 px và 375 px trên Nhân viên, Vai trò, Duyệt nghỉ phép và Lương:

- Không có horizontal overflow.
- Label/control/action không chồng nhau.
- Tablet tối đa hai cột hợp lý; mobile một cột.
- Button text và icon không bị cắt.
- Focus keyboard nhìn thấy và thứ tự focus theo DOM.

- [ ] **Step 6: Cập nhật trạng thái bằng số liệu thực**

Trong `docs/PROJECT_STATUS.md`, ghi ngày 2026-09-04, số test/assertion thực tế, kết quả build, route inventory và các viewport/browser đã kiểm tra. Nếu browser hoặc một role không kiểm tra được, ghi `chưa xác minh` thay vì `pass`.

- [ ] **Step 7: Final working-tree review**

Run:

```powershell
git status --short --branch
git diff --stat
git diff --check
```

Expected: chỉ có thay đổi task cùng các thay đổi tồn tại trước đó; file `AIAssistantInput-*.chatInput` không bị stage/sửa; không có commit hoặc push.
