<?php

namespace Tests\Feature\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Enums\LuongPermission;
use App\Enums\NghiPhepPermission;
use App\Services\LuongService;
use App\Services\NghiPhepService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class FeedbackV6PrivacyTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });

        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00001'],
            ['ma_nv' => '00002'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');

        parent::tearDown();
    }

    public function test_employee_salary_list_is_exactly_owned(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [LuongPermission::Xem],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );

        $service = Mockery::mock(LuongService::class);
        $service->shouldReceive('getAll')->once()->withArgs(static function (array $filters): bool {
            return ($filters['ma_nv'] ?? null) === '00001'
                && ($filters['tu_khoa'] ?? null) === null
                && ($filters['ma_pb'] ?? null) === null
                && ($filters['ma_cv'] ?? null) === null;
        })->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(LuongService::class, $service);

        $this->getJson('/api/v1/luong?ma_nv=00002&tu_khoa=người%20khác&ma_pb=2')
            ->assertOk()
            ->assertJsonPath('success', true);

    }

    public function test_admin_salary_filters_remain_unchanged(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [LuongPermission::Xem],
            ['ma_vt' => 1, 'ma_nv' => '00001'],
        );
        $service = Mockery::mock(LuongService::class);
        $filtersSeen = null;
        $service->shouldReceive('getAll')->withAnyArgs()->once()->andReturnUsing(function (array $filters) use (&$filtersSeen): array {
            $filtersSeen = $filters;

            return ['success' => true, 'data' => []];
        });
        $this->app->instance(LuongService::class, $service);

        $this->getJson('/api/v1/luong?ma_nv=00002&ma_pb=2')->assertOk();
        self::assertSame('00002', $filtersSeen['ma_nv']);
    }

    public function test_employee_salary_detail_and_export_cannot_cross_owner_boundary(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [LuongPermission::Xem],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );

        $service = Mockery::mock(LuongService::class);
        $service->shouldReceive('getById')->once()->with(7, '00001')
            ->andReturn(['success' => false, 'message' => 'Không tìm thấy bản ghi']);
        $service->shouldReceive('exportByKyLuong')->once()->withArgs(static function (string $period, array $filters): bool {
            return $period === '2026-09'
                && ($filters['ma_nv'] ?? null) === '00001'
                && ($filters['tu_khoa'] ?? null) === null
                && ($filters['ma_pb'] ?? null) === null
                && ($filters['ma_cv'] ?? null) === null;
        })->andReturn(['success' => false, 'message' => 'Không có dữ liệu lương phù hợp với bộ lọc.']);
        $this->app->instance(LuongService::class, $service);

        $this->getJson('/api/v1/luong/7')->assertNotFound();
        $this->getJson('/api/v1/luong/export?ky_luong=2026-09&ma_nv=00002&tu_khoa=khác')
            ->assertBadRequest()
            ->assertJsonPath('success', false);
    }

    public function test_employee_salary_mutation_defends_against_crafted_owner(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [LuongPermission::Tao],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );

        $service = Mockery::mock(LuongService::class);
        $service->shouldReceive('create')->never();
        $this->app->instance(LuongService::class, $service);

        $this->postJson('/api/v1/luong', [
            'ma_nv' => '00002',
            'ky_luong' => '2026-09-01',
        ])->assertForbidden();
    }

    public function test_employee_salary_update_and_delete_are_owner_scoped(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [LuongPermission::Sua],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );

        $service = Mockery::mock(LuongService::class);
        $service->shouldReceive('update')->once()->withArgs(static function ($id, array $data, ?string $owner): bool {
            return (int) $id === 7
                && ($data['ma_nv'] ?? null) === '00001'
                && $owner === '00001';
        })->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(LuongService::class, $service);

        $this->putJson('/api/v1/luong/7', [
            'ma_nv' => '00002',
            'ky_luong' => '2026-09-01',
        ])->assertOk();

        $this->actingAsEmployeeWithPermissions(
            [LuongPermission::Xoa],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );
        $service = Mockery::mock(LuongService::class);
        $service->shouldReceive('delete')->once()->with(7, '00001')
            ->andReturn(['success' => false, 'message' => 'Không tìm thấy bản ghi']);
        $this->app->instance(LuongService::class, $service);

        $this->deleteJson('/api/v1/luong/7')->assertNotFound();
    }

    public function test_employee_leave_list_and_lookup_are_exactly_owned(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [NghiPhepPermission::Xem],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );

        $leaveService = Mockery::mock(NghiPhepService::class);
        $leaveService->shouldReceive('getAll')->once()->withArgs(static function (array $filters): bool {
            return ($filters['ma_nv'] ?? null) === '00001'
                && ($filters['tu_khoa'] ?? null) === null
                && ($filters['ma_pb'] ?? null) === null
                && ($filters['ma_cv'] ?? null) === null;
        })->andReturn(['success' => true, 'data' => [], 'counts' => ['pending' => 0, 'history' => 0]]);
        $this->app->instance(NghiPhepService::class, $leaveService);

        $this->getJson('/api/v1/nghi-phep?ma_nv=00002&tu_khoa=khác&ma_pb=3&ma_cv=2')
            ->assertOk();

        $employeeService = Mockery::mock(NhanVienServiceContract::class);
        $employeeService->shouldReceive('paginate')->once()->withArgs(static function (array $filters): bool {
            return ($filters['ma_nv'] ?? null) === '00001'
                && ($filters['tu_khoa'] ?? null) === null
                && ($filters['ma_pb'] ?? null) === null
                && ($filters['ma_cv'] ?? null) === null;
        })->andReturn(new LengthAwarePaginator([], 1, 15));
        $this->app->instance(NhanVienServiceContract::class, $employeeService);

        $this->getJson('/api/v1/nghi-phep/nhan-vien?tu_khoa=00002&ma_pb=3')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_employee_leave_detail_create_update_and_delete_are_owner_scoped(): void
    {
        $this->actingAsEmployeeWithPermissions(
            [NghiPhepPermission::Xem],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getById')->once()->with(8, '00001')
            ->andReturn(['success' => false, 'message' => 'Không tìm thấy bản ghi']);
        $this->app->instance(NghiPhepService::class, $service);
        $this->getJson('/api/v1/nghi-phep/8')->assertNotFound();

        $this->actingAsEmployeeWithPermissions(
            [NghiPhepPermission::Tao],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );
        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('create')->once()->withArgs(static function (array $data): bool {
            return ($data['ma_nv'] ?? null) === '00001'
                && ($data['trang_thai_duyet'] ?? null) === 0;
        })->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(NghiPhepService::class, $service);

        $this->postJson('/api/v1/nghi-phep', [
            'ma_nv' => '00002',
            'tu_ngay' => '2026-09-10',
            'den_ngay' => '2026-09-10',
            'ma_lp' => 1,
            'ly_do' => 'Xin nghỉ',
            'trang_thai_duyet' => 1,
        ])->assertCreated();

        $this->actingAsEmployeeWithPermissions(
            [NghiPhepPermission::Sua],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );
        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('update')->once()->withArgs(static function ($id, array $data, ?string $owner): bool {
            return (int) $id === 8
                && ($data['ma_nv'] ?? null) === '00001'
                && $owner === '00001';
        })->andReturn(['success' => true, 'data' => []]);
        $this->app->instance(NghiPhepService::class, $service);

        $this->putJson('/api/v1/nghi-phep/8', [
            'ma_nv' => '00002',
            'tu_ngay' => '2026-09-10',
            'den_ngay' => '2026-09-10',
            'ma_lp' => 1,
            'ly_do' => 'Sửa lý do',
        ])->assertOk();

        $this->actingAsEmployeeWithPermissions(
            [NghiPhepPermission::Xoa],
            ['ma_vt' => 5, 'ma_nv' => '00001'],
        );
        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('delete')->once()->with(8, '00001')
            ->andReturn(['success' => false, 'message' => 'Không tìm thấy bản ghi']);
        $this->app->instance(NghiPhepService::class, $service);

        $this->deleteJson('/api/v1/nghi-phep/8')->assertNotFound();
    }
}
