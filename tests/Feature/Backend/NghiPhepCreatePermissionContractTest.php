<?php

namespace Tests\Feature\Backend;

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

    public function test_authenticated_actor_can_use_create_lookups_but_not_read_lookups(): void
    {
        $this->actingAsEmployeeWithPermissions([]);
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

    public function test_create_lookup_routes_are_auth_only_and_have_explicit_names(): void
    {
        foreach ([
            'api.v1.nghi-phep.tao.loai-phep',
            'api.v1.nghi-phep.tao.phong-ban',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            self::assertNotNull($route, $name);
            self::assertContains('auth', $route->gatherMiddleware(), $name);
            self::assertNotContains('can:NghiPhep.Insert', $route->gatherMiddleware(), $name);
        }
    }

    public function test_authenticated_actor_sees_self_leave_link_without_management_leave_list_link(): void
    {
        $this->actingAsEmployeeWithPermissions([]);

        $this->view('backend.layouts.sidebar')
            ->assertSee('Thông Tin Cá Nhân')
            ->assertSee('Đơn nghỉ phép')
            ->assertDontSee('Đơn nghỉ phép của tôi')
            ->assertDontSee('Danh sách nghỉ phép');
    }

    public function test_leave_create_page_does_not_render_a_back_action(): void
    {
        $source = file_get_contents(base_path('resources/views/backend/nghiphep/create.blade.php'));

        self::assertIsString($source);
        self::assertStringNotContainsString('Quay lại', $source);
        self::assertStringNotContainsString('<x-slot:actions>', $source);
    }

    public function test_self_history_rejects_a_client_employee_selector(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00999']);

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getAll')->never();
        $this->app->instance(NghiPhepService::class, $service);

        $this->getJson('/api/v1/nghi-phep/cua-toi?ma_nv=00001&tab=history&per_page=50')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ma_nv');

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
        self::assertNotContains('can:NghiPhep.Insert', $route->gatherMiddleware());

        $matched = Route::getRoutes()->match(
            HttpRequest::create('/api/v1/nghi-phep/cua-toi', 'GET'),
        );
        self::assertSame($route->getName(), $matched->getName());
    }

    public function test_self_history_rejects_actor_without_employee_code_without_insert_permission(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => null]);

        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertForbidden();
    }

    public function test_self_history_rejects_noncanonical_employee_code_before_service(): void
    {
        $this->actingAsEmployeeWithPermissions([
        ], ['ma_nv' => 'ABCDE']);

        $service = Mockery::mock(NghiPhepService::class);
        $service->shouldReceive('getAll')->never();
        $this->app->instance(NghiPhepService::class, $service);

        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertForbidden();

        $this->actingAsEmployeeWithPermissions([
        ], ['ma_nv' => '1234']);

        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertForbidden();
    }

    public function test_guest_cannot_read_self_history(): void
    {
        $this->getJson('/api/v1/nghi-phep/cua-toi')
            ->assertUnauthorized();
    }
}
