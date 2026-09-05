<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienRemovalAction;
use App\Exceptions\NhanVienDomainException;
use App\Http\Controllers\Backend\NhanVienController;
use App\Services\NhanVienService;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienIndexTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
            \App\Enums\NhanVienPermission::Tao,
            \App\Enums\NhanVienPermission::Sua,
            \App\Enums\NhanVienPermission::Xoa,
        ]);

        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                $entries = is_array($entrypoints) ? $entrypoints : [$entrypoints];

                return new HtmlString(collect($entries)->map(
                    fn (string $entry): string => '<script type="module" src="/build/'.basename($entry).'"></script>',
                )->implode(''));
            }
        });
    }

    public function test_provider_binds_the_service_contract_to_the_concrete_service(): void
    {
        $this->assertInstanceOf(NhanVienService::class, $this->app->make(NhanVienServiceContract::class));
    }

    public function test_authenticated_index_normalizes_filters_and_renders_real_employee_data(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->with([
                'tu_khoa' => '00001',
                'ma_pb' => null,
                'ma_cv' => null,
                'ma_tt' => null,
                'page' => 1,
                'so_dong' => 20,
            ])->andReturn($this->employeePaginator());
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $response = $this->get('/nhan-vien?tu_khoa=00001&ma_pb=&ma_cv=&ma_tt=&page=&so_dong=');

        $response
            ->assertOk()
            ->assertViewIs('backend.nhanvien.index')
            ->assertViewHas('filters', [
                'tu_khoa' => '00001',
                'ma_pb' => null,
                'ma_cv' => null,
                'ma_tt' => null,
                'page' => 1,
                'so_dong' => 20,
            ])
            ->assertSee('00001')
            ->assertSee('name="tu_khoa"', false)
            ->assertSee('value="00001"', false)
            ->assertSee('Nguyễn An')
            ->assertSee('an@example.test')
            ->assertDontSee('mat_khau')
            ->assertDontSee('Thêm mới')
            ->assertSee('Chỉnh sửa')
            ->assertSee('Xóa hoặc kết thúc')
            ->assertSee('Thao tác')
            ->assertSee('Xem')
            ->assertSee('href="'.route('backend.nhanvien.create').'"', false)
            ->assertSee('/build/nhanvien.js', false)
            ->assertDontSee('Chưa khả dụng');

        $this->assertSame(1, substr_count($response->getContent(), '/build/nhanvien.js'));
    }

    public function test_index_shows_edit_and_delete_for_every_employee(): void
    {
        $employee = (array) $this->employeePaginator()->items()[0];
        $employee['ma_vt'] = 1;
        $employee['ten_vt'] = 'Quản trị Nhân sự';
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->andReturn($this->employeePaginator([$employee]));
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $editUrl = route('backend.nhanvien.edit', ['ma_nv' => '00001']);

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertSee('href="'.e($editUrl).'"', false)
            ->assertSee('Chỉnh sửa')
            ->assertSee('Xóa hoặc kết thúc')
            ->assertDontSee('Đặt lại mật khẩu');
    }

    public function test_index_uses_public_disk_url_for_canonical_relative_avatar_path(): void
    {
        config()->set('filesystems.disks.public.url', '/storage');
        $employee = $this->employeePaginator()->items()[0];
        $employee->anh_dai_dien = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->andReturn($this->employeePaginator([(array) $employee]));
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url($employee->anh_dai_dien).'"', false)
            ->assertSee('src="/storage/nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png"', false);
    }

    public function test_index_avatar_payload_cannot_create_an_external_origin_src(): void
    {
        config()->set('filesystems.disks.public.url', '/storage');
        $employees = collect(['https://evil.example/avatar.png', '//evil.example/avatar.png'])
            ->map(function (string $payload): array {
                $employee = (array) $this->employeePaginator()->items()[0];
                $employee['anh_dai_dien'] = $payload;

                return $employee;
            })->all();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employees): void {
            $mock->shouldReceive('paginate')->once()->andReturn($this->employeePaginator($employees));
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertDontSee('src="https://evil.example/avatar.png"', false)
            ->assertDontSee('src="//evil.example/avatar.png"', false)
            ->assertSee('src="/storage/https://evil.example/avatar.png"', false)
            ->assertSee('src="/storage/evil.example/avatar.png"', false);
    }

    public function test_authenticated_index_passes_integer_filters_and_keeps_them_in_pagination_links(): void
    {
        $filters = [
            'tu_khoa' => null,
            'ma_pb' => 2,
            'ma_cv' => 3,
            'ma_tt' => 1,
            'page' => 2,
            'so_dong' => 5,
        ];
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($filters): void {
            $mock->shouldReceive('paginate')->once()->with($filters)->andReturn(
                $this->employeePaginator([], 11, 5, 2),
            );
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $response = $this->get('/nhan-vien?ma_pb=2&ma_cv=3&ma_tt=1&page=2&so_dong=5');

        $response->assertOk()
            ->assertSee('ma_pb=2', false)
            ->assertSee('so_dong=5', false)
            ->assertSee('name="so_dong"', false)
            ->assertSee('option value="5" selected', false);
        $employees = $response->viewData('employees');
        $this->assertSame(route('backend.nhanvien.index'), $employees->path());
        $this->assertStringContainsString('ma_cv=3', $employees->url(3));
        $this->assertStringContainsString('ma_tt=1', $employees->url(3));
    }

    public function test_index_has_consistent_summary_and_permission_guarded_action_select(): void
    {
        $employee = (array) $this->employeePaginator()->items()[0];
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->andReturn($this->employeePaginator([$employee], 11, 5, 2));
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $this->get('/nhan-vien?page=2&so_dong=5')
            ->assertOk()
            ->assertSee('Hiển thị 6-6 / 11 nhân viên')
            ->assertSee('option value="5" selected', false)
            ->assertSee('data-row-action-select', false)
            ->assertSee('Xem')
            ->assertSee('Chỉnh sửa')
            ->assertSee('Xóa hoặc kết thúc')
            ->assertSee('Trang cuối');
    }

    public function test_index_renders_on_demand_edit_modal_trigger_with_progressive_fallback(): void
    {
        $employee = (array) $this->employeePaginator()->items()[0];
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->andReturn($this->employeePaginator([$employee]));
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $editUrl = route('backend.nhanvien.edit', ['ma_nv' => '00001']);

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertSee('data-employee-edit-modal', false)
            ->assertSee('data-action="modal"', false)
            ->assertSee('data-modal-url="'.e($editUrl).'"', false)
            ->assertSee('href="'.e($editUrl).'"', false)
            ->assertDontSee('data-employee-wizard', false);
    }

    public function test_index_does_not_render_edit_modal_without_update_permission(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);
        $employee = (array) $this->employeePaginator()->items()[0];
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->andReturn($this->employeePaginator([$employee]));
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertDontSee('data-employee-edit-modal', false)
            ->assertDontSee('data-action="modal"', false)
            ->assertDontSee('Chỉnh sửa');
    }

    public function test_invalid_filters_do_not_call_the_service(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('lookups');
        });

        $this->from('/nhan-vien')
            ->get('/nhan-vien?tu_khoa='.str_repeat('x', 101).'&ma_pb=0&ma_cv=x&ma_tt=-1&page=0&so_dong=25')
            ->assertRedirect('/nhan-vien')
            ->assertSessionHasErrors(['tu_khoa', 'ma_pb', 'ma_cv', 'ma_tt', 'page', 'so_dong']);
    }

    public function test_database_empty_and_filter_empty_are_distinct_states(): void
    {
        $service = $this->mock(NhanVienServiceContract::class);
        $service->shouldReceive('paginate')->twice()->andReturn(
            $this->employeePaginator([], 0),
            $this->employeePaginator([], 0),
        );
        $service->shouldReceive('lookups')->twice()->andReturn($this->employeeLookups());

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertSee('Chưa có nhân viên trong hệ thống')
            ->assertDontSee('Không tìm thấy nhân viên phù hợp');

        $this->get('/nhan-vien?tu_khoa=99999')
            ->assertOk()
            ->assertSee('Không tìm thấy nhân viên phù hợp')
            ->assertDontSee('Chưa có nhân viên trong hệ thống');
    }

    public function test_empty_current_page_with_existing_results_has_a_truthful_distinct_state(): void
    {
        $service = $this->mock(NhanVienServiceContract::class);
        $service->shouldReceive('paginate')->twice()->andReturn(
            $this->employeePaginator([], 11, 20, 999),
            $this->employeePaginator([], 11, 20, 999),
        );
        $service->shouldReceive('lookups')->twice()->andReturn($this->employeeLookups());

        foreach ([
            '/nhan-vien?page=999',
            '/nhan-vien?tu_khoa=00001&page=999',
        ] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('Trang kết quả hiện tại không có dữ liệu')
                ->assertSee('11 nhân viên')
                ->assertDontSee('Chưa có nhân viên trong hệ thống')
                ->assertDontSee('Không tìm thấy nhân viên phù hợp');
        }
    }

    public function test_domain_exception_is_rendered_as_a_safe_error_state(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->andThrow(
                new NhanVienDomainException(
                    'Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.',
                    'NV_DATABASE_ERROR',
                ),
            );
            $mock->shouldNotReceive('lookups');
        });

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertSee('Không thể tải danh sách nhân viên lúc này. Vui lòng thử lại sau.')
            ->assertDontSee('NV_DATABASE_ERROR')
            ->assertDontSee('SQLSTATE');
    }

    public function test_flash_success_and_accessible_filter_table_markup_are_present(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->andReturn($this->employeePaginator());
            $mock->shouldReceive('lookups')->once()->andReturn($this->employeeLookups());
        });

        $this->withSession(['success' => 'Cập nhật nhân viên thành công.'])
            ->get('/nhan-vien')
            ->assertOk()
            ->assertSee('Cập nhật nhân viên thành công.')
            ->assertSee('<caption', false)
            ->assertSee('for="tu_khoa"', false)
            ->assertSee('aria-busy="false"', false)
            ->assertSee('aria-disabled="false"', false)
            ->assertSee('data-disable-on-submit', false)
            ->assertSee('data-submitting-text', false)
            ->assertSee('table-responsive', false);
    }

    public function test_legacy_redirect_is_protected_and_preserves_the_query_string(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('lookups');
        });

        $this->get('/admin/nhan-vien/danh-sach-nhan-vien?tu_khoa=00001&so_dong=50')
            ->assertStatus(301)
            ->assertRedirect('/nhan-vien?tu_khoa=00001&so_dong=50');
    }

    public function test_route_inventory_contains_the_canonical_index_show_and_protected_legacy_redirect(): void
    {
        $employeeRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'nhan-vien'))
            ->values();

        $this->assertSame(
            [
                'nhan-vien',
                'nhan-vien/create',
                'nhan-vien',
                'nhan-vien/{ma_nv}/edit',
                'nhan-vien/{ma_nv}',
                'nhan-vien/{ma_nv}/reset-mat-khau',
                'nhan-vien/{ma_nv}',
                'nhan-vien/{ma_nv}',
            ],
            $employeeRoutes->pluck('uri')->all(),
        );
        $this->assertSame(1, $employeeRoutes->where('action.as', 'backend.nhanvien.index')->count());
        $this->assertNull($employeeRoutes->firstWhere('uri', 'nhan-vien/danh-sach-nhan-vien'));
        $createRoute = Route::getRoutes()->getByName('backend.nhanvien.create');
        $this->assertInstanceOf(RoutingRoute::class, $createRoute);
        $this->assertSame('nhan-vien/create', $createRoute->uri());
        $this->assertSame(NhanVienController::class.'@create', $createRoute->getActionName());
        $storeRoute = Route::getRoutes()->getByName('backend.nhanvien.store');
        $this->assertInstanceOf(RoutingRoute::class, $storeRoute);
        $this->assertSame(['POST'], $storeRoute->methods());
        $this->assertSame(NhanVienController::class.'@store', $storeRoute->getActionName());
        $editRoute = Route::getRoutes()->getByName('backend.nhanvien.edit');
        $this->assertInstanceOf(RoutingRoute::class, $editRoute);
        $this->assertSame(['GET', 'HEAD'], $editRoute->methods());
        $this->assertSame(NhanVienController::class.'@edit', $editRoute->getActionName());
        $updateRoute = Route::getRoutes()->getByName('backend.nhanvien.update');
        $this->assertInstanceOf(RoutingRoute::class, $updateRoute);
        $this->assertSame(['PUT', 'PATCH'], $updateRoute->methods());
        $this->assertSame(NhanVienController::class.'@update', $updateRoute->getActionName());

        $destroyRoute = Route::getRoutes()->getByName('backend.nhanvien.destroy');
        $this->assertInstanceOf(RoutingRoute::class, $destroyRoute);
        $this->assertSame(['DELETE'], $destroyRoute->methods());
        $this->assertSame(
            NhanVienController::class.'@destroy',
            $destroyRoute->getActionName(),
        );
        $this->assertSame('[0-9]{5}', $destroyRoute->wheres['ma_nv']);

        $resetRoute = Route::getRoutes()->getByName('backend.nhanvien.reset-password');
        $this->assertInstanceOf(RoutingRoute::class, $resetRoute);
        $this->assertSame('nhan-vien/{ma_nv}/reset-mat-khau', $resetRoute->uri());
        $this->assertSame(['POST'], $resetRoute->methods());
        $this->assertSame(NhanVienController::class.'@resetPassword', $resetRoute->getActionName());
        $this->assertContains('auth', $resetRoute->gatherMiddleware());
        $this->assertContains('can:NhanVien.ResetPassword', $resetRoute->gatherMiddleware());

        $showRoute = Route::getRoutes()->getByName('backend.nhanvien.show');
        $this->assertInstanceOf(RoutingRoute::class, $showRoute);
        $this->assertSame('nhan-vien/{ma_nv}', $showRoute->uri());
        $this->assertSame(NhanVienController::class.'@show', $showRoute->getActionName());
        $this->assertSame('[0-9]{5}', $showRoute->wheres['ma_nv']);
        $this->assertLessThan(
            array_search($showRoute, Route::getRoutes()->getRoutes(), true),
            array_search($destroyRoute, Route::getRoutes()->getRoutes(), true),
        );

        $this->assertSame(4, $employeeRoutes->filter(
            fn (RoutingRoute $route): bool => $route->methods() === ['GET', 'HEAD'],
        )->count());
    }

    public function test_lifecycle_delete_dispatches_through_public_service_contract(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('lookups');
            $mock->shouldReceive('removeOrTerminate')->once()->with('00001')->andReturn(
                NhanVienRemovalAction::Deleted,
            );
        });

        $this->get('/admin/nhan-vien/them-nhan-vien')->assertStatus(301);
        $this->get('/nhan-vien/00001/sua')->assertNotFound();
        $this->delete('/nhan-vien/00001')
            ->assertRedirect(route('backend.nhanvien.index'))
            ->assertSessionHas('success', 'Đã xóa hồ sơ nhân viên.');
    }

    private function employeePaginator(
        array $items = [],
        ?int $total = null,
        int $perPage = 20,
        int $page = 1,
    ): LengthAwarePaginator {
        if ($items === [] && $total === null) {
            $items = [[
                'ma_nv' => '00001',
                'ho_ten' => 'Nguyễn An',
                'sdt' => '0900000001',
                'email' => 'an@example.test',
                'ngay_vao_lam' => '2020-01-01',
                'anh_dai_dien' => null,
                'ma_pb' => 1,
                'ten_pb' => 'Kỹ thuật',
                'ma_cv' => 1,
                'ten_cv' => 'Lập trình viên',
                'ma_tt' => 1,
                'ma_vt' => 5,
                'ma_tt' => 2,
                'ten_tt' => 'Đang làm',
            ]];
        }

        return new LengthAwarePaginator(
            collect($items)->map(fn (array $item): object => (object) $item),
            $total ?? count($items),
            $perPage,
            $page,
            ['pageName' => 'page'],
        );
    }

    private function employeeLookups(): array
    {
        return [
            'phong_ban' => [(object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 1]],
            'chuc_vu' => [(object) ['ma_cv' => 1, 'ten_cv' => 'Lập trình viên', 'he_so_phu_cap' => '0.20']],
            'trang_thai' => [(object) ['ma_tt' => 1, 'ten_tt' => 'Thử việc'], (object) ['ma_tt' => 2, 'ten_tt' => 'Đang làm']],
        ];
    }
}
