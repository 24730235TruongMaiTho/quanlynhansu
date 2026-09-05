<?php

namespace Tests\Feature\Backend\PhanQuyen;

use App\Contracts\PhanQuyenServiceContract;
use App\Contracts\VaiTroServiceContract;
use App\Models\NhanVien;
use App\Services\PermissionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use ReflectionMethod;
use Tests\TestCase;

final class AccountPermissionBulkTest extends TestCase
{
    public function test_account_routes_use_bulk_patch_contract_and_remove_row_mutation_route(): void
    {
        $bulk = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'backend.taikhoan.assign-roles');

        self::assertNotNull($bulk);
        self::assertSame('tai-khoan/vai-tro', $bulk->uri());
        self::assertSame(['PATCH'], $bulk->methods());
        self::assertContains('auth', $bulk->middleware());
        self::assertContains('can:PhanQuyen.Update', $bulk->middleware());
        self::assertNull(collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'backend.taikhoan.assign-role'));
    }

    public function test_public_contracts_expose_paginator_and_bulk_assignment(): void
    {
        $accounts = new ReflectionMethod(PhanQuyenServiceContract::class, 'accounts');
        self::assertSame(['array'], array_map(
            static fn ($parameter): string => (string) $parameter->getType(),
            $accounts->getParameters(),
        ));
        self::assertSame(LengthAwarePaginator::class, (string) $accounts->getReturnType());

        $assign = new ReflectionMethod(PhanQuyenServiceContract::class, 'assignRoles');
        self::assertSame(['array', 'string'], array_map(
            static fn ($parameter): string => (string) $parameter->getType(),
            $assign->getParameters(),
        ));
        self::assertSame('void', (string) $assign->getReturnType());
    }

    public function test_bulk_request_rejects_malformed_target_and_non_assignment_body(): void
    {
        $response = $this->withoutMiddleware()->patchJson('/tai-khoan/vai-tro', [
            'assignments' => ['not-an-employee' => 2],
            'ma_nv' => '00001',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['assignments', 'ma_nv']);
    }

    public function test_controller_calls_bulk_service_once_with_authenticated_actor_and_redirects(): void
    {
        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00999', 'ho_ten' => 'Quản trị', 'email' => 'admin@example.test',
            'mat_khau' => 'hash', 'ma_vt' => 1, 'ma_tt' => 1,
        ]);
        $this->actingAs($actor)->withoutMiddleware();
        $service = $this->mock(PhanQuyenServiceContract::class);
        $service->shouldReceive('assignRoles')->once()->with(['00001' => 2], '00999');

        $this->patch('/tai-khoan/vai-tro?tu_khoa=An&page=2&per_page=20', [
            'assignments' => ['00001' => 2],
        ])->assertRedirect('/tai-khoan?tu_khoa=An&page=2&per_page=20')
            ->assertSessionHas('success', 'Đã cập nhật phân quyền tài khoản.');
    }

    public function test_account_index_passes_allowlisted_filters_and_preserves_them_in_pagination(): void
    {
        $this->withoutMiddleware();
        $this->withViewErrors([]);
        $this->actingAs(NhanVien::fromAuthRow((object) [
            'ma_nv' => '00999',
            'ho_ten' => 'Quản trị',
            'email' => 'admin@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
        ]));
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('allows')->andReturnFalse();
            $mock->shouldReceive('canSeeModule')->andReturnFalse();
        });
        $paginator = new LengthAwarePaginator(
            [(object) [
                'ma_nv' => '00001',
                'ho_ten' => 'Nguyễn An',
                'email' => 'an@example.test',
                'ma_vt' => 2,
                'ten_vt' => 'Nhân sự',
            ]],
            21,
            20,
            2,
            ['path' => '/tai-khoan', 'pageName' => 'page'],
        );
        $this->mock(PhanQuyenServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('accounts')->once()->with([
                'tu_khoa' => 'An',
                'page' => 2,
                'per_page' => 20,
            ])->andReturn($paginator);
        });
        $this->mock(VaiTroServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->once()->andReturn([]);
        });

        $this->get('/tai-khoan?tu_khoa=An&page=2&per_page=20&unexpected=ignored')
            ->assertOk()
            ->assertSee('tu_khoa=An', false)
            ->assertSee('per_page=20', false)
            ->assertSee('action="http://127.0.0.1:8000/tai-khoan/vai-tro?tu_khoa=An&amp;page=2&amp;per_page=20"', false)
            ->assertDontSee('name="assignments[00001]"', false)
            ->assertSee('Nhân sự')
            ->assertDontSee('unexpected=ignored', false);
    }

    public function test_account_index_normalizes_invalid_page_size_to_ten(): void
    {
        $this->withoutMiddleware();
        $this->withViewErrors([]);
        $this->actingAs(NhanVien::fromAuthRow((object) [
            'ma_nv' => '00999',
            'ho_ten' => 'Quản trị',
            'email' => 'admin@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
        ]));
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('allows')->andReturnFalse();
            $mock->shouldReceive('canSeeModule')->andReturnFalse();
        });
        $paginator = new LengthAwarePaginator([], 11, 10, 1, ['path' => '/tai-khoan', 'pageName' => 'page']);
        $this->mock(PhanQuyenServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('accounts')->once()->with([
                'tu_khoa' => null,
                'page' => 1,
                'per_page' => 10,
            ])->andReturn($paginator);
        });
        $this->mock(VaiTroServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->once()->andReturn([]);
        });

        $this->get('/tai-khoan?per_page=5&unexpected=ignored')
            ->assertOk()
            ->assertSee('per_page=10', false)
            ->assertDontSee('per_page=5', false)
            ->assertDontSee('unexpected=ignored', false);
    }

    public function test_account_page_has_one_bulk_form_and_uses_requested_labels(): void
    {
        $page = File::get(resource_path('views/backend/taikhoan/index.blade.php'));
        $sidebar = File::get(resource_path('views/backend/layouts/sidebar.blade.php'));
        $permissionPage = File::get(resource_path('views/backend/vaitro/permissions.blade.php'));

        self::assertStringContainsString('Phân Quyền', $page);
        self::assertStringNotContainsString('Gán vai trò', $page);
        self::assertSame(1, substr_count($page, "route('backend.taikhoan.assign-roles'"));
        self::assertStringContainsString('assignments[', $page);
        self::assertStringContainsString('Áp dụng bộ lọc', $page);
        self::assertStringContainsString('Đặt lại', $page);
        self::assertStringContainsString('Lưu phân quyền', $page);
        self::assertStringNotContainsString('Gán vai trò tài khoản', $sidebar);
        self::assertStringContainsString('Phân Quyền', $sidebar);
        self::assertStringNotContainsString('ky_hieu_quyen', $permissionPage);
        self::assertStringNotContainsString('{{ $permission->ky_hieu_quyen }}', $permissionPage);
    }
}
