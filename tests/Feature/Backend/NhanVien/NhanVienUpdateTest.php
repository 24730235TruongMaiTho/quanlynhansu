<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Exceptions\NhanVienDomainException;
use App\Http\Controllers\Backend\NhanVienController;
use App\Http\Middleware\EnsureNhanVienModuleEnabled;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\CreatesEmployeeFeatureSchema;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienUpdateTest extends TestCase
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
                $entries = is_array($entrypoints) ? $entrypoints : [$entrypoints];

                return new HtmlString(collect($entries)->map(
                    fn (string $entry): string => '<script type="module" src="/build/'.basename($entry).'"></script>',
                )->implode(''));
            }
        });
    }

    protected function tearDown(): void
    {
        $this->dropEmployeeFeatureSchema();
        parent::tearDown();
    }

    public function test_edit_and_update_routes_are_guarded_constrained_and_declared_before_show(): void
    {
        $edit = Route::getRoutes()->getByName('backend.nhanvien.edit');
        $update = Route::getRoutes()->getByName('backend.nhanvien.update');
        $show = Route::getRoutes()->getByName('backend.nhanvien.show');

        $this->assertInstanceOf(RoutingRoute::class, $edit);
        $this->assertInstanceOf(RoutingRoute::class, $update);
        $this->assertSame('admin/nhan-vien/{ma_nv}/edit', $edit->uri());
        $this->assertSame(['GET', 'HEAD'], $edit->methods());
        $this->assertSame(NhanVienController::class.'@edit', $edit->getActionName());
        $this->assertSame('admin/nhan-vien/{ma_nv}', $update->uri());
        $this->assertSame(['PUT', 'PATCH'], $update->methods());
        $this->assertSame(NhanVienController::class.'@update', $update->getActionName());
        foreach ([$edit, $update] as $route) {
            $this->assertSame('NV[0-9]{3}', $route->wheres['ma_nv']);
            $this->assertContains(EnsureNhanVienModuleEnabled::class, $route->gatherMiddleware());
            $this->assertLessThan(
                array_search($show, Route::getRoutes()->getRoutes(), true),
                array_search($route, Route::getRoutes()->getRoutes(), true),
            );
        }
    }

    public function test_rollout_disabled_or_invalid_codes_never_dispatch_edit_or_update(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        config()->set('nhanvien.enabled', false);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('findOrFail');
            $mock->shouldNotReceive('update');
        });
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldNotReceive('find');
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->get('/admin/nhan-vien/NV001/edit')->assertNotFound();
        $this->put('/admin/nhan-vien/NV001', $this->validPayload())->assertNotFound();

        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        foreach (['NV1', 'NV0001', 'nv001'] as $code) {
            $this->get("/admin/nhan-vien/{$code}/edit")->assertNotFound();
            $this->put("/admin/nhan-vien/{$code}", $this->validPayload())->assertNotFound();
        }
    }

    public function test_privileged_edit_and_valid_or_invalid_update_return_generic_403_before_mutation(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $privileged = $this->employee(['ky_hieu_vai_tro' => 'QUAN_TRI', 'email' => 'admin@example.test']);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($privileged): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($privileged);
            $mock->shouldNotReceive('lookups');
            $mock->shouldNotReceive('update');
        });
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->twice()->with('NV001')->andReturn($privileged);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->get('/admin/nhan-vien/NV001/edit')
            ->assertForbidden()
            ->assertDontSee('QUAN_TRI')
            ->assertDontSee('admin@example.test');
        $this->put('/admin/nhan-vien/NV001', $this->validPayload())
            ->assertForbidden()
            ->assertDontSee('QUAN_TRI')
            ->assertDontSee('admin@example.test');
        $this->put('/admin/nhan-vien/NV001', ['email' => 'invalid'])
            ->assertForbidden()
            ->assertDontSee('validation')
            ->assertDontSee('admin@example.test');
    }

    public function test_missing_update_target_is_safe_404_before_validation_and_mutation(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $this->mock(NhanVienServiceContract::class, fn (MockInterface $mock) => $mock->shouldNotReceive('update'));
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('NV404')->andReturnNull();
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->put('/admin/nhan-vien/NV404', ['email' => 'invalid'])
            ->assertNotFound()
            ->assertDontSee('email');
    }

    public function test_successful_update_uses_cached_authorized_target_and_redirects_with_exact_flash(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $target = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('NV001')->andReturn($target);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('update')->once()->withArgs(fn (string $maNv, array $validated): bool => $maNv === 'NV001'
                && $validated['email'] === 'updated@example.test'
                && ! array_key_exists('ma_vt', $validated)
            )->andReturn($this->employee(['email' => 'updated@example.test']));
        });

        $this->put('/admin/nhan-vien/NV001', $this->validPayload([
            'email' => ' UPDATED@EXAMPLE.TEST ',
        ]))->assertRedirect(route('backend.nhanvien.show', ['ma_nv' => 'NV001']))
            ->assertSessionHas('success', 'Đã cập nhật hồ sơ nhân viên.');
    }

    public function test_race_time_privileged_domain_error_maps_to_safe_403(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->andReturn($this->employee());
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new NhanVienDomainException(
                'Bạn không có quyền thực hiện thao tác này.',
                'NV_PRIVILEGED_TARGET',
            ));
        });

        $this->put('/admin/nhan-vien/NV001', $this->validPayload())
            ->assertForbidden()
            ->assertDontSee('NV_PRIVILEGED_TARGET');
    }

    public function test_domain_field_error_preserves_only_safe_old_input_and_generic_error_leaks_nothing(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->twice()->andReturn($this->employee());
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new NhanVienDomainException(
                'Email đã được sử dụng.',
                'NV_EMAIL_DUPLICATE',
                'email',
            ));
            $mock->shouldReceive('update')->once()->andThrow(new RuntimeException(
                'SQLSTATE secret path C:\\avatar\\private.png',
            ));
        });

        $this->from('/admin/nhan-vien/NV001/edit')
            ->put('/admin/nhan-vien/NV001', $this->validPayload([
                'email' => 'collision@example.test',
            ]))
            ->assertRedirect('/admin/nhan-vien/NV001/edit')
            ->assertSessionHasErrors(['email'])
            ->assertSessionHasInput('email', 'collision@example.test');

        $this->from('/admin/nhan-vien/NV001/edit')
            ->put('/admin/nhan-vien/NV001', $this->validPayload())
            ->assertRedirect('/admin/nhan-vien/NV001/edit')
            ->assertSessionHasErrors([
                'nhan_vien' => 'Không thể cập nhật nhân viên lúc này. Vui lòng thử lại sau.',
            ])
            ->assertSessionMissing('_old_input.anh_dai_dien');
    }

    public function test_active_edit_renders_safe_accessible_form_and_locks_on_missing_lookups(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findOrFail')->twice()->with('NV001')->andReturn($this->employee());
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookups());
            $mock->shouldReceive('lookups')->once()->andReturn([
                'phong_ban' => [],
                'chuc_vu' => [],
                'trang_thai' => [],
            ]);
        });

        $response = $this->get('/admin/nhan-vien/NV001/edit?'.http_build_query([
            'tu_khoa' => 'An', 'ma_pb' => 1, 'page' => 2, 'redirect' => 'https://evil.example',
        ]));
        $response->assertOk()
            ->assertViewIs('backend.nhanvien.edit')
            ->assertSee('action="'.route('backend.nhanvien.update', ['ma_nv' => 'NV001']).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Nguyễn An"', false)
            ->assertSee('name="sdt"', false)
            ->assertSee('value="0901234567"', false)
            ->assertSee('name="email"', false)
            ->assertSee('value="an@example.test"', false)
            ->assertSee('name="xoa_anh_dai_dien"', false)
            ->assertSee('data-avatar-delete', false)
            ->assertSee('Nhân viên')
            ->assertDontSee('NHAN_VIEN_MAC_DINH')
            ->assertDontSee('name="ma_nv"', false)
            ->assertDontSee('name="ma_vt"', false)
            ->assertDontSee('name="mat_khau"', false)
            ->assertDontSee('name="mat_khau_hash"', false)
            ->assertDontSee('name="ngay_nghi_viec"', false)
            ->assertDontSee('Đã nghỉ việc')
            ->assertDontSee('evil.example')
            ->assertSee('/build/nhanvien.js', false);
        $this->assertSame(1, substr_count($response->getContent(), '/build/nhanvien.js'));

        $this->get('/admin/nhan-vien/NV001/edit')
            ->assertOk()
            ->assertSee('Thiếu dữ liệu danh mục bắt buộc')
            ->assertSee('data-submit-employee', false)
            ->assertSee('disabled', false);
    }

    public function test_terminated_edit_keeps_canonical_status_read_only_and_lookup_failure_is_safe_locked(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $terminated = $this->employee([
            'ma_tt' => 3,
            'ky_hieu' => 'DA_NGHI',
            'ten_tt' => 'Đã nghỉ việc',
            'ngay_nghi_viec' => '2026-07-01',
        ]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($terminated): void {
            $mock->shouldReceive('findOrFail')->twice()->andReturn($terminated);
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookups());
            $mock->shouldReceive('lookups')->once()->andThrow(new NhanVienDomainException(
                'SQLSTATE private details',
                'NV_DATABASE_ERROR',
            ));
        });

        $this->get('/admin/nhan-vien/NV001/edit')
            ->assertOk()
            ->assertSee('Trạng thái không thể thay đổi qua cập nhật hồ sơ')
            ->assertSee('type="hidden" name="ma_tt" value="3"', false)
            ->assertSee('<dd class="col-sm-7">Đã nghỉ việc</dd>', false)
            ->assertDontSee('data-review-output="ma_tt"', false)
            ->assertDontSee('id="ma_tt"', false);

        $this->get('/admin/nhan-vien/NV001/edit')
            ->assertOk()
            ->assertSee('Không thể tải dữ liệu danh mục lúc này. Vui lòng thử lại sau.')
            ->assertSee('disabled', false)
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('NV_DATABASE_ERROR');
    }

    public function test_dynamic_employee_name_is_escaped_in_the_document_title(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);
        $employee = $this->employee(['ho_ten' => '</title><script>alert(1)</script>']);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('findOrFail')->once()->andReturn($employee);
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookups());
        });

        $this->get('/admin/nhan-vien/NV001/edit')
            ->assertOk()
            ->assertSee('&lt;/title&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('</title><script>alert(1)</script>', false);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'updated@example.test',
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
            'ten_vt' => 'Nhân viên',
            'anh_dai_dien' => 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png',
            'dia_chi_cu_the' => '1 Nguyễn Trãi', 'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1', 'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }

    private function lookups(): array
    {
        return [
            'phong_ban' => [(object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật']],
            'chuc_vu' => [(object) ['ma_cv' => 1, 'ten_cv' => 'Lập trình viên']],
            'trang_thai' => [
                (object) ['ma_tt' => 1, 'ky_hieu' => 'DANG_LAM', 'ten_tt' => 'Đang làm việc'],
                (object) ['ma_tt' => 2, 'ky_hieu' => 'THU_VIEC', 'ten_tt' => 'Thử việc'],
                (object) ['ma_tt' => 3, 'ky_hieu' => 'DA_NGHI', 'ten_tt' => 'Đã nghỉ việc'],
            ],
        ];
    }
}
