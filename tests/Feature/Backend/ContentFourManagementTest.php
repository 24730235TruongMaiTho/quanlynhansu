<?php

namespace Tests\Feature\Backend;

use App\Authorization\PermissionRegistry;
use App\Contracts\HopDongRepositoryContract;
use App\Contracts\HopDongServiceContract;
use App\Contracts\PhanQuyenRepositoryContract;
use App\Contracts\PhanQuyenServiceContract;
use App\Contracts\VaiTroRepositoryContract;
use App\Contracts\VaiTroServiceContract;
use App\Enums\HopDongPermission;
use App\Enums\PhanQuyenPermission;
use App\Repositories\HopDongRepository;
use App\Repositories\PhanQuyenRepository;
use App\Repositories\VaiTroRepository;
use App\Services\HopDongService;
use App\Services\PhanQuyenService;
use App\Services\VaiTroService;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ContentFourManagementTest extends TestCase
{
    public function test_permissions_are_registered_with_canonical_ids_and_warning_window(): void
    {
        $registry = app(PermissionRegistry::class);
        $this->assertSame([401, 402, 403, 404], array_map(fn ($permission) => $permission->id(), HopDongPermission::cases()));
        $this->assertSame([801, 802], array_map(fn ($permission) => $permission->id(), PhanQuyenPermission::cases()));
        $this->assertSame(401, $registry->forAbility('HD_VIEW')?->id());
        $this->assertSame(802, $registry->forAbility('PQ_ROLE_MANAGE')?->id());
        $this->assertSame(30, config('hopdong.expiring_warning_days'));
    }

    public function test_dependencies_resolve_to_the_expected_implementations(): void
    {
        $this->assertInstanceOf(HopDongRepository::class, app(HopDongRepositoryContract::class));
        $this->assertInstanceOf(HopDongService::class, app(HopDongServiceContract::class));
        $this->assertInstanceOf(VaiTroRepository::class, app(VaiTroRepositoryContract::class));
        $this->assertInstanceOf(VaiTroService::class, app(VaiTroServiceContract::class));
        $this->assertInstanceOf(PhanQuyenRepository::class, app(PhanQuyenRepositoryContract::class));
        $this->assertInstanceOf(PhanQuyenService::class, app(PhanQuyenServiceContract::class));
    }

    public function test_routes_are_guarded_by_auth_and_exact_permissions(): void
    {
        $expected = [
            'backend.hopdong.index' => 'can:HD_VIEW', 'backend.hopdong.store' => 'can:HD_CREATE',
            'backend.hopdong.update' => 'can:HD_EDIT', 'backend.hopdong.destroy' => 'can:HD_DELETE',
            'backend.vaitro.index' => 'can:PQ_ROLE_VIEW', 'backend.vaitro.store' => 'can:PQ_ROLE_MANAGE',
            'backend.vaitro.permissions.update' => 'can:PQ_ROLE_MANAGE',
            'backend.taikhoan.index' => 'can:PQ_ROLE_VIEW', 'backend.taikhoan.assign-role' => 'can:PQ_ROLE_MANAGE',
        ];

        foreach ($expected as $name => $permission) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertInstanceOf(RoutingRoute::class, $route, $name);
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
            $this->assertContains($permission, $route->gatherMiddleware(), $name);
        }
    }
}
