<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicCrudRouteTest extends TestCase
{
    public function test_root_redirects_to_employee_index_without_login(): void
    {
        $this->get('/')->assertRedirect(route('backend.nhanvien.index'));
    }

    public function test_login_logout_and_reset_password_routes_are_absent(): void
    {
        $this->get('/dang-nhap')->assertNotFound();
        $this->post('/dang-xuat')->assertNotFound();
        $this->patch('/admin/nhan-vien/NV001/dat-lai-mat-khau')->assertNotFound();
    }

    public function test_three_crud_modules_are_public(): void
    {
        $routeNames = [
            'backend.nhanvien.index',
            'backend.nhanvien.create',
            'backend.nhanvien.store',
            'backend.nhanvien.show',
            'backend.nhanvien.edit',
            'backend.nhanvien.update',
            'backend.nhanvien.destroy',
            'backend.phongban.index',
            'backend.phongban.create',
            'backend.phongban.store',
            'backend.phongban.edit',
            'backend.phongban.update',
            'backend.phongban.destroy',
            'backend.chucvu.index',
            'backend.chucvu.create',
            'backend.chucvu.store',
            'backend.chucvu.edit',
            'backend.chucvu.update',
            'backend.chucvu.destroy',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertInstanceOf(RoutingRoute::class, $route, "Thiếu route {$routeName}.");
            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth', $middleware, "Route {$routeName} vẫn yêu cầu auth.");
            $this->assertSame(
                [],
                array_filter(
                    $middleware,
                    static fn (mixed $item): bool => str_starts_with((string) $item, 'can:'),
                ),
                "Route {$routeName} vẫn có middleware can:.",
            );
        }
    }
}
