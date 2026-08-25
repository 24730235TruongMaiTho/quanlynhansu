<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienPermission;
use App\Enums\NhanVienRole;
use App\Models\NhanVien;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienDepartmentScopeTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_department_manager_index_forces_actor_department_and_limits_lookup(): void
    {
        $this->departmentManager([NhanVienPermission::Xem]);
        $employee = $this->employee(['ma_pb' => 3, 'ten_pb' => 'Phòng 3']);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('paginate')->once()->withArgs(function (array $filters): bool {
                return $filters['ma_pb'] === 3;
            })->andReturn($this->paginator([$employee]));
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookupsWithDepartments());
        });

        $this->get('/admin/nhan-vien?ma_pb=4')
            ->assertOk()
            ->assertSee('Danh sách nhân viên được giới hạn theo phòng ban của bạn.')
            ->assertSee('ma_pb=3', false)
            ->assertSee('Phòng 3')
            ->assertDontSee('Phòng 4');
    }

    public function test_department_manager_can_show_same_department_but_cross_department_is_generic_404(): void
    {
        $this->departmentManager([NhanVienPermission::Xem]);
        $sameDepartment = $this->employee(['ma_pb' => 3, 'ten_pb' => 'Phòng 3']);
        $otherDepartment = $this->employee(['ma_pb' => 4, 'ten_pb' => 'Phòng 4']);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('findOrFail')->twice()->with('NV004')->andReturn($sameDepartment, $otherDepartment);
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->get('/admin/nhan-vien/NV004')->assertOk();
        $this->get('/admin/nhan-vien/NV004')
            ->assertNotFound()
            ->assertDontSee('Phòng 4')
            ->assertDontSee('NV004');
    }

    public function test_department_manager_cross_department_edit_is_404_before_rendering_form(): void
    {
        $this->departmentManager([NhanVienPermission::Sua]);
        $target = $this->employee(['ma_pb' => 4]);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('findOrFail')->once()->with('NV005')->andReturn($target);
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->get('/admin/nhan-vien/NV005/edit')->assertNotFound();
    }

    public function test_department_manager_same_department_edit_only_renders_own_department_lookup(): void
    {
        $this->departmentManager([NhanVienPermission::Sua]);
        $target = $this->employee(['ma_pb' => 3, 'ten_pb' => 'Phòng 3']);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('findOrFail')->once()->with('NV004')->andReturn($target);
        $service->shouldReceive('lookups')->once()->andReturn($this->lookupsWithDepartments());
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->get('/admin/nhan-vien/NV004/edit')
            ->assertOk()
            ->assertSee('Phòng 3')
            ->assertDontSee('Phòng 4');
    }

    public function test_department_manager_cross_department_update_is_404_before_service_mutation(): void
    {
        $this->departmentManager([NhanVienPermission::Sua]);
        $target = $this->employee(['ma_pb' => 4]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('NV005')->andReturn($target);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('update');
        });

        $this->put('/admin/nhan-vien/NV005', $this->validPayload(['ma_pb' => 4]))
            ->assertNotFound();
    }

    public function test_department_manager_cross_department_delete_and_reset_are_404_before_mutation(): void
    {
        $this->departmentManager([
            NhanVienPermission::Xoa,
            NhanVienPermission::DatLaiMatKhau,
        ]);
        $target = $this->employee(['ma_pb' => 4]);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('findOrFail')->twice()->with('NV005')->andReturn($target);
        $service->shouldNotReceive('removeOrTerminate');
        $service->shouldNotReceive('resetPassword');
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->delete('/admin/nhan-vien/NV005')->assertNotFound();
        $this->patch('/admin/nhan-vien/NV005/dat-lai-mat-khau')->assertNotFound();
    }

    public function test_department_manager_with_missing_department_fails_closed_with_empty_scope(): void
    {
        $actor = $this->departmentManager([NhanVienPermission::Xem]);
        $actor->forceFill(['ma_pb' => null]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->withArgs(fn (array $filters): bool => $filters['ma_pb'] === 0)
                ->andReturn($this->paginator());
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookupsWithDepartments());
        });

        $this->get('/admin/nhan-vien')
            ->assertOk()
            ->assertSee('Danh sách nhân viên được giới hạn theo phòng ban của bạn.')
            ->assertDontSee('Phòng 3')
            ->assertDontSee('Phòng 4');
    }

    #[DataProvider('unscopedRoles')]
    public function test_non_department_manager_roles_keep_cross_department_filter(int $role): void
    {
        $actor = $this->actingAsEmployeeWithPermissions([NhanVienPermission::Xem]);
        $actor->forceFill(['ma_vt' => $role, 'ma_pb' => 3]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->withArgs(fn (array $filters): bool => $filters['ma_pb'] === 4)
                ->andReturn($this->paginator());
            $mock->shouldReceive('lookups')->once()->andReturn($this->lookupsWithDepartments());
        });

        $this->get('/admin/nhan-vien?ma_pb=4')->assertOk();
    }

    public static function unscopedRoles(): array
    {
        return [
            'super admin' => [NhanVienRole::SuperAdmin->value],
            'human resources' => [NhanVienRole::HumanResources->value],
            'cbl admin' => [NhanVienRole::CblAdmin->value],
            'employee' => [NhanVienRole::Employee->value],
        ];
    }

    private function departmentManager(array $permissions): NhanVien
    {
        $actor = $this->actingAsEmployeeWithPermissions($permissions);
        $actor->forceFill(['ma_vt' => NhanVienRole::DepartmentManager->value, 'ma_pb' => 3]);

        return $actor;
    }

    private function paginator(array $items = []): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, count($items), 20, 1, [
            'path' => '/admin/nhan-vien',
        ]);
    }

    private function employee(array $overrides = []): object
    {
        return (object) array_replace([
            'ma_nv' => 'NV004', 'ho_ten' => 'Nguyễn An', 'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1, 'sdt' => '0901234567', 'email' => 'an@example.test',
            'ngay_vao_lam' => '2020-01-01', 'ma_pb' => 3, 'ten_pb' => 'Phòng 3',
            'ma_cv' => 1, 'ten_cv' => 'Lập trình viên', 'dan_toc' => 'Kinh',
            'cccd' => '001200000001', 'noi_cap_cccd' => 'Cục CSQLHC', 'hoc_van' => 'Đại học',
            'ma_tt' => 2, 'ten_tt' => 'Đang làm việc', 'ngay_nghi_viec' => null,
            'ma_vt' => 5, 'ten_vt' => 'Nhân viên', 'anh_dai_dien' => null,
            'dia_chi_cu_the' => '1 Nguyễn Trãi', 'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1', 'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'ho_ten' => 'Nguyễn An', 'ngay_sinh' => '1990-01-01', 'gioi_tinh' => 1,
            'sdt' => '0901234567', 'email' => 'updated@example.test',
            'ngay_vao_lam' => '2020-01-01', 'ma_pb' => 4, 'ma_cv' => 1,
            'dan_toc' => 'Kinh', 'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC', 'hoc_van' => 'Đại học', 'ma_tt' => 2,
            'dia_chi_cu_the' => '1 Nguyễn Trãi', 'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1', 'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }

    private function lookupsWithDepartments(): array
    {
        return [
            'phong_ban' => [
                (object) ['ma_pb' => 3, 'ten_pb' => 'Phòng 3'],
                (object) ['ma_pb' => 4, 'ten_pb' => 'Phòng 4'],
            ],
            'chuc_vu' => [(object) ['ma_cv' => 1, 'ten_cv' => 'Lập trình viên']],
            'trang_thai' => [(object) ['ma_tt' => 2, 'ten_tt' => 'Đang làm việc']],
        ];
    }
}
