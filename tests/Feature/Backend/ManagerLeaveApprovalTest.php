<?php

namespace Tests\Feature\Backend;

use App\Enums\NghiPhepPermission;
use App\Models\NhanVien;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ManagerLeaveApprovalTest extends TestCase
{
    public function test_department_manager_gate_requires_exact_role_and_valid_department(): void
    {
        $manager = $this->actor(['ma_vt' => 4, 'ma_pb' => 2]);
        $employee = $this->actor(['ma_vt' => 5, 'ma_pb' => 2]);
        $missingDepartment = $this->actor(['ma_vt' => 4, 'ma_pb' => null]);
        $invalidDepartment = $this->actor(['ma_vt' => 4, 'ma_pb' => 0]);

        self::assertTrue(\Illuminate\Support\Facades\Gate::forUser($manager)->allows('department-manager'));
        self::assertFalse(\Illuminate\Support\Facades\Gate::forUser($employee)->allows('department-manager'));
        self::assertFalse(\Illuminate\Support\Facades\Gate::forUser($missingDepartment)->allows('department-manager'));
        self::assertFalse(\Illuminate\Support\Facades\Gate::forUser($invalidDepartment)->allows('department-manager'));
    }

    public function test_approval_routes_require_auth_permission_and_manager_middleware(): void
    {
        $expected = [
            'api.v1.nghi-phep.phe-duyet' => [
                'auth',
                'can:'.NghiPhepPermission::Xem->value,
                'can:department-manager',
            ],
            'api.v1.nghi-phep.duyet' => [
                'auth',
                'can:'.NghiPhepPermission::Sua->value,
                'can:department-manager',
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
        self::assertStringContainsString('->duyet((int) $ma_np, (int) $validated[\'trang_thai_duyet\'], (int) $department)', $controller);
        self::assertStringContainsString('JsonPaginator::from($data)', $controller);
        self::assertStringNotContainsString('$request->input(\'ma_nv\')', $controller);
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
        self::assertStringContainsString('pending_department_leave_count', $service);
        self::assertStringContainsString('pending_department_leave_count', $controller);
        self::assertStringContainsString('id="statsCards"', $view);
        self::assertStringNotContainsString('id="statCards"', $view);
        self::assertStringContainsString("route('backend.nghiphep.index') . '#leave-table-card'", $view);
        self::assertStringContainsString('id="leave-table-card"', file_get_contents(resource_path('views/backend/nghiphep/index.blade.php')));
        self::assertStringNotContainsString('backend.nghiphep.duyet-nghi-phep', $view);
        self::assertStringContainsString('pendingDepartmentLeaveCount', $view);
        self::assertStringContainsString("const pendingLeaveCountElement = document.getElementById('pendingDepartmentLeaveCount');", $view);
        self::assertStringContainsString('if (pendingLeaveCountElement) {', $view);
        self::assertStringContainsString('Nghỉ phép chờ duyệt', $view);
        self::assertStringContainsString('NghiPhepPermission::Xem->value', $view);
        self::assertStringContainsString('NghiPhepPermission::Sua->value', $view);
        self::assertStringContainsString("Gate::allows('department-manager')", $view);
    }

    private function actor(array $overrides = []): NhanVien
    {
        return NhanVien::fromAuthRow((object) array_replace([
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 5,
            'ma_pb' => null,
            'ma_tt' => 1,
        ], $overrides));
    }
}
