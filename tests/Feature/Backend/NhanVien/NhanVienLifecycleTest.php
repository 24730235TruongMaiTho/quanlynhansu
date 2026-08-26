<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienRemovalAction;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienLifecycleTest extends TestCase
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

    public function test_lifecycle_routes_are_constrained_and_before_dynamic_show(): void
    {
        $destroy = Route::getRoutes()->getByName('backend.nhanvien.destroy');
        $show = Route::getRoutes()->getByName('backend.nhanvien.show');

        $this->assertInstanceOf(RoutingRoute::class, $destroy);
        $this->assertSame('nhan-vien/{ma_nv}', $destroy->uri());
        $this->assertSame(['DELETE'], $destroy->methods());
        $this->assertSame('NV[0-9]{3}', $destroy->wheres['ma_nv']);
        $this->assertLessThan(
            array_search($show, Route::getRoutes()->getRoutes(), true),
            array_search($destroy, Route::getRoutes()->getRoutes(), true),
        );
    }

    public function test_destroy_flash_uses_only_a_safe_static_message(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('removeOrTerminate')->once()->andReturn(NhanVienRemovalAction::Deleted);
        });

        $this->delete('/nhan-vien/NV001')
            ->assertRedirect(route('backend.nhanvien.index'))
            ->assertSessionHas('success', 'Đã xóa hồ sơ nhân viên.');
    }

    public function test_forms_are_accessible_and_partial_is_not_present_on_create(): void
    {
        $target = $this->employee();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($target): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new \Illuminate\Pagination\LengthAwarePaginator(
                [$target], 1, 10, 1,
            ));
            $mock->shouldReceive('lookups')->times(3)->andReturn([
                'phong_ban' => [], 'chuc_vu' => [], 'trang_thai' => [],
            ]);
            $mock->shouldReceive('findOrFail')->twice()->andReturn($target);
        });

        $this->get('/nhan-vien')->assertOk()
            ->assertSee('data-action-dialog', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('Xóa cứng nếu chưa có lịch sử', false);
        $this->get('/nhan-vien/NV001')->assertOk()->assertSee('data-action-dialog', false);
        $this->get('/nhan-vien/NV001/edit')->assertOk()->assertSee('data-action-dialog', false);
        $this->get('/nhan-vien/create')->assertOk()->assertDontSee('data-action-dialog', false);
    }

    private function employee(array $overrides = []): object
    {
        return (object) array_replace([
            'ma_nv' => 'NV001', 'ho_ten' => 'Nguyễn An', 'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1, 'sdt' => '0901234567', 'email' => 'an@example.test',
            'ngay_vao_lam' => '2020-01-01', 'ma_pb' => 1, 'ten_pb' => 'Kỹ thuật',
            'ma_cv' => 1, 'ten_cv' => 'Lập trình viên', 'dan_toc' => 'Kinh',
            'cccd' => '001200000001', 'noi_cap_cccd' => 'Cục CSQLHC', 'hoc_van' => 'Đại học',
            'ma_tt' => 2, 'ten_tt' => 'Đang làm việc',
            'ngay_nghi_viec' => null, 'ma_vt' => 5,
            'ten_vt' => 'Nhân viên', 'anh_dai_dien' => null,
            'dia_chi_cu_the' => '1 Nguyễn Trãi', 'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1', 'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }
}
