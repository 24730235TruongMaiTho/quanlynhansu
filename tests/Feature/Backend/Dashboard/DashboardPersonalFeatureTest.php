<?php

namespace Tests\Feature\Backend\Dashboard;

use App\Enums\ChamCongPermission;
use App\Enums\HopDongPermission;
use App\Enums\LuongPermission;
use App\Enums\NghiPhepPermission;
use App\Enums\NhanVienPermission;
use App\Enums\PhongBanPermission;
use App\Models\NhanVien;
use App\Services\PermissionService;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DashboardPersonalFeatureTest extends TestCase
{
    public function test_personal_dashboard_route_is_web_auth_only_and_exists(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.dashboard.personal');

        self::assertInstanceOf(RoutingRoute::class, $route);
        self::assertContains('GET', $route->methods());
        self::assertContains('web', $route->gatherMiddleware());
        self::assertContains('auth', $route->gatherMiddleware());
        $this->getJson('/api/v1/dashboard/personal')->assertUnauthorized();
    }

    public function test_dashboard_company_endpoints_are_gated_by_exact_module_permissions(): void
    {
        $expected = [
            'api.v1.dashboard.education-stats' => ['can:'.NhanVienPermission::Xem->value],
            'api.v1.dashboard.department-stats' => [
                'can:'.NhanVienPermission::Xem->value,
                'can:'.PhongBanPermission::Xem->value,
            ],
            'api.v1.dashboard.expiring-contracts' => ['can:'.HopDongPermission::Xem->value],
            'api.v1.dashboard.attendance-report' => ['can:'.ChamCongPermission::Xem->value],
            'api.v1.dashboard.salary-report' => ['can:'.LuongPermission::Xem->value],
        ];

        foreach ($expected as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);
            self::assertInstanceOf(RoutingRoute::class, $route, $name);
            self::assertContains('web', $route->gatherMiddleware(), $name);
            self::assertContains('auth', $route->gatherMiddleware(), $name);
            foreach ($middleware as $permission) {
                self::assertContains($permission, $route->gatherMiddleware(), $name);
            }
        }
    }

    public function test_personal_dashboard_contract_has_five_independent_widgets_without_salary_amount_fields(): void
    {
        $serviceSource = file_get_contents(app_path('Services/PersonalDashboardService.php'));
        $viewSource = file_get_contents(resource_path('views/backend/tongquan/index.blade.php'));

        self::assertIsString($serviceSource);
        self::assertIsString($viewSource);
        foreach (['profile', 'attendance', 'leave', 'contract', 'salary'] as $widget) {
            self::assertStringContainsString("'$widget'", $serviceSource);
            self::assertStringContainsString("data-personal-widget=\"$widget\"", $viewSource);
        }

        foreach (['luong_co_ban', 'thuong', 'phat', 'bao_hiem', 'thue', 'tong_luong', 'luong_trung_binh'] as $amountField) {
            self::assertStringNotContainsString("'$amountField'", $serviceSource);
            self::assertStringNotContainsString("\"$amountField\"", $serviceSource);
        }
        self::assertStringContainsString("'state'", $serviceSource);
        self::assertStringContainsString('CurrentEmployee', $serviceSource);
    }

    public function test_dashboard_view_separates_personal_and_company_sections_with_server_side_gating(): void
    {
        $source = file_get_contents(resource_path('views/backend/tongquan/index.blade.php'));

        self::assertIsString($source);
        self::assertStringContainsString('Tổng quan cá nhân', $source);
        self::assertStringContainsString('Tổng quan công ty', $source);
        self::assertStringContainsString("Gate::allows('NhanVien.Read')", $source);
        self::assertStringContainsString("Gate::allows('PhongBan.Read')", $source);
        self::assertStringContainsString("Gate::allows('HopDong.Read')", $source);
        self::assertStringContainsString("Gate::allows('ChamCong.Read')", $source);
        self::assertStringContainsString("Gate::allows('Luong.Read')", $source);
        self::assertStringContainsString('NghiPhepPermission::Xem->value', $source);
        self::assertStringContainsString("fetchJson('/api/v1/dashboard/personal'", $source);
        self::assertStringContainsString('Promise.allSettled', $source);
        self::assertStringContainsString('textContent', $source);
    }

    public function test_actor_without_company_permissions_gets_personal_dashboard_only_and_forbidden_company_endpoint(): void
    {
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('allows')->andReturnFalse();
            $mock->shouldReceive('canSeeModule')->andReturnFalse();
        });
        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nhân viên',
            'email' => 'employee@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 5,
            'ma_tt' => 1,
        ]);

        $this->actingAs($actor)
            ->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->actingAs($actor)
            ->getJson('/api/v1/dashboard/education-stats')
            ->assertForbidden();
    }

    public function test_dashboard_view_renders_personal_section_without_company_markup_for_employee(): void
    {
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('allows')->andReturnFalse();
            $mock->shouldReceive('canSeeModule')->andReturnFalse();
        });

        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nhân viên',
            'email' => 'employee@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 5,
            'ma_tt' => 1,
        ]);

        $this->actingAs($actor)
            ->get('/tong-quan')
            ->assertOk()
            ->assertSee('Tổng quan cá nhân', false)
            ->assertDontSee('Tổng quan công ty', false)
            ->assertDontSee('Tổng nhân viên', false)
            ->assertSee('data-personal-widget="salary"', false);
    }

    public function test_personal_endpoint_ignores_forged_employee_selector_and_passes_authenticated_actor_only(): void
    {
        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Chính chủ',
            'email' => 'owner@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 5,
            'ma_tt' => 1,
        ]);

        $this->actingAs($actor)
            ->getJson('/api/v1/dashboard/personal?ma_nv=00002')
            ->assertOk()
            ->assertJsonPath('data.profile.data.ma_nv', '00001')
            ->assertJsonMissingPath('data.profile.data.luong_co_ban');
    }

    public function test_personal_endpoint_rejects_noncanonical_authenticated_identity(): void
    {
        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '0001',
            'ho_ten' => 'Mã không hợp lệ',
            'email' => 'invalid@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 5,
            'ma_tt' => 1,
        ]);

        $this->actingAs($actor)
            ->getJson('/api/v1/dashboard/personal')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tài khoản không hợp lệ.');
    }
}
