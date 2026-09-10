<?php

namespace Tests\Feature\Backend;

use App\Enums\NghiPhepPermission;
use App\Services\NghiPhepService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class NghiPhepCreatePermissionContractTest extends TestCase
{
    use InteractsWithEmployeeModule;

    public function test_insert_only_actor_can_use_create_lookups_but_not_read_lookups(): void
    {
        $this->actingAsEmployeeWithPermissions([NghiPhepPermission::Tao]);
        $this->mock(NghiPhepService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getLoaiPhep')->once()->andReturn([
                (object) ['ma_lp' => 1, 'ten_lp' => 'Phép năm'],
            ]);
            $mock->shouldReceive('getPhongBan')->once()->andReturn([
                (object) ['ma_pb' => 2, 'ten_pb' => 'Nhân sự'],
            ]);
        });

        $this->getJson('/api/v1/nghi-phep/tao/loai-phep')
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->getJson('/api/v1/nghi-phep/tao/phong-ban')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/nghi-phep/loai-phep')->assertForbidden();
        $this->getJson('/api/v1/nghi-phep/phong-ban')->assertForbidden();
    }

    public function test_create_lookup_routes_use_insert_permission_and_explicit_names(): void
    {
        foreach ([
            'api.v1.nghi-phep.tao.loai-phep',
            'api.v1.nghi-phep.tao.phong-ban',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            self::assertNotNull($route, $name);
            self::assertContains('auth', $route->gatherMiddleware(), $name);
            self::assertContains('can:NghiPhep.Insert', $route->gatherMiddleware(), $name);
        }
    }

    public function test_insert_only_actor_sees_create_link_without_leave_list_link(): void
    {
        $this->actingAsEmployeeWithPermissions([NghiPhepPermission::Tao]);

        $this->view('backend.layouts.sidebar')
            ->assertSee('Quản lý nghỉ phép')
            ->assertSee('Tạo nghỉ phép')
            ->assertDontSee('Danh sách nghỉ phép');
    }

    public function test_insert_only_actor_can_read_only_own_history_and_request_ma_nv_is_ignored(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Tao,
        ], ['ma_nv' => '00999']);

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getAll')
            ->once()
            ->withArgs(static function (array $filters): bool {
                return ($filters['ma_nv'] ?? null) === '00999'
                    && ($filters['tab'] ?? null) === 'history'
                    && ($filters['per_page'] ?? null) === 50;
            })
            ->andReturn([
                'success' => true,
                'data' => [],
                'counts' => ['pending' => 0, 'history' => 0],
            ]);
        $this->app->instance(NghiPhepService::class, $service);

        $this->getJson('/api/v1/nghi-phep/cua-toi?ma_nv=00001&tab=history&per_page=50')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/nghi-phep')
            ->assertForbidden();
    }

    public function test_self_history_route_is_named_and_declared_before_resource_parameter_route(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.nghi-phep.cua-toi');

        self::assertInstanceOf(RoutingRoute::class, $route);
        self::assertSame('api/v1/nghi-phep/cua-toi', $route->uri());
        self::assertSame(['GET', 'HEAD'], $route->methods());
        self::assertContains('web', $route->gatherMiddleware());
        self::assertContains('auth', $route->gatherMiddleware());
        self::assertContains('can:NghiPhep.Insert', $route->gatherMiddleware());

        $matched = Route::getRoutes()->match(
            HttpRequest::create('/api/v1/nghi-phep/cua-toi', 'GET'),
        );
        self::assertSame($route->getName(), $matched->getName());
    }

    public function test_self_history_requires_insert_permission_and_rejects_actor_without_employee_code(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Xem,
        ]);

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getAll')->never();
        $this->app->instance(NghiPhepService::class, $service);

        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertForbidden();

        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Tao,
        ], ['ma_nv' => null]);

        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_self_history_rejects_noncanonical_employee_code_before_service(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Tao,
        ], ['ma_nv' => 'ABCDE']);

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getAll')->never();
        $this->app->instance(NghiPhepService::class, $service);

        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->actingAsEmployeeWithPermissions([
            NghiPhepPermission::Tao,
        ], ['ma_nv' => '1234']);

        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_guest_cannot_read_self_history(): void
    {
        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertUnauthorized();
    }
}
