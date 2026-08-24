<?php

namespace Tests\Feature\Backend\ChucVu;

use App\Contracts\ChucVuServiceContract;
use Illuminate\Foundation\Vite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;
use Tests\Support\InteractsWithChucVuModule;
use Tests\TestCase;

class ChucVuFeatureTest extends TestCase
{
    use InteractsWithChucVuModule;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('chuc_vu')) {
            Schema::create('chuc_vu', static function (Blueprint $table): void {
                $table->increments('ma_cv');
                $table->string('ten_cv', 100)->unique('uq_chuc_vu_ten');
                $table->decimal('he_so_phu_cap', 5, 2);
            });
        }
        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }

    public function test_admin_position_routes_use_canonical_names_methods_and_positive_parameter(): void
    {
        $index = Route::getRoutes()->getByName('backend.chucvu.index');
        $create = Route::getRoutes()->getByName('backend.chucvu.create');
        $store = Route::getRoutes()->getByName('backend.chucvu.store');
        $edit = Route::getRoutes()->getByName('backend.chucvu.edit');
        $update = Route::getRoutes()->getByName('backend.chucvu.update');
        $destroy = Route::getRoutes()->getByName('backend.chucvu.destroy');

        $this->assertInstanceOf(RoutingRoute::class, $index);
        $this->assertSame('admin/chuc-vu', $index->uri());
        $this->assertSame(['GET', 'HEAD'], $index->methods());
        $this->assertSame('admin/chuc-vu/create', $create->uri());
        $this->assertSame(['POST'], $store->methods());
        $this->assertSame('admin/chuc-vu/{ma_cv}/edit', $edit->uri());
        $this->assertSame('admin/chuc-vu/{ma_cv}', $update->uri());
        $this->assertSame(['PUT', 'PATCH'], $update->methods());
        $this->assertSame(['DELETE'], $destroy->methods());
        $this->assertSame('[1-9][0-9]*', $edit->wheres['ma_cv']);
        $this->assertSame('[1-9][0-9]*', $update->wheres['ma_cv']);
        $this->assertSame('[1-9][0-9]*', $destroy->wheres['ma_cv']);
    }

    public function test_guest_is_redirected_from_every_position_entry_point(): void
    {
        foreach ([
            ['GET', '/admin/chuc-vu'],
            ['GET', '/admin/chuc-vu/create'],
            ['GET', '/admin/chuc-vu/1/edit'],
            ['POST', '/admin/chuc-vu'],
            ['PUT', '/admin/chuc-vu/1'],
            ['PATCH', '/admin/chuc-vu/1'],
            ['DELETE', '/admin/chuc-vu/1'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect(route('login'));
        }
    }

    public function test_missing_permission_fails_closed_before_service_dispatch(): void
    {
        $this->actingAsChucVuEmployee([]);
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('all');
            $mock->shouldNotReceive('findOrFail');
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('delete');
        });

        foreach ([
            ['GET', '/admin/chuc-vu'], ['GET', '/admin/chuc-vu/create'],
            ['GET', '/admin/chuc-vu/1/edit'], ['POST', '/admin/chuc-vu'],
            ['PUT', '/admin/chuc-vu/1'], ['DELETE', '/admin/chuc-vu/1'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }
    }

    public function test_view_permission_renders_rows_and_hides_mutation_actions(): void
    {
        $this->actingAsChucVuEmployee(['CV_VIEW']);
        $rows = [
            (object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 2],
            (object) ['ma_cv' => 2, 'ten_cv' => 'Nhân viên', 'he_so_phu_cap' => '1.00', 'so_nhan_vien' => 0],
        ];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('all')->once()->andReturn($rows);
        });

        $this->get('/admin/chuc-vu')->assertOk()
            ->assertViewIs('backend.chucvu.index')
            ->assertSee('Giám đốc')
            ->assertSee('2.00')
            ->assertSee('Chưa có nhân viên')
            ->assertDontSee(route('backend.chucvu.create'))
            ->assertDontSee('Chỉnh sửa')
            ->assertDontSee('Xóa');
    }

    public function test_all_permissions_render_actions_and_disable_delete_for_dependencies(): void
    {
        $this->actingAsChucVuEmployee(['CV_VIEW', 'CV_CREATE', 'CV_EDIT', 'CV_DELETE']);
        $rows = [(object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 1]];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('all')->once()->andReturn($rows);
        });

        $this->get('/admin/chuc-vu')->assertOk()
            ->assertSee(route('backend.chucvu.create'), false)
            ->assertSee('Chỉnh sửa', false)
            ->assertSee('disabled', false)
            ->assertSee('Không thể xóa chức vụ đang có nhân viên', false);
    }

    public function test_store_update_and_delete_normalize_input_and_flash_success(): void
    {
        $this->actingAsChucVuEmployee(['CV_VIEW', 'CV_CREATE', 'CV_EDIT', 'CV_DELETE']);
        $position = (object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 0];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($position): void {
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($position);
            $mock->shouldReceive('create')->once()->with('Kế toán', '1.50')->andReturnNull();
            $mock->shouldReceive('update')->once()->with(1, 'Trưởng phòng', '1.25')->andReturnNull();
            $mock->shouldReceive('delete')->once()->with(1)->andReturnNull();
        });

        $this->get('/admin/chuc-vu/create')->assertOk()->assertViewIs('backend.chucvu.create');
        $this->post('/admin/chuc-vu', ['ten_cv' => '  Kế toán  ', 'he_so_phu_cap' => '1.50'])
            ->assertRedirect(route('backend.chucvu.index'))
            ->assertSessionHas('success', 'Đã thêm chức vụ.');
        $this->get('/admin/chuc-vu/1/edit')->assertOk()->assertViewIs('backend.chucvu.edit');
        $this->put('/admin/chuc-vu/1', ['ten_cv' => '  Trưởng phòng  ', 'he_so_phu_cap' => '1.25'])
            ->assertRedirect(route('backend.chucvu.index'))
            ->assertSessionHas('success', 'Đã cập nhật chức vụ.');
        $this->delete('/admin/chuc-vu/1')->assertRedirect(route('backend.chucvu.index'))
            ->assertSessionHas('success', 'Đã xóa chức vụ.');
    }

    public function test_validation_rejects_blank_overlong_and_invalid_decimal_before_service(): void
    {
        $this->actingAsChucVuEmployee(['CV_CREATE']);
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        foreach ([
            ['ten_cv' => '   ', 'he_so_phu_cap' => '1.00'],
            ['ten_cv' => str_repeat('a', 101), 'he_so_phu_cap' => '1.00'],
            ['ten_cv' => 'Hợp lệ', 'he_so_phu_cap' => '1.234'],
        ] as $input) {
            $this->from('/admin/chuc-vu/create')->post('/admin/chuc-vu', $input)
                ->assertRedirect('/admin/chuc-vu/create')
                ->assertSessionHasErrors();
        }
    }

    public function test_not_found_and_generic_errors_are_safe(): void
    {
        $this->actingAsChucVuEmployee(['CV_VIEW', 'CV_EDIT']);
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findOrFail')->once()->with(999)->andThrow(new \App\Exceptions\ChucVuDomainException('Không tìm thấy chức vụ.', 'CV_NOT_FOUND'));
            $mock->shouldReceive('update')->once()->andThrow(new \RuntimeException('SQLSTATE private details'));
        });

        $this->get('/admin/chuc-vu/999/edit')->assertNotFound()->assertDontSee('CV_NOT_FOUND');
        $this->from('/admin/chuc-vu/1/edit')->put('/admin/chuc-vu/1', ['ten_cv' => 'Mới', 'he_so_phu_cap' => '1.00'])
            ->assertRedirect('/admin/chuc-vu/1/edit')
            ->assertSessionHasErrors(['chuc_vu' => 'Không thể cập nhật chức vụ lúc này. Vui lòng thử lại sau.'])
            ->assertDontSee('SQLSTATE');
    }
}
