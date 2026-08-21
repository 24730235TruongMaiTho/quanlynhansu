<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienPermission;
use App\Enums\NhanVienRemovalAction;
use App\Http\Controllers\Backend\ChamCongController;
use App\Http\Controllers\Backend\NghiPhepController;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\CreatesEmployeeFeatureSchema;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienAuthorizationTest extends TestCase
{
    use CreatesEmployeeFeatureSchema;
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmployeeFeatureSchema();
        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }

    protected function tearDown(): void
    {
        $this->dropEmployeeFeatureSchema();
        parent::tearDown();
    }

    public function test_guest_is_redirected_for_every_employee_browser_entry_point(): void
    {
        foreach ([
            ['GET', '/admin/nhan-vien'],
            ['GET', '/admin/nhan-vien/NV001'],
            ['GET', '/admin/nhan-vien/create'],
            ['GET', '/admin/nhan-vien/NV001/edit'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect(route('login'));
        }
    }

    public function test_lookup_api_denies_authenticated_actor_without_xem(): void
    {
        $this->actingAsEmployeeWithPermissions([]);

        $this->getJson('/api/v1/nghi-phep/nhan-vien')->assertForbidden();
        $this->getJson('/api/v1/cham-cong/nhan-vien')->assertForbidden();
    }

    public function test_lookup_rollout_precedes_xem_gate_and_never_dispatches_when_disabled(): void
    {
        $this->actingAsEmployeeWithPermissions([]);
        config()->set('nhanvien.enabled', false);

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginateForAttendance');
            $mock->shouldNotReceive('paginate');
        });
        $this->mock(ChamCongController::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getMiddleware')->zeroOrMoreTimes()->andReturn([]);
            $mock->shouldNotReceive('employees');
        });
        $this->mock(NghiPhepController::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getMiddleware')->zeroOrMoreTimes()->andReturn([]);
            $mock->shouldNotReceive('employees');
        });

        $this->getJson('/api/v1/nghi-phep/nhan-vien')->assertNotFound();
        $this->getJson('/api/v1/cham-cong/nhan-vien')->assertNotFound();
    }

    public function test_sidebar_requires_rollout_and_xem_but_keeps_attendance_visible(): void
    {
        $this->actingAsEmployeeWithPermissions([]);

        $withoutXem = view('backend.layouts.sidebar')->render();

        $this->assertStringNotContainsString('/admin/nhan-vien', $withoutXem);
        $this->assertStringNotContainsString('Danh sách nhân viên', $withoutXem);
        $this->assertStringContainsString(route('backend.backend.chamcong.index'), $withoutXem);

        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xem]);
        $withXem = view('backend.layouts.sidebar')->render();

        $this->assertStringContainsString(route('backend.nhanvien.index'), $withXem);
        $this->assertStringContainsString('Danh sách nhân viên', $withXem);
        $this->assertStringContainsString(route('backend.backend.chamcong.index'), $withXem);

        config()->set('nhanvien.enabled', false);
        $disabled = view('backend.layouts.sidebar')->render();

        $this->assertStringNotContainsString(route('backend.nhanvien.index'), $disabled);
        $this->assertStringContainsString(route('backend.backend.chamcong.index'), $disabled);
    }

    public function test_xem_only_actor_can_read_and_cannot_open_any_mutation(): void
    {
        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xem]);
        $employee = $this->employee();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator(
                [$employee], 1, 20, 1,
            ));
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookups());
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($employee);
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('removeOrTerminate');
            $mock->shouldNotReceive('resetPassword');
        });

        $this->get('/admin/nhan-vien')->assertOk();
        $this->get('/admin/nhan-vien/NV001')->assertOk();

        $this->get('/admin/nhan-vien/create')->assertForbidden();
        $this->post('/admin/nhan-vien', [])->assertForbidden();
        $this->get('/admin/nhan-vien/NV001/edit')->assertForbidden();
        $this->put('/admin/nhan-vien/NV001', [])->assertForbidden();
        $this->delete('/admin/nhan-vien/NV001')->assertForbidden();
        $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')->assertForbidden();
    }

    public function test_tao_only_actor_can_create_and_store_but_no_other_action(): void
    {
        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Tao]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookups());
            $mock->shouldReceive('create')->once()->withArgs(function (array $validated): bool {
                return ! array_key_exists('ma_vt', $validated);
            })->andReturn('NV002');
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('findOrFail');
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('removeOrTerminate');
            $mock->shouldNotReceive('resetPassword');
        });
        $this->get('/admin/nhan-vien/create')->assertOk();
        $this->post('/admin/nhan-vien', $this->validPayload())->assertRedirect('/admin/nhan-vien/NV002');

        $this->get('/admin/nhan-vien')->assertForbidden();
        $this->get('/admin/nhan-vien/NV001')->assertForbidden();
        $this->get('/admin/nhan-vien/NV001/edit')->assertForbidden();
        $this->put('/admin/nhan-vien/NV001', [])->assertForbidden();
        $this->delete('/admin/nhan-vien/NV001')->assertForbidden();
        $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')->assertForbidden();
    }

    public function test_sua_only_actor_can_edit_and_update_but_no_other_action(): void
    {
        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Sua]);
        $target = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('NV001')->andReturn($target);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($target): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($target);
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookups());
            $mock->shouldReceive('update')->once()->with('NV001', Mockery::type('array'))->andReturn($target);
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('removeOrTerminate');
            $mock->shouldNotReceive('resetPassword');
        });
        $this->get('/admin/nhan-vien/NV001/edit')->assertOk();
        $this->get('/admin/nhan-vien/create')->assertForbidden();
        $this->put('/admin/nhan-vien/NV001', $this->validPayload())
            ->assertRedirect('/admin/nhan-vien/NV001');
        $this->get('/admin/nhan-vien')->assertForbidden();
        $this->get('/admin/nhan-vien/NV001')->assertForbidden();
        $this->post('/admin/nhan-vien', [])->assertForbidden();
        $this->delete('/admin/nhan-vien/NV001')->assertForbidden();
        $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')->assertForbidden();
    }

    public function test_xoa_only_actor_can_delete_but_cannot_reset_or_use_other_actions(): void
    {
        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xoa]);
        $target = $this->employee();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($target): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($target);
            $mock->shouldReceive('removeOrTerminate')->once()->with('NV001')->andReturn(NhanVienRemovalAction::Deleted);
            $mock->shouldNotReceive('resetPassword');
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('lookups');
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
        });

        $this->delete('/admin/nhan-vien/NV001')
            ->assertRedirect(route('backend.nhanvien.index'));
        $this->get('/admin/nhan-vien')->assertForbidden();
        $this->get('/admin/nhan-vien/NV001')->assertForbidden();
        $this->get('/admin/nhan-vien/create')->assertForbidden();
        $this->post('/admin/nhan-vien', [])->assertForbidden();
        $this->get('/admin/nhan-vien/NV001/edit')->assertForbidden();
        $this->put('/admin/nhan-vien/NV001', [])->assertForbidden();
        $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')->assertForbidden();
    }

    public function test_reset_only_actor_can_reset_but_cannot_delete_or_use_other_actions(): void
    {
        $this->actingAsEmployeeWithPermissions([NhanVienPermission::DatLaiMatKhau]);
        $target = $this->employee();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($target): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($target);
            $mock->shouldReceive('resetPassword')->once()->with('NV001');
            $mock->shouldNotReceive('removeOrTerminate');
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('lookups');
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
        });

        $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')
            ->assertRedirect(route('backend.nhanvien.show', ['ma_nv' => 'NV001']));
        $this->get('/admin/nhan-vien')->assertForbidden();
        $this->get('/admin/nhan-vien/NV001')->assertForbidden();
        $this->get('/admin/nhan-vien/create')->assertForbidden();
        $this->post('/admin/nhan-vien', [])->assertForbidden();
        $this->get('/admin/nhan-vien/NV001/edit')->assertForbidden();
        $this->put('/admin/nhan-vien/NV001', [])->assertForbidden();
        $this->delete('/admin/nhan-vien/NV001')->assertForbidden();
    }

    public function test_target_guard_runs_before_lifecycle_mutation_for_privileged_targets(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NhanVienPermission::Sua,
            NhanVienPermission::Xoa,
            NhanVienPermission::DatLaiMatKhau,
        ]);
        $target = $this->employee(['ky_hieu_vai_tro' => 'QUAN_TRI']);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($target): void {
            $mock->shouldReceive('findOrFail')->times(3)->with('NV001')->andReturn($target);
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('removeOrTerminate');
            $mock->shouldNotReceive('resetPassword');
        });

        $this->get('/admin/nhan-vien/NV001/edit')->assertForbidden();
        $this->delete('/admin/nhan-vien/NV001')->assertForbidden();
        $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')->assertForbidden();
    }

    public function test_non_baseline_nv002_target_is_forbidden_before_every_mutation_path(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NhanVienPermission::Xem,
            NhanVienPermission::Sua,
            NhanVienPermission::Xoa,
            NhanVienPermission::DatLaiMatKhau,
        ]);
        $target = $this->employee([
            'ma_nv' => 'NV002',
            'ky_hieu_vai_tro' => 'QUAN_TRI',
            'ten_vt' => 'Quản trị viên',
        ]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('NV002')->andReturn($target);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($target): void {
            $mock->shouldReceive('findOrFail')->times(3)->with('NV002')->andReturn($target);
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('removeOrTerminate');
            $mock->shouldNotReceive('resetPassword');
            $mock->shouldNotReceive('lookups');
        });

        $this->get('/admin/nhan-vien/NV002/edit')->assertForbidden();
        $this->put('/admin/nhan-vien/NV002', [])->assertForbidden();
        $this->delete('/admin/nhan-vien/NV002')->assertForbidden();
        $this->patch('/admin/nhan-vien/NV002/dat-lai-mat-khau')->assertForbidden();
    }

    public function test_blade_hides_create_and_manage_actions_when_actor_lacks_permissions(): void
    {
        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xem]);
        $employee = $this->employee();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator(
                [$employee],
                1,
                20,
                1,
            ));
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookups());
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($employee);
        });

        $this->get('/admin/nhan-vien')->assertOk()
            ->assertDontSee('href="'.route('backend.nhanvien.create').'"', false)
            ->assertDontSee('Đặt lại mật khẩu')
            ->assertDontSee('Xóa hoặc kết thúc');
        $this->get('/admin/nhan-vien/NV001')->assertOk()
            ->assertDontSee('Chỉnh sửa')
            ->assertDontSee('Đặt lại mật khẩu')
            ->assertDontSee('Xóa hoặc kết thúc');
    }

    public function test_edit_blade_runtime_hides_and_shows_update_form_by_sua_gate(): void
    {
        $viewData = [
            'employee' => $this->employee(),
            'lookups' => $this->lookups(),
            'lookupError' => null,
            'missingLookups' => [],
            'firstErrorField' => null,
            'firstErrorStep' => 1,
            'avatarUrl' => null,
            'errors' => new ViewErrorBag,
        ];

        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xem]);
        $withoutSua = view('backend.nhanvien.edit', $viewData)->render();
        $this->assertStringNotContainsString('data-employee-wizard', $withoutSua);
        $this->assertStringNotContainsString('data-submit-employee', $withoutSua);
        $this->assertStringNotContainsString('name="_method" value="PUT"', $withoutSua);
        $this->assertStringNotContainsString(
            'action="'.route('backend.nhanvien.update', ['ma_nv' => 'NV001']).'"',
            $withoutSua,
        );

        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xem, NhanVienPermission::Sua]);
        $withSua = view('backend.nhanvien.edit', $viewData)->render();
        $this->assertStringContainsString('data-employee-wizard', $withSua);
        $this->assertStringContainsString('data-submit-employee', $withSua);
        $this->assertStringContainsString('name="_method" value="PUT"', $withSua);
        $this->assertStringContainsString(
            'action="'.route('backend.nhanvien.update', ['ma_nv' => 'NV001']).'"',
            $withSua,
        );
    }

    private function employee(array $overrides = []): object
    {
        return (object) array_replace([
            'ma_nv' => 'NV001', 'ho_ten' => 'Nguyễn An', 'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1, 'sdt' => '0901234567', 'email' => 'an@example.test',
            'ngay_vao_lam' => '2020-01-01', 'ma_pb' => 1, 'ten_pb' => 'Kỹ thuật',
            'ma_cv' => 1, 'ten_cv' => 'Lập trình viên', 'dan_toc' => 'Kinh',
            'cccd' => '001200000001', 'noi_cap_cccd' => 'Cục CSQLHC', 'hoc_van' => 'Đại học',
            'ma_tt' => 1, 'ky_hieu' => 'DANG_LAM', 'ten_tt' => 'Đang làm việc',
            'ngay_nghi_viec' => null, 'ma_vt' => 1, 'ky_hieu_vai_tro' => 'NHAN_VIEN_MAC_DINH',
            'ten_vt' => 'Nhân viên', 'anh_dai_dien' => null,
            'dia_chi_cu_the' => '1 Nguyễn Trãi', 'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1', 'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'authorization@example.test',
            'ngay_vao_lam' => '2020-01-01',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => 1,
            'dia_chi_cu_the' => '1 Nguyễn Trãi',
            'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }

    private function lookups(): array
    {
        return [
            'phong_ban' => [(object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật']],
            'chuc_vu' => [(object) ['ma_cv' => 1, 'ten_cv' => 'Lập trình viên']],
            'trang_thai' => [(object) ['ma_tt' => 1, 'ky_hieu' => 'DANG_LAM', 'ten_tt' => 'Đang làm việc']],
        ];
    }
}
