<?php

namespace Tests\Feature\Backend;

use App\Enums\NghiPhepPermission;
use App\Services\NghiPhepService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class SelfServiceLeaveTest extends TestCase
{
    use InteractsWithEmployeeModule;

    public function test_authenticated_account_without_leave_permissions_can_read_and_create_own_leave(): void
    {
        $this->actingAsEmployeeWithPermissions([], [
            'ma_nv' => '00007',
            'ma_vt' => 5,
            'ma_pb' => 1,
        ]);

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getAll')->once()->withArgs(
            static fn (array $filters): bool => $filters['ma_nv'] === '00007'
                && ! array_key_exists('tu_khoa', $filters)
                && ! array_key_exists('ma_pb', $filters)
        )->andReturn([
            'success' => true,
            'data' => [],
            'counts' => ['pending' => 0, 'history' => 0],
        ]);
        $service->shouldReceive('create')->once()->withArgs(
            static fn (array $data): bool => $data['ma_nv'] === '00007'
                && $data['trang_thai_duyet'] === 0
                && $data['ma_lp'] === 1
        )->andReturn([
            'success' => true,
            'message' => 'Tạo nghỉ phép thành công',
            'data' => [],
        ]);
        $this->app->instance(NghiPhepService::class, $service);

        $this->getJson('/api/v1/nghi-phep/cua-toi')->assertOk();
        $this->postJson('/api/v1/nghi-phep/cua-toi', [
            'tu_ngay' => '2026-09-20',
            'den_ngay' => '2026-09-20',
            'ma_lp' => 1,
            'ly_do' => 'Việc gia đình',
        ])->assertCreated();
    }

    public function test_own_leave_rejects_client_identity_and_approval_status(): void
    {
        $this->actingAsEmployeeWithPermissions([], [
            'ma_nv' => '00007',
            'ma_vt' => 5,
        ]);

        $this->getJson('/api/v1/nghi-phep/cua-toi?ma_nv=00002')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ma_nv');

        $this->postJson('/api/v1/nghi-phep/cua-toi', [
            'ma_nv' => '00002',
            'trang_thai_duyet' => 1,
            'tu_ngay' => '2026-09-20',
            'den_ngay' => '2026-09-20',
            'ma_lp' => 1,
            'ly_do' => 'Giả mạo',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ma_nv', 'trang_thai_duyet']);
    }

    public function test_auth_only_create_lookups_and_web_form_do_not_open_management_routes(): void
    {
        $this->actingAsEmployeeWithPermissions([], [
            'ma_nv' => '00007',
            'ma_vt' => 5,
        ]);

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getLoaiPhep')->once()->andReturn([
            (object) ['ma_lp' => 1, 'ten_lp' => 'Phép năm'],
        ]);
        $service->shouldReceive('getPhongBan')->once()->andReturn([
            (object) ['ma_pb' => 1, 'ten_pb' => 'Nhân sự'],
        ]);
        $this->app->instance(NghiPhepService::class, $service);

        $this->get('/tao-nghi-phep')->assertOk();
        $this->getJson('/api/v1/nghi-phep/tao/loai-phep')->assertOk();
        $this->getJson('/api/v1/nghi-phep/tao/phong-ban')->assertOk();
        $this->getJson('/api/v1/nghi-phep')->assertForbidden();
        $this->getJson('/api/v1/nghi-phep/loai-phep')->assertForbidden();
        $this->getJson('/api/v1/nghi-phep/phong-ban')->assertForbidden();
    }

    public function test_self_service_routes_are_auth_only_and_management_create_stays_protected(): void
    {
        $routes = [
            'backend.nghiphep.create' => 'GET',
            'api.v1.nghi-phep.cua-toi' => 'GET',
            'api.v1.nghi-phep.cua-toi.store' => 'POST',
            'api.v1.nghi-phep.tao.loai-phep' => 'GET',
            'api.v1.nghi-phep.tao.phong-ban' => 'GET',
        ];

        foreach ($routes as $name => $method) {
            $route = Route::getRoutes()->getByName($name);

            self::assertInstanceOf(RoutingRoute::class, $route, $name);
            self::assertContains('auth', $route->gatherMiddleware(), $name);
            self::assertNotContains('can:NghiPhep.Insert', $route->gatherMiddleware(), $name);
        }

        $managementStore = Route::getRoutes()->getByName('nghi-phep.store');
        self::assertInstanceOf(RoutingRoute::class, $managementStore);
        self::assertContains('can:NghiPhep.Insert', $managementStore->gatherMiddleware());
    }

    public function test_guest_cannot_read_or_create_own_leave(): void
    {
        $this->get('/tao-nghi-phep')->assertRedirect(route('login'));
        $this->getJson('/api/v1/nghi-phep/cua-toi')->assertUnauthorized();
        $this->postJson('/api/v1/nghi-phep/cua-toi', [])->assertUnauthorized();
    }
}
