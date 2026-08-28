<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienPermission;
use App\Models\NhanVien;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;
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
                    fn (string $entry): string => '<script type="module" src="/build/'.basename($entry).'"> </script>',
                )->implode(''));
            }
        });
    }

    public function test_department_manager_list_forces_actor_department_and_hides_other_department(): void
    {
        $this->actingAsManager(2);
        $visible = $this->employee(['ma_nv' => '00002', 'ho_ten' => 'Trong phòng', 'ma_pb' => 2]);

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($visible): void {
            $mock->shouldReceive('paginate')->once()->withArgs(
                fn (array $filters): bool => $filters['ma_pb'] === 2,
            )->andReturn($this->paginator([$visible]));
            $mock->shouldReceive('lookups')->once()->andReturn([
                'phong_ban' => [(object) ['ma_pb' => 1, 'ten_pb' => 'Khác'], (object) ['ma_pb' => 2, 'ten_pb' => 'Của tôi']],
                'chuc_vu' => [],
                'trang_thai' => [],
            ]);
        });

        $this->get('/nhan-vien?ma_pb=1')
            ->assertOk()
            ->assertSee('Trong phòng')
            ->assertDontSee('Ngoài phòng')
            ->assertSee('value="2" selected', false)
            ->assertDontSee('value="1" selected', false)
            ->assertDontSee('Khác');
    }

    public function test_manager_without_department_receives_an_empty_safe_list(): void
    {
        $this->actingAsManager(null);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('lookups');
        });

        $this->get('/nhan-vien')
            ->assertOk()
            ->assertSee('Không thể xác định phạm vi phòng ban')
            ->assertDontSee('Ngoài phòng');
    }

    public function test_manager_cannot_show_or_edit_employee_from_another_department(): void
    {
        $this->actingAsManager(2);
        $target = $this->employee(['ma_pb' => 1]);
        $service = $this->mock(NhanVienServiceContract::class);
        $service->shouldReceive('findOrFail')->twice()->with('00002')->andReturn($target);

        $this->get('/nhan-vien/00002')->assertNotFound();
        $this->get('/nhan-vien/00002/edit')->assertNotFound();
    }

    public function test_manager_cannot_update_or_destroy_employee_from_another_department(): void
    {
        $this->actingAsManager(2);
        $target = $this->employee(['ma_pb' => 1]);
        $service = $this->mock(NhanVienServiceContract::class);
        $service->shouldReceive('removeOrTerminate')->never();
        $service->shouldReceive('update')->never();
        $service->shouldReceive('findOrFail')->once()->with('00002')->andReturn($target);

        $repository = $this->mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('00002')->andReturn($target);

        $this->delete('/nhan-vien/00002')->assertNotFound();
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->put('/nhan-vien/00002', ['ho_ten' => 'Không được ghi'])->assertNotFound();
    }

    public function test_manager_with_invalid_department_cannot_access_any_target(): void
    {
        $this->actingAsManager('invalid');
        $service = $this->mock(NhanVienServiceContract::class);
        $service->shouldNotReceive('findOrFail');
        $service->shouldNotReceive('removeOrTerminate');

        $this->get('/nhan-vien/00002')->assertNotFound();
        $this->get('/nhan-vien/00002/edit')->assertNotFound();
        $this->delete('/nhan-vien/00002')->assertNotFound();
    }

    public function test_actor_cannot_delete_themselves_and_service_is_not_called(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NhanVienPermission::Xem,
            NhanVienPermission::Xoa,
        ], ['ma_nv' => '00001']);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('findOrFail');
            $mock->shouldNotReceive('removeOrTerminate');
        });

        $this->from('/nhan-vien/00001')
            ->delete('/nhan-vien/00001')
            ->assertRedirect('/nhan-vien/00001')
            ->assertSessionHasErrors([
                'nhan_vien' => 'Không thể tự xóa tài khoản đang đăng nhập.',
            ]);
    }

    public function test_self_destroy_action_is_hidden_from_index_show_and_edit(): void
    {
        $this->actingAsEmployeeWithPermissions([
            NhanVienPermission::Xem,
            NhanVienPermission::Sua,
            NhanVienPermission::Xoa,
        ], ['ma_nv' => '00001']);
        $employee = $this->employee(['ma_nv' => '00001']);
        $service = $this->mock(NhanVienServiceContract::class);
        $service->shouldReceive('paginate')->once()->andReturn($this->paginator([$employee]));
        $service->shouldReceive('lookups')->twice()->andReturn([
            'phong_ban' => [], 'chuc_vu' => [], 'trang_thai' => [],
        ]);
        $service->shouldReceive('findOrFail')->twice()->with('00001')->andReturn($employee);

        $this->get('/nhan-vien')->assertOk()->assertDontSee('Xóa hoặc kết thúc');
        $this->get('/nhan-vien/00001')->assertOk()->assertDontSee('Xóa hoặc kết thúc');
        $this->get('/nhan-vien/00001/edit')->assertOk()->assertDontSee('Xóa hoặc kết thúc');
    }

    private function actingAsManager(int|string|null $department): NhanVien
    {
        return $this->actingAsEmployeeWithPermissions([
            NhanVienPermission::Xem,
            NhanVienPermission::Sua,
            NhanVienPermission::Xoa,
        ], [
            'ma_nv' => '00001',
            'ma_vt' => 4,
            'ma_pb' => $department,
        ]);
    }

    private function employee(array $overrides = []): object
    {
        return (object) array_replace([
            'ma_nv' => '00002', 'ho_ten' => 'Nhân viên', 'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1, 'sdt' => '0900000001', 'email' => 'employee@example.test',
            'ngay_vao_lam' => '2020-01-01', 'ma_pb' => 2, 'ten_pb' => 'Của tôi',
            'ma_cv' => 1, 'ten_cv' => 'Lập trình viên', 'dan_toc' => 'Kinh',
            'cccd' => '001200000001', 'noi_cap_cccd' => 'Cục CSQLHC', 'hoc_van' => 'Đại học',
            'ma_tt' => 1, 'ten_tt' => 'Đang làm việc', 'ngay_nghi_viec' => null,
            'ma_vt' => 5, 'ten_vt' => 'Nhân viên', 'anh_dai_dien' => null,
            'dia_chi_cu_the' => null, 'phuong_xa' => null, 'quan_huyen' => null, 'tinh_thanh' => null,
        ], $overrides);
    }

    private function paginator(array $employees): LengthAwarePaginator
    {
        return new LengthAwarePaginator($employees, count($employees), 20, 1, ['pageName' => 'page']);
    }
}
