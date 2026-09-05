<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienPermission;
use App\Enums\PermissionAction;
use App\Services\PermissionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Mockery;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class NhanVienResetPasswordTest extends TestCase
{
    use InteractsWithEmployeeModule;

    public function test_reset_route_is_post_before_employee_show_and_uses_exact_gate(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->getName() === 'backend.nhanvien.reset-password');

        self::assertNotNull($route);
        self::assertSame('nhan-vien/{ma_nv}/reset-mat-khau', $route->uri());
        self::assertSame(['POST'], $route->methods());
        self::assertStringContainsString('can:NhanVien.ResetPassword', implode('|', $route->middleware()));
        $show = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->getName() === 'backend.nhanvien.show');
        self::assertLessThan(array_search($show, app('router')->getRoutes()->getRoutes(), true), array_search($route, app('router')->getRoutes()->getRoutes(), true));
    }

    public function test_permission_catalog_and_active_seed_are_contiguous_and_mapped(): void
    {
        $contract = new ReflectionMethod(NhanVienServiceContract::class, 'resetPassword');
        self::assertSame(['string', 'string'], array_map(
            static fn ($parameter): string => (string) $parameter->getType(),
            $contract->getParameters(),
        ));
        self::assertSame('void', (string) $contract->getReturnType());

        self::assertSame('NhanVien.ResetPassword', NhanVienPermission::DatLaiMatKhau->symbol());
        self::assertSame(42, NhanVienPermission::DatLaiMatKhau->id());
        self::assertSame('NhanVien', NhanVienPermission::DatLaiMatKhau->module());
        self::assertNull(NhanVienPermission::DatLaiMatKhau->action());
        self::assertSame(PermissionAction::View, PermissionAction::fromSymbol('NhanVien.Read'));

        $sql = File::get(base_path('database/sql/du_lieu_mau.sql'));
        self::assertMatchesRegularExpression('/\(37,\s*N\x27HeThong\.Config.*?\),\s*\R\s*\(38,/s', $sql);
        self::assertStringContainsString("(42, N'NhanVien.ResetPassword', N'Đặt lại mật khẩu', N'NhanVien')", $sql);
        self::assertStringContainsString('ALTER TABLE quyen AUTO_INCREMENT = 43;', $sql);
        foreach (range(1, 42) as $permissionId) {
            self::assertStringContainsString("(1, {$permissionId})", $sql);
        }
        self::assertStringContainsString('(2, 42)', $sql);
    }

    public function test_unauthorized_actor_cannot_reset(): void
    {
        $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xem]);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('resetPassword')->never();
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->post('/nhan-vien/00001/reset-mat-khau')->assertForbidden();
    }

    public function test_authorized_reset_uses_route_target_and_safe_flash_only(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NhanVienPermission::Xem,
            NhanVienPermission::DatLaiMatKhau,
        ]);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('resetPassword')->once()->with('00001', '00999');
        $this->app->instance(NhanVienServiceContract::class, $service);

        $response = $this->post('/nhan-vien/00001/reset-mat-khau');

        $response->assertRedirect(route('backend.nhanvien.show', ['ma_nv' => '00001']));
        self::assertSame('Đã đặt lại mật khẩu theo quy ước mật khẩu mặc định.', session('success'));
        self::assertStringNotContainsString('nhom3@', (string) session('success'));
        self::assertStringNotContainsString('hash', strtolower((string) session('success')));
    }

    public function test_crafted_plaintext_hash_and_target_body_fields_are_rejected(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NhanVienPermission::Xem,
            NhanVienPermission::DatLaiMatKhau,
        ]);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('resetPassword')->never();
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->postJson('/nhan-vien/00001/reset-mat-khau', [
            'ma_nv' => '99999',
            'target' => '99999',
            'password' => 'crafted-secret',
            'mat_khau_hash' => 'crafted-hash',
        ])->assertUnprocessable()->assertJsonValidationErrors(['ma_nv', 'target', 'password', 'mat_khau_hash']);
    }

    public function test_employee_action_sources_are_gate_and_target_bound_on_index_show_and_edit(): void
    {
        foreach (['index.blade.php', 'show.blade.php', 'edit.blade.php'] as $view) {
            $source = File::get(resource_path('views/backend/nhanvien/'.$view));
            self::assertStringContainsString('partials.action-dialogs', $source);
        }

        $partial = File::get(resource_path('views/backend/nhanvien/partials/action-dialogs.blade.php'));
        self::assertStringContainsString('NhanVienPermission::DatLaiMatKhau', $partial);
        self::assertStringContainsString('reset-password', $partial);
        self::assertStringContainsString('data-confirm-action="reset-password"', $partial);
        self::assertStringContainsString('employee->ma_nv', $partial);
        self::assertStringContainsString('employee->ho_ten', $partial);
        self::assertStringContainsString('@csrf', $partial);
    }

    public function test_permission_definition_is_not_a_crud_action(): void
    {
        self::assertNull(NhanVienPermission::DatLaiMatKhau->action());
    }
}
