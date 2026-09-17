# Default Account Self-Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mọi tài khoản nhân viên đang hoạt động có thể tự xem/cập nhật hồ sơ, tạo và xem đơn nghỉ phép, xem hợp đồng, xem lương và xem chấm công của chính mình mà không cần quyền quản trị module.

**Architecture:** Tạo một boundary duy nhất lấy mã nhân viên từ actor đã xác thực, rồi dựng các route self-service chỉ dùng `auth` và luôn truyền mã này xuống data layer bằng tham số bắt buộc. Hợp đồng, lương và chấm công dùng trang Blade server-rendered read-only cùng API chính chủ; luồng nghỉ phép hiện có chuyển GET/POST cá nhân sang endpoint `cua-toi`, trong khi toàn bộ route quản trị và quyền `*.Read/Insert/Update/Delete` vẫn giữ nguyên.

**Tech Stack:** Laravel 12, PHP 8.2+, Blade, Query Builder/Eloquent hiện hữu, Bootstrap, JavaScript ES modules, PHPUnit 11, Node test runner, Vite 7, MariaDB 10.4.32/SQLite disposable.

**Spec:** `docs/superpowers/specs/2026-09-17-default-account-self-service-design.md`

## Global Constraints

- Bảo toàn toàn bộ file dirty/untracked hiện có; không revert hoặc viết đè thay đổi feedback_v6/chấm công của đồng nghiệp.
- Người dùng đã cho phép triển khai trên checkout `main` hiện tại; không tạo worktree/branch mới, không commit, không push, không fetch, không merge và không rebase.
- Không chạy SQL trên database live. Chỉ kiểm thử mutation bằng SQLite in-memory hoặc MariaDB disposable có guard.
- Nguồn SQL active theo thứ tự `database/sql/tao_bang.sql` → `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql` → `database/sql/salary/2026_09_09_001_luong_functions.sql`.
- Không cấp `Gate::before`, không sao chép quyền quản trị vào mọi vai trò và không nới middleware của route quản trị hiện hữu.
- Mã nhân viên self-service chỉ lấy từ actor `NhanVien` đã xác thực, phải khớp `\A[0-9]{5}\z`; mọi selector nhận diện do client gửi phải bị từ chối hoặc không được dùng.
- Các trang self-service không có danh sách nhân viên/phòng ban, mutation hợp đồng/lương/chấm công, import/export hoặc action quản trị.
- TDD bắt buộc: thêm một hành vi kiểm thử, chạy và quan sát RED đúng nguyên nhân, mới sửa production, rồi chạy GREEN và regression liên quan.
- Runtime UI tiếp tục dùng `backend.layouts.app`; không tích hợp shell từ branch `frontend` và không tạo design system mới.
- Không trả SQLSTATE, stack trace, hash mật khẩu, dữ liệu nhân viên khác hoặc filesystem path trong response.
- Do commit chưa được cho phép, mỗi bước “checkpoint” chỉ ghi nhận `git diff --check` và `git status --short`; không chạy `git add` hoặc `git commit`.

## Threat Model

| Ranh giới | Abuse case | Kiểm soát bắt buộc |
| --- | --- | --- |
| Query/body HTTP | Gửi `ma_nv=00002` để đọc/tạo thay người khác | FormRequest `prohibited` và server luôn dùng `CurrentEmployee::id()` |
| Route self-service | Dùng route mới để đi vào action quản trị | Route tĩnh `cua-toi` đứng trước resource; middleware chỉ `auth`; controller/action riêng read-only |
| Data layer | Quên filter hoặc dùng `LIKE` làm lộ nhiều nhân viên | Phương thức exact-owner nhận `string $maNv` bắt buộc và dùng `where('ma_nv', '=', $maNv)` |
| Frontend | UI cũ gọi lookup nhân viên/phòng ban hoặc export | Blade self-service riêng; test render không có selector/action quản trị |
| RBAC | Cấp `*.Read` đại trà làm mở trang công ty | Seed role 5 không có `(25,26,33)`; management middleware và catalog giữ nguyên |
| Error path | Exception DB lộ SQL/PII | `report()` nội bộ và thông báo công khai ổn định; test exception path |
| Existing DB | Script cũ tiếp tục cấp quyền quản trị role 5 | Script cũ fail-closed là superseded; cleanup mới cần biến approval và chỉ xóa exact target |

## File Structure

### Tạo mới

- `app/Support/CurrentEmployee.php`: boundary duy nhất biến actor đã đăng nhập thành mã nhân viên canonical hoặc dừng `403`.
- `app/Http/Requests/ListOwnNghiPhepRequest.php`: allowlist filter lịch sử nghỉ phép và cấm selector quản trị.
- `app/Http/Requests/StoreOwnNghiPhepRequest.php`: validate đơn tự tạo và cấm `ma_nv`/trạng thái client.
- `app/Http/Requests/ListOwnLuongRequest.php`: validate kỳ lương/phân trang self-service.
- `app/Http/Requests/ListOwnChamCongRequest.php`: validate tháng/năm/phân trang self-service.
- `resources/views/backend/selfservice/hopdong.blade.php`: danh sách hợp đồng chính chủ, server-rendered, read-only.
- `resources/views/backend/selfservice/luong.blade.php`: bảng lương chính chủ, server-rendered, read-only.
- `resources/views/backend/selfservice/chamcong.blade.php`: chấm công chính chủ, server-rendered, read-only.
- `resources/js/frontend/nghiphep/own-leave-contract.js`: URL và payload builder thuần cho POST nghỉ phép chính chủ.
- `tests/Unit/Support/CurrentEmployeeTest.php`: boundary actor hợp lệ/sai loại/sai mã.
- `tests/Feature/Backend/SelfServiceAccessTest.php`: ma trận auth/route/sidebar và management deny.
- `tests/Feature/Backend/SelfServiceLeaveTest.php`: GET/POST nghỉ phép chính chủ và spoofing.
- `tests/Feature/Backend/SelfServiceContractTest.php`: repository/page hợp đồng chính chủ.
- `tests/Feature/Backend/SelfServiceSalaryTest.php`: repository/API/page lương chính chủ và lỗi an toàn.
- `tests/Feature/Backend/SelfServiceAttendanceTest.php`: API/page/summary chấm công chính chủ.
- `tests/Frontend/nghiphep/own-leave-contract.test.js`: kiểm thử module payload/URL bằng import thật.
- `database/sql/rbac/2026_09_17_001_remove_employee_management_permissions.sql`: remediation có approval guard cho database hiện hữu; không tự chạy.

### Sửa hiện hữu

- `routes/web.php`, `routes/api.php`: route self-service và giữ nguyên middleware quản trị.
- `app/Http/Controllers/Backend/NghiPhepController.php`: dùng boundary chung cho GET/POST `cua-toi`.
- `app/Http/Controllers/Backend/HopDongController.php`: render danh sách hợp đồng của actor.
- `app/Http/Controllers/Backend/LuongController.php`: API và page lương của actor.
- `app/Http/Controllers/Backend/ChamCongController.php`: API và page chấm công của actor, dùng chung service query với management detail.
- `app/Contracts/HopDongRepositoryContract.php`, `app/Contracts/HopDongServiceContract.php`, `app/Repositories/HopDongRepository.php`, `app/Services/HopDongService.php`: contract phân trang exact-owner.
- `app/Repositories/LuongRepository.php`, `app/Services/LuongService.php`: query lương tối thiểu exact-owner và lỗi công khai an toàn.
- `app/Services/ChamCongService.php`: query danh sách + summary exact-owner dùng chung.
- `resources/views/backend/nghiphep/create.blade.php`, `resources/js/frontend/nghiphep/create.js`: bỏ gate Insert ở client, POST endpoint cá nhân, không gửi identity/status.
- `resources/views/backend/layouts/sidebar.blade.php`: nhóm “Thông tin của tôi” luôn có cho actor; nhóm quản trị vẫn RBAC.
- `database/sql/du_lieu_mau.sql`, `quan_ly_nhan_vien_session_update.sql`: bỏ ba mapping role 5 khỏi fresh source/snapshot.
- `database/sql/rbac/2026_09_16_001_add_employee_self_service_permissions.sql`: chuyển thành guard superseded, không còn grant.
- `tests/Feature/Backend/ContentFourManagementTest.php`, `tests/Feature/Backend/NghiPhepCreatePermissionContractTest.php`, `tests/Feature/Backend/FeedbackV6RbacContractTest.php`, `tests/Unit/Database/PortableSqlContractTest.php`, `tests/Integration/MariaDb/FreshEmployeeSchemaContractTest.php`: cập nhật contract mới.
- `docs/DATABASE.md`, `docs/PROJECT_STATUS.md`, `docs/CODEX_NEXT_HANDOFF.md`: cập nhật trạng thái và giới hạn live DB/browser.

---

### Task 1: Current employee identity boundary

**Files:**
- Create: `app/Support/CurrentEmployee.php`
- Create: `tests/Unit/Support/CurrentEmployeeTest.php`

**Interfaces:**
- Consumes: `App\Models\NhanVien::getAuthIdentifier()`.
- Produces: `CurrentEmployee::id(?object $actor): string`; trả đúng mã 5 chữ số hoặc ném HTTP `403` với thông báo công khai.

- [ ] **Step 1: Viết unit test RED cho actor hợp lệ và hai abuse case**

```php
<?php

namespace Tests\Unit\Support;

use App\Models\NhanVien;
use App\Support\CurrentEmployee;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class CurrentEmployeeTest extends TestCase
{
    public function test_it_returns_only_a_canonical_employee_identifier(): void
    {
        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00007', 'ho_ten' => 'Nhân viên',
            'email' => 'employee@example.test', 'mat_khau' => 'hash',
            'ma_vt' => 5, 'ma_pb' => 1, 'ma_tt' => 1,
        ]);

        self::assertSame('00007', (new CurrentEmployee())->id($actor));
    }

    /** @dataProvider invalidActors */
    public function test_it_fails_closed_for_non_employee_or_malformed_identity(?object $actor): void
    {
        try {
            (new CurrentEmployee())->id($actor);
            self::fail('Invalid actor must fail closed.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('Không xác định được nhân viên hiện tại.', $exception->getMessage());
        }
    }

    public static function invalidActors(): array
    {
        $bad = NhanVien::fromAuthRow((object) [
            'ma_nv' => 'ABC', 'ho_ten' => 'Sai', 'email' => 'bad@example.test',
            'mat_khau' => 'hash', 'ma_vt' => 5, 'ma_pb' => 1, 'ma_tt' => 1,
        ]);

        return [
            'null actor' => [null],
            'malformed employee identifier' => [$bad],
        ];
    }
}
```

- [ ] **Step 2: Chạy test và xác nhận RED do class chưa tồn tại**

Run: `herd php artisan test tests/Unit/Support/CurrentEmployeeTest.php`

Expected: FAIL vì `App\Support\CurrentEmployee` chưa tồn tại; không chấp nhận lỗi fixture/autoload khác.

- [ ] **Step 3: Tạo boundary tối thiểu**

```php
<?php

namespace App\Support;

use App\Models\NhanVien;

final class CurrentEmployee
{
    public function id(?object $actor): string
    {
        abort_unless(
            $actor instanceof NhanVien,
            403,
            'Không xác định được nhân viên hiện tại.',
        );

        $identifier = $actor->getAuthIdentifier();

        abort_unless(
            is_string($identifier) && preg_match('/\A[0-9]{5}\z/', $identifier) === 1,
            403,
            'Không xác định được nhân viên hiện tại.',
        );

        return $identifier;
    }
}
```

- [ ] **Step 4: Chạy GREEN và lint**

Run: `herd php artisan test tests/Unit/Support/CurrentEmployeeTest.php`

Run: `herd php -l app/Support/CurrentEmployee.php`

Expected: test pass; lint báo `No syntax errors detected`.

- [ ] **Step 5: Checkpoint không commit**

Run: `git diff --check -- app/Support/CurrentEmployee.php tests/Unit/Support/CurrentEmployeeTest.php`

Run: `git status --short -- app/Support/CurrentEmployee.php tests/Unit/Support/CurrentEmployeeTest.php`

### Task 2: Auth-only leave creation and own history

**Files:**
- Create: `app/Http/Requests/ListOwnNghiPhepRequest.php`
- Create: `app/Http/Requests/StoreOwnNghiPhepRequest.php`
- Create: `resources/js/frontend/nghiphep/own-leave-contract.js`
- Create: `tests/Feature/Backend/SelfServiceLeaveTest.php`
- Create: `tests/Frontend/nghiphep/own-leave-contract.test.js`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Backend/NghiPhepController.php`
- Modify: `resources/views/backend/nghiphep/create.blade.php`
- Modify: `resources/js/frontend/nghiphep/create.js`
- Modify: `tests/Feature/Backend/NghiPhepCreatePermissionContractTest.php`
- Modify: `tests/Frontend/nghiphep/create-contract.test.js`

**Interfaces:**
- Consumes: `CurrentEmployee::id()`, `NghiPhepService::getAll(array)`, `NghiPhepService::create(array)`.
- Produces: auth-only `GET /api/v1/nghi-phep/cua-toi`, auth-only `POST /api/v1/nghi-phep/cua-toi`, auth-only web `GET /tao-nghi-phep`; generic `/api/v1/nghi-phep` contracts không đổi.

- [ ] **Step 1: Viết feature tests RED cho middleware, owner scope và spoofing**

`tests/Feature/Backend/SelfServiceLeaveTest.php` phải dùng actor không có permission và mock service thật ở boundary controller:

```php
public function test_authenticated_account_without_leave_permissions_can_read_and_create_own_leave(): void
{
    $actor = $this->actingAsEmployeeWithPermissions([], [
        'ma_nv' => '00007', 'ma_vt' => 5, 'ma_pb' => 1,
    ]);

    $service = Mockery::mock(NghiPhepService::class);
    $service->shouldReceive('getAll')->once()->withArgs(
        fn (array $filters): bool => $filters['ma_nv'] === '00007'
            && ! array_key_exists('tu_khoa', $filters)
            && ! array_key_exists('ma_pb', $filters)
    )->andReturn(['success' => true, 'data' => [], 'counts' => ['pending' => 0, 'history' => 0]]);
    $service->shouldReceive('create')->once()->withArgs(
        fn (array $data): bool => $data['ma_nv'] === '00007'
            && $data['trang_thai_duyet'] === 0
            && $data['ma_lp'] === 1
    )->andReturn(['success' => true, 'message' => 'Tạo nghỉ phép thành công', 'data' => []]);
    $this->app->instance(NghiPhepService::class, $service);

    $this->getJson('/api/v1/nghi-phep/cua-toi')->assertOk();
    $this->postJson('/api/v1/nghi-phep/cua-toi', [
        'tu_ngay' => '2026-09-20', 'den_ngay' => '2026-09-20',
        'ma_lp' => 1, 'ly_do' => 'Việc gia đình',
    ])->assertCreated();
}

public function test_own_leave_rejects_client_identity_and_approval_status(): void
{
    $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);

    $this->getJson('/api/v1/nghi-phep/cua-toi?ma_nv=00002')
        ->assertUnprocessable()->assertJsonValidationErrors('ma_nv');

    $this->postJson('/api/v1/nghi-phep/cua-toi', [
        'ma_nv' => '00002', 'trang_thai_duyet' => 1,
        'tu_ngay' => '2026-09-20', 'den_ngay' => '2026-09-20',
        'ma_lp' => 1, 'ly_do' => 'Giả mạo',
    ])->assertUnprocessable()->assertJsonValidationErrors(['ma_nv', 'trang_thai_duyet']);
}
```

Thêm assertion route `backend.nghiphep.create`, `api.v1.nghi-phep.cua-toi`, `api.v1.nghi-phep.cua-toi.store` có `auth` nhưng không có `can:NghiPhep.*`; đồng thời `nghi-phep.store` vẫn có `can:NghiPhep.Insert` và trả `403` cho actor không quyền.

- [ ] **Step 2: Chạy feature test và xác nhận RED đúng route/middleware/action thiếu**

Run: `herd php artisan test tests/Feature/Backend/SelfServiceLeaveTest.php`

Expected: FAIL vì POST `cua-toi`, FormRequest và auth-only middleware chưa có.

- [ ] **Step 3: Tạo hai FormRequest allowlist**

`ListOwnNghiPhepRequest::rules()`:

```php
return [
    'ma_nv' => ['prohibited'],
    'tu_khoa' => ['prohibited'],
    'ma_pb' => ['prohibited'],
    'ma_cv' => ['prohibited'],
    'trang_thai_duyet' => ['nullable', 'integer', 'in:0,1,2'],
    'tu_ngay' => ['nullable', 'date_format:Y-m-d'],
    'den_ngay' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
    'tab' => ['nullable', 'in:pending,history'],
    'page' => ['nullable', 'integer', 'min:1'],
    'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50])],
];
```

`filters()` trả đúng bảy field nghiệp vụ với default `page=1`, `per_page=10`. `StoreOwnNghiPhepRequest::rules()` dùng `ma_nv` và `trang_thai_duyet` là `prohibited`, còn ngày/loại/lý do giữ contract hiện hành; dùng `NormalizesDisplayDates` giống `StoreNghiPhepRequest`.

- [ ] **Step 4: Chuyển controller/routes sang boundary server-side**

Constructor `NghiPhepController` nhận thêm `CurrentEmployee $currentEmployee`. Hai action:

```php
public function own(ListOwnNghiPhepRequest $request): JsonResponse
{
    $filters = $request->filters();
    $filters['ma_nv'] = $this->currentEmployee->id($request->user());
    $result = $this->service->getAll($filters);

    return response()->json($result, $result['success'] ? 200 : 500);
}

public function storeOwn(StoreOwnNghiPhepRequest $request): JsonResponse
{
    $data = $request->validated();
    $data['ma_nv'] = $this->currentEmployee->id($request->user());
    $data['trang_thai_duyet'] = 0;
    $result = $this->service->create($data);

    return response()->json($result, $result['success'] ? 201 : 400);
}
```

Khai báo GET rồi POST `nghi-phep/cua-toi` trước resource route. Bỏ `can:NghiPhep.Insert` khỏi web `/tao-nghi-phep`, GET/POST cá nhân và hai lookup hỗ trợ form `tao/loai-phep`, `tao/phong-ban`; mọi route generic vẫn giữ middleware quản trị hiện tại.

- [ ] **Step 5: Khóa lookup hỗ trợ form ở auth-only nhưng không mở lookup quản trị**

Thêm vào feature test: actor không permission gọi được `api.v1.nghi-phep.tao.loai-phep` và `api.v1.nghi-phep.tao.phong-ban`; các route `api.v1.nghi-phep.loai-phep` và `api.v1.nghi-phep.phong-ban` quản trị vẫn trả `403`. Không đổi response `auth/me` và không nới endpoint danh sách nhân viên.

- [ ] **Step 6: Viết Node test RED cho payload thuần và URL self POST**

```js
import assert from 'node:assert/strict';
import test from 'node:test';
import {
    OWN_LEAVE_API_URL,
    buildOwnLeavePayload,
} from '../../../resources/js/frontend/nghiphep/own-leave-contract.js';

test('own leave payload cannot carry employee or approval identity', () => {
    assert.equal(OWN_LEAVE_API_URL, '/api/v1/nghi-phep/cua-toi');
    assert.deepEqual(buildOwnLeavePayload({
        fromDate: '2026-09-20', toDate: '2026-09-21',
        leaveType: '1', reason: ' Việc gia đình ',
        ma_nv: '00002', trang_thai_duyet: 1,
    }), {
        tu_ngay: '2026-09-20', den_ngay: '2026-09-21',
        ma_lp: '1', ly_do: 'Việc gia đình',
    });
});
```

Run: `node --test tests/Frontend/nghiphep/own-leave-contract.test.js`

Expected: FAIL vì module chưa tồn tại.

- [ ] **Step 7: Tạo module payload và nối `create.js`**

```js
import { toIsoDate } from '../shared/date-field.js';

export const OWN_LEAVE_API_URL = '/api/v1/nghi-phep/cua-toi';

export function buildOwnLeavePayload({ fromDate, toDate, leaveType, reason }) {
    return {
        tu_ngay: toIsoDate(fromDate || '') || null,
        den_ngay: toIsoDate(toDate || '') || null,
        ma_lp: leaveType || null,
        ly_do: String(reason || '').trim(),
    };
}
```

`create.js` import module này; bỏ `CREATE_PERMISSIONS`, coi actor auth canonical là đủ tạo/xem log, giữ hai lookup auth-only hỗ trợ form, và dùng `OWN_LEAVE_API_URL` cho POST create. Update/Delete vẫn gọi generic endpoint và vẫn cần permission tương ứng.

- [ ] **Step 8: Sửa view nghỉ phép để không dẫn actor self-service sang trang quản trị**

Breadcrumb và nút Quay lại trỏ `backend.tongquan.index`; loading copy đổi thành “Đang kiểm tra tài khoản”; bỏ câu “chưa có quyền NghiPhep.Insert”. Giữ trạng thái submit/validation/empty hiện hành.

- [ ] **Step 9: Chạy GREEN và regression nghỉ phép**

Run: `herd php artisan test tests/Feature/Backend/SelfServiceLeaveTest.php tests/Feature/Backend/NghiPhepCreatePermissionContractTest.php tests/Feature/Backend/NghiPhepPaginationTest.php`

Run: `node --test tests/Frontend/nghiphep/own-leave-contract.test.js tests/Frontend/nghiphep/create-contract.test.js tests/Frontend/nghiphep/json-list-contract.test.js`

Expected: tất cả pass; generic route management vẫn deny khi thiếu permission.

- [ ] **Step 10: Checkpoint không commit**

Run: `git diff --check -- routes/web.php routes/api.php app/Http/Requests app/Http/Controllers/Backend/NghiPhepController.php resources/views/backend/nghiphep/create.blade.php resources/js/frontend/nghiphep tests/Feature/Backend/SelfServiceLeaveTest.php`

### Task 3: Own-contract server-rendered page

**Files:**
- Create: `resources/views/backend/selfservice/hopdong.blade.php`
- Create: `tests/Feature/Backend/SelfServiceContractTest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Backend/HopDongController.php`
- Modify: `app/Contracts/HopDongRepositoryContract.php`
- Modify: `app/Contracts/HopDongServiceContract.php`
- Modify: `app/Repositories/HopDongRepository.php`
- Modify: `app/Services/HopDongService.php`
- Modify: `tests/Unit/Repositories/HopDongRepositoryTest.php`

**Interfaces:**
- Consumes: `CurrentEmployee::id()`.
- Produces: `HopDongRepositoryContract::paginateForEmployee(string $maNv, int $perPage): LengthAwarePaginator`, service pass-through cùng signature, web `backend.selfservice.hopdong.index`.

- [ ] **Step 1: Viết repository/feature tests RED**

Repository fixture có hợp đồng của `00007` mã 4 và 9 cùng một hợp đồng của `00008`; assert kết quả chỉ `[9,4]`. Feature test actor không permission gọi `/hop-dong-cua-toi`, thấy hợp đồng chính chủ, không thấy mã người khác và không thấy `Thêm hợp đồng`, `Chỉnh sửa`, `Xóa`.

```php
$page = $repository->paginateForEmployee('00007', 20);
self::assertSame([9, 4], array_map(
    static fn (object $row): int => (int) $row->ma_hd,
    $page->items(),
));
```

Run: `herd php artisan test tests/Feature/Backend/SelfServiceContractTest.php tests/Unit/Repositories/HopDongRepositoryTest.php`

Expected: FAIL vì method/route/view chưa tồn tại.

- [ ] **Step 2: Thêm exact-owner contract ở repository/service**

```php
public function paginateForEmployee(string $maNv, int $perPage = 20): LengthAwarePaginator
{
    return $this->database->connection()->table('hop_dong as hd')
        ->join('loai_hop_dong as lhd', 'lhd.ma_lhd', '=', 'hd.ma_lhd')
        ->where('hd.ma_nv', '=', $maNv)
        ->select([
            'hd.ma_hd', 'hd.ma_nv', 'hd.ma_lhd', 'lhd.ten_lhd',
            'hd.ngay_ky', 'hd.ngay_het_han', 'hd.luong_co_ban',
        ])
        ->orderByDesc('hd.ma_hd')
        ->paginate($perPage)
        ->withQueryString();
}
```

Khai báo cùng signature ở hai interface; service chỉ forward repository, không dùng filter client.

- [ ] **Step 3: Thêm controller route/page auth-only**

`HopDongController` inject `CurrentEmployee`; action `own(Request $request): View` lấy actor ID, gọi service với `20`, render `backend.selfservice.hopdong`. Route:

```php
Route::get('/hop-dong-cua-toi', [HopDongController::class, 'own'])
    ->middleware('auth')
    ->name('selfservice.hopdong.index');
```

Route nằm trong group name `backend.`; `/hop-dong` management giữ nguyên `can:HopDong.Read`.

- [ ] **Step 4: Tạo Blade read-only**

View extends `backend.layouts.app`, dùng page header “Hợp đồng của tôi”, `@forelse`, các cột Loại hợp đồng/Ngày ký/Ngày hết hạn/Lương cơ bản, `<caption class="visually-hidden">`, `scope="col"`, empty state rõ ràng và shared pagination partial. Không render `ma_nv`, employee filter hoặc action controls.

```blade
@forelse ($contracts as $contract)
    <tr>
        <td>{{ $contract->ten_lhd }}</td>
        <td>{{ \Carbon\Carbon::parse($contract->ngay_ky)->format('d/m/Y') }}</td>
        <td>{{ $contract->ngay_het_han ? \Carbon\Carbon::parse($contract->ngay_het_han)->format('d/m/Y') : 'Không thời hạn' }}</td>
        <td class="text-end">{{ number_format((float) $contract->luong_co_ban, 0, ',', '.') }} đ</td>
    </tr>
@empty
    <tr><td colspan="4" class="text-center text-secondary py-5">Bạn chưa có hợp đồng.</td></tr>
@endforelse
```

- [ ] **Step 5: Chạy GREEN và management regression**

Run: `herd php artisan test tests/Feature/Backend/SelfServiceContractTest.php tests/Unit/Repositories/HopDongRepositoryTest.php tests/Feature/Backend/HopDongModalFeatureTest.php tests/Feature/Backend/ContentFourManagementTest.php`

Expected: self-service pass không permission; `/hop-dong` vẫn `403` khi thiếu `HopDong.Read`.

- [ ] **Step 6: Checkpoint không commit**

Run: `git diff --check -- app/Contracts/HopDong* app/Repositories/HopDongRepository.php app/Services/HopDongService.php app/Http/Controllers/Backend/HopDongController.php resources/views/backend/selfservice/hopdong.blade.php tests/Feature/Backend/SelfServiceContractTest.php`

### Task 4: Own-salary API and server-rendered page

**Files:**
- Create: `app/Http/Requests/ListOwnLuongRequest.php`
- Create: `resources/views/backend/selfservice/luong.blade.php`
- Create: `tests/Feature/Backend/SelfServiceSalaryTest.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Backend/LuongController.php`
- Modify: `app/Repositories/LuongRepository.php`
- Modify: `app/Services/LuongService.php`

**Interfaces:**
- Consumes: `CurrentEmployee::id()` and four canonical salary SQL functions.
- Produces: `LuongRepository::paginateForEmployee(string $maNv, array $filters): LengthAwarePaginator`, `LuongService::getForEmployee(string $maNv, array $filters): array`, web `backend.selfservice.luong.index`, API `api.v1.luong.cua-toi`.

- [ ] **Step 1: Viết RED tests cho exact owner, response shape và safe error**

Trong SQLite test, tạo `luong` và đăng ký các function cần cho dedicated query bằng PDO `sqliteCreateFunction`; chèn lương của hai nhân viên. Assert repository chỉ trả actor và kỳ chính xác. Feature API mock service và assert `ma_nv`, `ma_pb`, `ma_cv`, `tu_khoa` client đều bị `422`; request hợp lệ truyền ID actor vào service. Exception repository phải trở thành `Không thể tải lương của bạn.` và không chứa `SQLSTATE`.

```php
DB::getPdo()->sqliteCreateFunction('fn_so_ngay_cong_chuan', static fn (): int => 20, 2);
DB::getPdo()->sqliteCreateFunction('fn_so_ngay_cong_thuc_te', static fn (): float => 19.5, 2);
DB::getPdo()->sqliteCreateFunction('fn_tinh_luong_thuc_nhan', static fn (): int => 12000000, 2);
DB::getPdo()->sqliteCreateFunction('fn_thong_bao_tinh_luong', static fn (): string => 'Hoàn tất tính lương', 2);
```

Run: `herd php artisan test tests/Feature/Backend/SelfServiceSalaryTest.php`

Expected: FAIL vì request/method/routes/view chưa có.

- [ ] **Step 2: Tạo `ListOwnLuongRequest`**

Rules chỉ nhận `ky_luong` dạng `Y-m`, `page`, `per_page in:10,20,50`; cấm `ma_nv`, `tu_khoa`, `ma_pb`, `ma_cv`. `filters()` chuyển kỳ `2026-09` thành `2026-09-01` và default page/per-page.

- [ ] **Step 3: Thêm dedicated repository/service query tối thiểu**

```php
public function paginateForEmployee(string $maNv, array $filters): LengthAwarePaginator
{
    $page = max((int) ($filters['page'] ?? 1), 1);
    $perPage = in_array((int) ($filters['per_page'] ?? 10), [10, 20, 50], true)
        ? (int) $filters['per_page'] : 10;
    $period = $this->nullIfEmpty($filters['ky_luong'] ?? null);

    return DB::table('luong as l')
        ->where('l.ma_nv', '=', $maNv)
        ->when($period !== null, fn ($query) => $query->where('l.ky_luong', '=', $period))
        ->select(['l.ma_luong', 'l.ma_nv', 'l.ky_luong', 'l.thuong', 'l.phat', 'l.bao_hiem', 'l.thue'])
        ->selectRaw('fn_so_ngay_cong_chuan(l.ma_nv, l.ky_luong) AS so_ngay_cong_chuan')
        ->selectRaw('fn_so_ngay_cong_thuc_te(l.ma_nv, l.ky_luong) AS so_ngay_cong_thuc_te')
        ->selectRaw('fn_tinh_luong_thuc_nhan(l.ma_nv, l.ky_luong) AS thuc_nhan')
        ->selectRaw('fn_thong_bao_tinh_luong(l.ma_nv, l.ky_luong) AS thong_bao_tinh_luong')
        ->orderByDesc('l.ky_luong')
        ->orderByDesc('l.ma_luong')
        ->paginate($perPage, ['*'], 'page', $page)
        ->withQueryString();
}
```

Service bắt `Throwable`, `report($exception)`, trả `{success:false,message:'Không thể tải lương của bạn.'}`; success trả paginator chưa serialize để web/API dùng chung.

- [ ] **Step 4: Thêm page và API controller actions**

`ownPage(ListOwnLuongRequest $request): View` render paginator hoặc error banner. `own(ListOwnLuongRequest $request): JsonResponse` serialize paginator bằng `JsonPaginator::from()`; cả hai luôn gọi `CurrentEmployee::id($request->user())`.

Khai báo `GET /luong-cua-toi` auth-only và `GET /api/v1/luong/cua-toi` trước mọi route `luong/{id}`. Management `/luong`, resource, lookup và export giữ `Luong.*`.

- [ ] **Step 5: Tạo Blade lương read-only**

Trang có GET form kỳ lương (`type="month"`) và `per_page`, nút Áp dụng/Đặt lại, error/empty state, bảng có caption cùng các cột Kỳ lương, Ngày công chuẩn, Ngày công thực tế, Thưởng, Phạt, Bảo hiểm, Thuế, Thực nhận, Trạng thái. Không có nhân viên/phòng ban/chức vụ, tạo/sửa/xóa, hệ số hay export.

- [ ] **Step 6: Chạy GREEN và salary regression**

Run: `herd php artisan test tests/Feature/Backend/SelfServiceSalaryTest.php tests/Feature/Backend/LuongJsonListContractTest.php tests/Feature/Backend/FeedbackV6PrivacyTest.php`

Expected: self API/page pass; generic salary routes và privacy regression vẫn pass.

- [ ] **Step 7: Checkpoint không commit**

Run: `git diff --check -- app/Http/Requests/ListOwnLuongRequest.php app/Http/Controllers/Backend/LuongController.php app/Repositories/LuongRepository.php app/Services/LuongService.php resources/views/backend/selfservice/luong.blade.php tests/Feature/Backend/SelfServiceSalaryTest.php`

### Task 5: Own-attendance API and server-rendered page

**Files:**
- Create: `app/Http/Requests/ListOwnChamCongRequest.php`
- Create: `resources/views/backend/selfservice/chamcong.blade.php`
- Create: `tests/Feature/Backend/SelfServiceAttendanceTest.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Backend/ChamCongController.php`
- Modify: `app/Services/ChamCongService.php`

**Interfaces:**
- Consumes: `CurrentEmployee::id()`.
- Produces: `ChamCongService::forEmployee(string $maNv, array $filters): array{paginator: LengthAwarePaginator, summary: array}`, web `backend.selfservice.chamcong.index`, API `api.v1.cham-cong.cua-toi`.

- [ ] **Step 1: Viết RED feature test bằng SQLite canonical**

Tạo `nhan_vien` và `cham_cong` đúng schema active (`smallInteger so_gio_lam`, không timestamps), chèn dữ liệu cùng tháng cho `00007` và `00008`. Actor không quyền gọi API/page và chỉ nhận hai dòng/chỉ số của `00007`; `?ma_nv=00008` trả `422`. View không chứa `employee-search`, `department-filter`, `import`, `export`, `data-delete`, `data-save`.

```php
$this->getJson('/api/v1/cham-cong/cua-toi?thang=9&nam=2026')
    ->assertOk()
    ->assertJsonCount(2, 'data.data')
    ->assertJsonPath('data.data.0.ma_nv', '00007')
    ->assertJsonPath('summary.tong_gio_lam', 12.0);
```

Run: `herd php artisan test tests/Feature/Backend/SelfServiceAttendanceTest.php`

Expected: FAIL vì route/request/service action chưa có.

- [ ] **Step 2: Tạo `ListOwnChamCongRequest`**

Rules cho `thang 1..12`, `nam 2000..2100`, `page >=1`, `per_page in:10,20,50`; cấm `ma_nv`, `tu_khoa`, `ma_pb`. `filters()` default theo `now()` và trả integer canonical.

- [ ] **Step 3: Chuyển query detail vào service dùng chung**

`ChamCongService::forEmployee()` dựng một base query exact `ma_nv` + khoảng đầu/cuối tháng, clone cho paginator và summary, giữ thứ tự `ngay_lam ASC` và shape summary hiện có. Dùng khoảng ngày Carbon thay cho identity/filter client:

```php
$base = DB::table('cham_cong')
    ->where('ma_nv', '=', $maNv)
    ->whereDate('ngay_lam', '>=', $fromDate)
    ->whereDate('ngay_lam', '<', $toDate);
```

Refactor `ChamCongController::index()` gọi method này để management detail không đổi response; thêm `own()` và `ownPage()` dùng boundary actor. Catch path phải `report()` và trả message `Không thể tải dữ liệu chấm công của bạn.`.

- [ ] **Step 4: Thêm routes và Blade read-only**

Khai báo `/cham-cong-cua-toi` và `/api/v1/cham-cong/cua-toi` auth-only, route API đứng trước resource. Blade có GET form tháng/năm/per-page, bốn summary cards bằng text, bảng Ngày/Giờ làm/Vào muộn/Về sớm, error/empty state và pagination; không có employee/department lookup hoặc mutation/import/export.

- [ ] **Step 5: Chạy GREEN và attendance regression**

Run: `herd php artisan test tests/Feature/Backend/SelfServiceAttendanceTest.php tests/Feature/Compatibility/ChamCongEmployeeLookupSecurityTest.php tests/Feature/Backend/ChamCongDeleteFeatureTest.php`

Expected: self route exact-owner pass; management employee lookup/delete contract không đổi.

- [ ] **Step 6: Checkpoint không commit**

Run: `git diff --check -- app/Http/Requests/ListOwnChamCongRequest.php app/Http/Controllers/Backend/ChamCongController.php app/Services/ChamCongService.php resources/views/backend/selfservice/chamcong.blade.php tests/Feature/Backend/SelfServiceAttendanceTest.php`

### Task 6: Always-visible self-service navigation and route matrix

**Files:**
- Create: `tests/Feature/Backend/SelfServiceAccessTest.php`
- Modify: `resources/views/backend/layouts/sidebar.blade.php`
- Modify: `tests/Feature/Backend/ContentFourManagementTest.php`
- Modify: `tests/Frontend/shared/sidebar-state.test.js` only if the existing accordion fixture needs the new group.

**Interfaces:**
- Consumes: route names from Tasks 2–5 and `backend.profile.edit`.
- Produces: authenticated sidebar group `data-sidebar-group="selfservice"`; management groups remain governed by `PermissionService`.

- [ ] **Step 1: Viết RED route matrix cho năm role không module permission**

Data provider chạy role `1..5`, actor canonical không cấp permission, assert năm web route self-service không trả `403`; guest bị redirect login. Vì salary/contract/attendance cần data dependency, bind mock service trả paginator rỗng nhưng không mock authorization.

```php
public static function canonicalRoles(): array
{
    return [[1], [2], [3], [4], [5]];
}

/** @dataProvider canonicalRoles */
public function test_every_role_can_open_all_self_service_pages_without_module_permissions(int $role): void
{
    $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => $role]);

    foreach (['/ho-so-ca-nhan', '/tao-nghi-phep', '/hop-dong-cua-toi', '/luong-cua-toi', '/cham-cong-cua-toi'] as $url) {
        $this->get($url)->assertSuccessful();
    }
}
```

Thêm test actor không quyền nhận `403` tại `/hop-dong`, `/luong`, `/cham-cong`, `/nghi-phep` để chứng minh không nới management.

Run: `herd php artisan test tests/Feature/Backend/SelfServiceAccessTest.php`

Expected: FAIL vì sidebar/route matrix chưa hoàn chỉnh.

- [ ] **Step 2: Tạo nhóm sidebar self-service độc lập RBAC**

Ngay sau Tổng quan, nếu `$sidebarUser instanceof NhanVien`, render accordion “Thông tin của tôi” với năm link:

```blade
<a href="{{ route('backend.profile.edit') }}">Tài khoản cá nhân</a>
<a href="{{ route('backend.nghiphep.create') }}">Đơn nghỉ phép của tôi</a>
<a href="{{ route('backend.selfservice.hopdong.index') }}">Hợp đồng của tôi</a>
<a href="{{ route('backend.selfservice.luong.index') }}">Lương của tôi</a>
<a href="{{ route('backend.selfservice.chamcong.index') }}">Chấm công của tôi</a>
```

Active group gồm `backend.profile.*`, `backend.nghiphep.create`, `backend.selfservice.*`. Nhóm Quản lý nghỉ phép chỉ render danh sách quản trị khi có `NghiPhep.Read`; không lặp link create. Các group Hợp đồng/Lương/Chấm công quản trị giữ nguyên PermissionService.

- [ ] **Step 3: Kiểm thử sidebar với permission service deny-all và allow-management**

Actor deny-all phải thấy đủ năm nhãn “của tôi” nhưng không thấy “Danh sách hợp đồng”, “Danh sách lương”, “Danh sách chấm công”, “Danh sách nghỉ phép”. Actor có quyền đọc tương ứng thấy cả self-service và management, với route khác nhau.

- [ ] **Step 4: Chạy GREEN và frontend sidebar regression**

Run: `herd php artisan test tests/Feature/Backend/SelfServiceAccessTest.php tests/Feature/Backend/ContentFourManagementTest.php`

Run: `node --test tests/Frontend/shared/sidebar-state.test.js`

Expected: route matrix, label và active state pass.

- [ ] **Step 5: Checkpoint không commit**

Run: `git diff --check -- resources/views/backend/layouts/sidebar.blade.php tests/Feature/Backend/SelfServiceAccessTest.php tests/Feature/Backend/ContentFourManagementTest.php`

### Task 7: Fresh RBAC cleanup, guarded existing-DB remediation, and canonical snapshot

**Files:**
- Create: `database/sql/rbac/2026_09_17_001_remove_employee_management_permissions.sql`
- Modify: `database/sql/du_lieu_mau.sql`
- Modify: `database/sql/rbac/2026_09_16_001_add_employee_self_service_permissions.sql`
- Modify: `quan_ly_nhan_vien_session_update.sql`
- Modify: `tests/Feature/Backend/FeedbackV6RbacContractTest.php`
- Modify: `tests/Unit/Database/PortableSqlContractTest.php`
- Modify: `tests/Integration/MariaDb/FreshEmployeeSchemaContractTest.php`
- Modify: `docs/DATABASE.md`

**Interfaces:**
- Consumes: permission metadata IDs `25`, `26`, `33`, role ID `5`.
- Produces: fresh role 5 có zero management assignment; existing DB cleanup chỉ chạy khi session approval variable bằng `1`.

- [ ] **Step 1: Viết RED SQL contract tests**

Trong `PortableSqlContractTest`, parse grant section và assert không có pair role 5. Trong fresh MariaDB test, ngay sau `runFreshPair()` assert count role 5 bằng `0`. Trong RBAC contract test, assert script 2026-09-16 có marker/error `RBAC_EMPLOYEE_SELF_SERVICE_SCRIPT_SUPERSEDED`, cleanup script có approval guard và chỉ target `ma_quyen IN (25, 26, 33)`.

Run: `herd php artisan test tests/Unit/Database/PortableSqlContractTest.php tests/Feature/Backend/FeedbackV6RbacContractTest.php`

Expected: FAIL vì seed còn ba mapping và script cũ còn grant.

- [ ] **Step 2: Bỏ role-5 grants khỏi fresh seed**

Xóa đúng block `(5, 25), (5, 26), (5, 33)` khỏi `du_lieu_mau.sql`; không xóa ba quyền khỏi catalog và không đổi grants của role 1–4.

- [ ] **Step 3: Làm script additive cũ fail-closed là superseded**

Giữ filename để lịch sử không mất, nhưng procedure chỉ `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_SELF_SERVICE_SCRIPT_SUPERSEDED';`; không còn `INSERT`, `UPDATE` hoặc `DELETE`.

- [ ] **Step 4: Tạo cleanup script approval-gated exact target**

Procedure mới phải:

```sql
IF COALESCE(@approved_employee_self_service_cleanup, 0) <> 1 THEN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_SELF_SERVICE_CLEANUP_APPROVAL_REQUIRED';
END IF;

IF (SELECT COUNT(*) FROM vai_tro WHERE ma_vt = 5) <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_ROLE_MISSING';
END IF;

IF (SELECT COUNT(*) FROM quyen
    WHERE (ma_quyen = 25 AND BINARY ky_hieu_quyen = BINARY N'NghiPhep.Read')
       OR (ma_quyen = 26 AND BINARY ky_hieu_quyen = BINARY N'NghiPhep.Insert')
       OR (ma_quyen = 33 AND BINARY ky_hieu_quyen = BINARY N'Luong.Read')) <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_PERMISSION_METADATA_COLLISION';
END IF;

DELETE FROM vai_tro_quyen
WHERE ma_vt = 5 AND ma_quyen IN (25, 26, 33);
```

Bao quanh transaction/exit handler; post-check exact target bằng zero; reset approval variable sau call. Comment đầu file yêu cầu preflight target + backup + approval và ghi rõ Codex không tự chạy.

- [ ] **Step 5: Đồng bộ snapshot bằng exact source**

Trong marker source 2 của `quan_ly_nhan_vien_session_update.sql`, xóa cùng role-5 block để `PortableSqlContractTest::test_combined_snapshot_is_generated_from_all_current_sources` pass; không sửa các source marker khác.

- [ ] **Step 6: Cập nhật database documentation**

`docs/DATABASE.md` bỏ script 2026-09-16 khỏi danh sách rollout hợp lệ, ghi nó superseded; mô tả script 2026-09-17 chỉ dùng sau preflight/backup/approval và câu lệnh session `SET @approved_employee_self_service_cleanup = 1;`. Không claim script đã chạy.

- [ ] **Step 7: Chạy GREEN portable và MariaDB disposable nếu guard sẵn sàng**

Run: `herd php artisan test tests/Unit/Database/PortableSqlContractTest.php tests/Feature/Backend/FeedbackV6RbacContractTest.php`

Run only on guarded disposable target: `pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb`

Expected: portable tests pass; MariaDB fresh asserts role 5 count `0`. Nếu disposable wrapper không hoàn tất, dừng nó an toàn và báo `unverified`; tuyệt đối không đổi sang live DB.

- [ ] **Step 8: Checkpoint không commit**

Run: `git diff --check -- database/sql/du_lieu_mau.sql database/sql/rbac/2026_09_16_001_add_employee_self_service_permissions.sql database/sql/rbac/2026_09_17_001_remove_employee_management_permissions.sql quan_ly_nhan_vien_session_update.sql docs/DATABASE.md`

### Task 8: Full regression, browser acceptance, and handoff

**Files:**
- Modify: `docs/PROJECT_STATUS.md`
- Modify: `docs/CODEX_NEXT_HANDOFF.md`
- Review: every file listed above.

**Interfaces:**
- Consumes: all completed self-service routes and tests.
- Produces: fresh verification evidence with verified/partial/unverified boundaries.

- [ ] **Step 1: Chạy targeted self-service suite**

Run:

```powershell
herd php artisan test `
  tests/Unit/Support/CurrentEmployeeTest.php `
  tests/Feature/Backend/SelfServiceAccessTest.php `
  tests/Feature/Backend/SelfServiceLeaveTest.php `
  tests/Feature/Backend/SelfServiceContractTest.php `
  tests/Feature/Backend/SelfServiceSalaryTest.php `
  tests/Feature/Backend/SelfServiceAttendanceTest.php `
  tests/Unit/Repositories/HopDongRepositoryTest.php `
  tests/Unit/Database/PortableSqlContractTest.php `
  tests/Feature/Backend/FeedbackV6RbacContractTest.php
```

Expected: tất cả pass, không warning/error ngoài output chuẩn PHPUnit.

- [ ] **Step 2: Chạy toàn bộ Laravel và frontend**

Run: `herd php artisan test`

Run: `npm run test:frontend`

Run: `npm run build`

Expected: exit `0`; ghi lại số test/assertion/module fresh, không dùng số từ handoff cũ.

- [ ] **Step 3: Kiểm tra route inventory và duplicate**

```powershell
$routeJson = herd php artisan route:list --json
$routes = $routeJson | ConvertFrom-Json
$duplicateNames = $routes | Where-Object Name | Group-Object Name | Where-Object Count -gt 1
$duplicateSignatures = $routes | Group-Object { "{0} {1}" -f $_.method, $_.uri } | Where-Object Count -gt 1
[pscustomobject]@{
    RouteCount = $routes.Count
    DuplicateNames = $duplicateNames.Count
    DuplicateSignatures = $duplicateSignatures.Count
}
```

Expected: các route self-service có đúng name/middleware, duplicate names/signatures bằng `0`.

- [ ] **Step 4: Lint, Composer, security audit và diff hygiene**

Run PHP lint cho mọi PHP mới/sửa bằng danh sách `git diff --name-only --diff-filter=ACM` cộng file untracked đúng scope; dùng `herd php -l` từng file.

Run: `herd composer validate --no-check-publish`

Run: `npm audit --audit-level=high`

Run: `git diff --check`

Expected: lint/Composer/diff pass; npm audit không có high/critical reachable. Nếu audit có issue, ghi package/severity/reachability, không tự nâng dependency ngoài scope.

- [ ] **Step 5: Browser acceptance read-only bằng account không quyền quản trị**

Đăng nhập local bằng employee sample `00007` và mật khẩu sample hiện hành, không tạo đơn và không mutation business data. Kiểm tra desktop và mobile đại diện:

1. Sidebar luôn có nhóm “Thông tin của tôi” và đủ năm link.
2. `/ho-so-ca-nhan`, `/tao-nghi-phep`, `/hop-dong-cua-toi`, `/luong-cua-toi`, `/cham-cong-cua-toi` mở được.
3. Hợp đồng/lương/chấm công chỉ hiển thị mã/dữ liệu `00007`; chấm công không phát request tới `/nhan-vien` hoặc `/phong-ban`.
4. Không có import/export/create/edit/delete trên ba page read-only.
5. Form nghỉ phép không hiển thị lỗi thiếu `NghiPhep.Insert`; không bấm Gửi.
6. Console error/warning bằng `[]`, không document overflow; keyboard focus đi qua filter/link/table controls hợp lý.
7. Mở `/hop-dong`, `/luong`, `/cham-cong`, `/nghi-phep` và xác nhận `403` cho management route thiếu quyền.

Nếu local server/account/browser không khả dụng, ghi `browser blocked` với lỗi cụ thể; automated evidence không được đổi tên thành browser verification.

- [ ] **Step 6: Cập nhật trạng thái/handoff bằng evidence fresh**

Thêm section đầu `PROJECT_STATUS.md` và `CODEX_NEXT_HANDOFF.md` gồm:

- route/self-service contract đã triển khai;
- RED và GREEN targeted;
- full Laravel/frontend/build/routes/lint/Composer/diff/audit numbers;
- browser evidence cụ thể hoặc blocker;
- MariaDB disposable/live DB phân biệt rõ;
- cleanup SQL được tạo nhưng chưa chạy live;
- HEAD vẫn `f84d46762248ab7e6b286b3ff6a74907abcf5fc4` nếu không đổi và worktree vẫn dirty/untracked do task.

- [ ] **Step 7: Final self-review theo spec**

Đối chiếu từng mục 1–10 của design spec. Chạy:

```powershell
rg -n "T[B]D|T[O]DO|F[I]XME|PLACEH[O]LDER" docs/superpowers/plans/2026-09-17-default-account-self-service.md docs/superpowers/specs/2026-09-17-default-account-self-service-design.md
git status --short --branch
git diff --stat
git diff --check
```

Expected: không dấu giữ chỗ; không file ngoài scope bị revert; không staged commit; mọi giới hạn chưa verify được ghi rõ.
