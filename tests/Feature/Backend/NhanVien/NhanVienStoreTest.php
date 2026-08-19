<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienServiceContract;
use App\Exceptions\NhanVienDomainException;
use App\Http\Controllers\Backend\NhanVienController;
use App\Http\Middleware\EnsureNhanVienModuleEnabled;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Tests\Support\CreatesEmployeeFeatureSchema;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienStoreTest extends TestCase
{
    use CreatesEmployeeFeatureSchema;
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEmployeeFeatureSchema();
    }

    protected function tearDown(): void
    {
        $this->dropEmployeeFeatureSchema();
        parent::tearDown();
    }

    public function test_store_is_fail_closed_before_validation_or_service(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        $this->post('/admin/nhan-vien', [])->assertNotFound();
    }

    public function test_enabled_store_redirects_to_canonical_show_with_safe_exact_handoff_flash(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->withArgs(function (array $validated): bool {
                $this->assertArrayNotHasKey('ma_vt', $validated);
                $this->assertArrayNotHasKey('mat_khau', $validated);

                return true;
            })->andReturn('NV001');
        });

        $response = $this->post('/admin/nhan-vien', $this->validPayload());

        $response
            ->assertRedirect('/admin/nhan-vien/NV001')
            ->assertSessionHas('success', 'Đã tạo nhân viên; có thể bổ sung hợp đồng sau.')
            ->assertSessionHas('created_employee_code', 'NV001')
            ->assertSessionHas(
                'password_convention',
                'Tài khoản dùng quy ước mật khẩu demo nhom3@{năm tạo}.',
            )
            ->assertSessionMissing('mat_khau')
            ->assertSessionMissing('password')
            ->assertSessionMissing('password_hash');

        $session = session()->all();
        $this->assertStringNotContainsString('nhom3@2026', json_encode($session, JSON_UNESCAPED_UNICODE));
    }

    public function test_domain_field_error_returns_old_safe_input_without_internal_details(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->andThrow(new NhanVienDomainException(
                'Email đã được sử dụng.',
                'NV_EMAIL_DUPLICATE',
                'email',
            ));
        });

        $this->from('/admin/nhan-vien/create')
            ->post('/admin/nhan-vien', $this->validPayload())
            ->assertRedirect('/admin/nhan-vien/create')
            ->assertSessionHasErrors(['email' => 'Email đã được sử dụng.'])
            ->assertSessionHasInput('ho_ten', 'Nguyễn An')
            ->assertSessionMissing('NV_EMAIL_DUPLICATE')
            ->assertSessionMissing('SQLSTATE');
    }

    public function test_store_route_is_canonical_guarded_and_does_not_accept_client_role(): void
    {
        $route = Route::getRoutes()->getByName('backend.nhanvien.store');
        $this->assertInstanceOf(RoutingRoute::class, $route);
        $this->assertSame('admin/nhan-vien', $route->uri());
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame(NhanVienController::class.'@store', $route->getActionName());
        $this->assertContains(EnsureNhanVienModuleEnabled::class, $route->gatherMiddleware());

        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });
        $this->post('/admin/nhan-vien', $this->validPayload(['ma_vt' => 99]))
            ->assertSessionHasErrors('ma_vt');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '2000-08-12',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'nhanvien@example.test',
            'ngay_vao_lam' => '2026-08-12',
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
}
