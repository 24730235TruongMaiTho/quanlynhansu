<?php

namespace Tests\Feature\Backend;

use App\Enums\NghiPhepPermission;
use App\Models\NhanVien;
use App\Services\NghiPhepService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class ManagerLeaveApprovalTest extends TestCase
{
    use InteractsWithEmployeeModule;

    public function test_approval_routes_require_read_for_list_and_approve_for_list_and_mutation(): void
    {
        $expected = [
            'api.v1.nghi-phep.phe-duyet' => [
                'auth',
                'can:'.NghiPhepPermission::Xem->value,
                'can:'.NghiPhepPermission::Duyet->value,
            ],
            'api.v1.nghi-phep.duyet' => [
                'auth',
                'can:'.NghiPhepPermission::Duyet->value,
            ],
        ];

        foreach ($expected as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);
            self::assertInstanceOf(RoutingRoute::class, $route, $name);
            foreach ($middleware as $item) {
                self::assertContains($item, $route->gatherMiddleware(), $name);
            }
        }

        self::assertNull(
            Route::getRoutes()->getByName('backend.nghiphep.duyet-nghi-phep'),
            'The standalone leave-approval web route is no longer canonical.',
        );
    }

    public function test_actor_with_read_and_approve_can_list_without_manager_role_or_department(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Xem,
            NghiPhepPermission::Duyet,
        ], ['ma_vt' => 5, 'ma_pb' => null]);
        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getApprovalList')->once()->withArgs(static function (array $filters): bool {
            return ! array_key_exists('ma_pb', $filters);
        })->andReturn(new LengthAwarePaginator([], 0, 10));
        $this->app->instance(NghiPhepService::class, $service);

        $this->getJson('/api/v1/nghi-phep/phe-duyet')->assertOk();
    }

    public function test_update_only_manager_without_approve_is_denied_before_service(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Xem,
            NghiPhepPermission::Sua,
        ], ['ma_vt' => 4, 'ma_pb' => 2]);
        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('duyet')->never();
        $this->app->instance(NghiPhepService::class, $service);

        $this->patchJson('/api/v1/nghi-phep/1/duyet', ['trang_thai_duyet' => 1])
            ->assertForbidden();
    }

    public function test_any_role_with_approve_and_no_department_can_patch_leave(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Duyet,
        ], ['ma_vt' => 6, 'ma_pb' => null]);
        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('duyet')
            ->once()
            ->with(17, 2)
            ->andReturn(['success' => true]);
        $this->app->instance(NghiPhepService::class, $service);

        $this->patchJson('/api/v1/nghi-phep/17/duyet', ['trang_thai_duyet' => 2])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_approval_controller_and_service_use_route_id_and_server_scope_only(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Backend/NghiPhepController.php'));
        $request = file_get_contents(app_path('Http/Requests/UpdateNghiPhepRequest.php'));
        $service = file_get_contents(app_path('Services/NghiPhepService.php'));

        self::assertIsString($controller);
        self::assertIsString($request);
        self::assertIsString($service);
        self::assertStringContainsString('$validated = $request->validate(', $controller);
        self::assertStringContainsString("'trang_thai_duyet'", $controller);
        self::assertStringContainsString('->duyet((int) $ma_np, (int) $validated[\'trang_thai_duyet\'])', $controller);
        self::assertStringContainsString('JsonPaginator::from($data)', $controller);
        self::assertStringNotContainsString('$request->input(\'ma_nv\')', $controller);
        self::assertStringNotContainsString('NhanVienScope', $controller);
        self::assertStringNotContainsString('department-manager', $controller);
        self::assertStringNotContainsString('$department', $controller);
        self::assertStringNotContainsString('sp_nghi_phep_duyet_phep', $controller);
        self::assertStringContainsString('->lockForUpdate()', $service);
        self::assertStringContainsString('return DB::transaction(', $service);
        self::assertStringContainsString("->where('trang_thai_duyet', 0)", $service);
        self::assertStringContainsString("'trang_thai_duyet' => ['prohibited']", $request);
    }

    public function test_dashboard_contract_exposes_eligible_manager_count_and_clickable_card(): void
    {
        $service = file_get_contents(app_path('Services/DashboardService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Backend/DashboardController.php'));
        $view = file_get_contents(resource_path('views/backend/tongquan/index.blade.php'));

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($view);
        self::assertStringContainsString('pending_leave_count', $service);
        self::assertStringContainsString('pending_leave_count', $controller);
        self::assertStringNotContainsString('pending_department_leave_count', $service);
        self::assertStringNotContainsString('department-manager', $service);
        $methodStart = strpos($service, 'public function getPendingLeaveCount');
        $methodEnd = strpos($service, "\n    /**", $methodStart === false ? 0 : $methodStart);
        self::assertNotFalse($methodStart);
        self::assertNotFalse($methodEnd);
        $pendingMethod = substr($service, $methodStart, $methodEnd - $methodStart);
        self::assertStringNotContainsString('getMessage()', $pendingMethod);
        self::assertStringContainsString("'exception_class' => \$e::class", $pendingMethod);
        self::assertStringContainsString('id="statsCards"', $view);
        self::assertStringNotContainsString('id="statCards"', $view);
        self::assertStringContainsString("route('backend.nghiphep.index') . '#leave-table-card'", $view);
        self::assertStringContainsString('id="leave-table-card"', file_get_contents(resource_path('views/backend/nghiphep/index.blade.php')));
        self::assertStringNotContainsString('backend.nghiphep.duyet-nghi-phep', $view);
        self::assertStringContainsString('pendingLeaveCount', $view);
        self::assertStringContainsString("const pendingLeaveCountElement = document.getElementById('pendingLeaveCount');", $view);
        self::assertStringContainsString('if (pendingLeaveCountElement) {', $view);
        self::assertStringContainsString('Nghỉ phép chờ duyệt', $view);
        self::assertStringContainsString('NghiPhepPermission::Xem->value', $view);
        self::assertStringContainsString('NghiPhepPermission::Duyet->value', $view);
        self::assertStringNotContainsString('NghiPhepPermission::Sua->value', $view);
        self::assertStringNotContainsString("Gate::allows('department-manager')", $view);
    }
}
