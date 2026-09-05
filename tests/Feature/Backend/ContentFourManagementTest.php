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
use App\Enums\VaiTroPermission;
use App\Enums\NghiPhepPermission;
use App\Enums\ChamCongPermission;
use App\Enums\LuongPermission;
use App\Repositories\HopDongRepository;
use App\Repositories\PhanQuyenRepository;
use App\Repositories\VaiTroRepository;
use App\Services\HopDongService;
use App\Services\PhanQuyenService;
use App\Services\PermissionService;
use App\Services\VaiTroService;
use App\Contracts\PermissionDefinitionContract;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ContentFourManagementTest extends TestCase
{
    public function test_permissions_are_registered_with_canonical_ids_and_warning_window(): void
    {
        $registry = app(PermissionRegistry::class);
        $this->assertSame([21, 22, 23, 24], array_map(fn ($permission) => $permission->id(), HopDongPermission::cases()));
        $this->assertSame([5, 6, 7, 8], array_map(fn ($permission) => $permission->id(), PhanQuyenPermission::cases()));
        $this->assertSame([25, 26, 27, 28], array_map(fn ($permission) => $permission->id(), NghiPhepPermission::cases()));
        $this->assertSame([29, 30, 31, 32], array_map(fn ($permission) => $permission->id(), ChamCongPermission::cases()));
        $this->assertSame([33, 34, 35, 36], array_map(fn ($permission) => $permission->id(), LuongPermission::cases()));
        $this->assertSame(21, $registry->forAbility('HopDong.Read')?->id());
        $this->assertSame(3, $registry->forAbility('VaiTro.Update')?->id());
        $this->assertSame(25, $registry->forAbility('NghiPhep.Read')?->id());
        $this->assertSame(29, $registry->forAbility('ChamCong.Read')?->id());
        $this->assertSame(33, $registry->forAbility('Luong.Read')?->id());
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
            'backend.hopdong.index' => 'can:HopDong.Read', 'backend.hopdong.store' => 'can:HopDong.Insert',
            'backend.hopdong.update' => 'can:HopDong.Update', 'backend.hopdong.destroy' => 'can:HopDong.Delete',
            'backend.vaitro.index' => 'can:VaiTro.Read', 'backend.vaitro.store' => 'can:VaiTro.Insert',
            'backend.vaitro.permissions.update' => 'can:PhanQuyen.Update',
            'backend.taikhoan.index' => 'can:PhanQuyen.Read', 'backend.taikhoan.assign-roles' => 'can:PhanQuyen.Update',
        ];

        foreach ($expected as $name => $permission) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertInstanceOf(RoutingRoute::class, $route, $name);
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
            $this->assertContains($permission, $route->gatherMiddleware(), $name);
        }
    }

    public function test_work_module_routes_use_their_own_exact_permission_catalog(): void
    {
        $expected = [
            'backend.tongquan.index' => ['auth'],
            'backend.chamcong.index' => ['auth', 'can:ChamCong.Read'],
            'backend.nghiphep.index' => ['auth', 'can:NghiPhep.Read'],
            'backend.luong.index' => ['auth', 'can:Luong.Read'],
            'api.v1.cham-cong.nhan-vien' => ['auth', 'can:ChamCong.Read'],
            'api.v1.cham-cong.phong-ban' => ['auth', 'can:ChamCong.Read'],
            'cham-cong.index' => ['auth', 'can:ChamCong.Read'],
            'cham-cong.update' => ['auth', 'can:ChamCong.Update'],
            'api.v1.nghi-phep.nhan-vien' => ['auth', 'can:NghiPhep.Read'],
            'api.v1.nghi-phep.phong-ban' => ['auth', 'can:NghiPhep.Read'],
            'api.v1.nghi-phep.chuc-vu' => ['auth', 'can:NghiPhep.Read'],
            'api.v1.nghi-phep.loai-phep' => ['auth', 'can:NghiPhep.Read'],
            'nghi-phep.index' => ['auth', 'can:NghiPhep.Read'],
            'nghi-phep.show' => ['auth', 'can:NghiPhep.Read'],
            'nghi-phep.store' => ['auth', 'can:NghiPhep.Insert'],
            'nghi-phep.update' => ['auth', 'can:NghiPhep.Update'],
            'nghi-phep.destroy' => ['auth', 'can:NghiPhep.Delete'],
            'api.v1.nghi-phep.duyet' => ['auth', 'can:NghiPhep.Update'],
            'luong.index' => ['auth', 'can:Luong.Read'],
            'luong.show' => ['auth', 'can:Luong.Read'],
            'luong.store' => ['auth', 'can:Luong.Insert'],
            'luong.update' => ['auth', 'can:Luong.Update'],
            'luong.destroy' => ['auth', 'can:Luong.Delete'],
            'api.v1.luong.he-so-luong' => ['auth', 'can:Luong.Read'],
            'api.v1.luong.he-so-luong.store' => ['auth', 'can:Luong.Insert'],
            'api.v1.luong.he-so-luong.show' => ['auth', 'can:Luong.Read'],
            'api.v1.luong.he-so-luong.update' => ['auth', 'can:Luong.Update'],
        ];

        foreach ($expected as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(RoutingRoute::class, $route, $name);

            foreach ($middleware as $item) {
                $this->assertContains($item, $route->gatherMiddleware(), $name);
            }
        }
    }

    public function test_role_view_exposes_independent_action_flags_and_permission_link_gate(): void
    {
        $employee = \App\Models\NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
        ]);
        $this->actingAs($employee);
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('canSeeModule')->andReturnFalse();
            $mock->shouldReceive('allows')->andReturnUsing(
                static function (mixed $candidate, PermissionDefinitionContract|string $permission): bool {
                    $symbol = $permission instanceof PermissionDefinitionContract
                        ? $permission->symbol()
                        : $permission;

                    return in_array($symbol, [VaiTroPermission::Tao->value, VaiTroPermission::Xoa->value], true);
                },
            );
        });

        $this->view('backend.vaitro.index')
            ->assertSee('data-role-can-create="1"', false)
            ->assertSee('data-role-can-edit="0"', false)
            ->assertSee('data-role-can-delete="1"', false)
            ->assertSee('data-role-can-permission="0"', false)
            ->assertSee('data-role-create', false);
    }

    public function test_sidebar_shows_only_the_role_link_for_vai_tro_read(): void
    {
        $employee = \App\Models\NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
        ]);
        $this->actingAs($employee);
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('canSeeModule')
                ->andReturnUsing(static fn (mixed $candidate, string $module): bool => $module === 'VaiTro');
        });

        $this->view('backend.layouts.sidebar')
            ->assertSee('Danh sách vai trò')
            ->assertDontSee('Gán vai trò tài khoản');
    }

    public function test_sidebar_shows_only_the_permission_link_for_phan_quyen_read(): void
    {
        $employee = \App\Models\NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
        ]);
        $this->actingAs($employee);
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('canSeeModule')
                ->andReturnUsing(static fn (mixed $candidate, string $module): bool => $module === 'PhanQuyen');
        });

        $this->view('backend.layouts.sidebar')
            ->assertDontSee('Danh sách vai trò')
            ->assertSee('Phân Quyền');
    }
}
