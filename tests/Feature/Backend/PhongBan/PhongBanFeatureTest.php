<?php

namespace Tests\Feature\Backend\PhongBan;

use App\Contracts\PhongBanServiceContract;
use App\Enums\PhongBanPermission;
use App\Exceptions\PhongBanDomainException;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;
use Tests\Support\InteractsWithPhongBanModule;
use Tests\TestCase;

class PhongBanFeatureTest extends TestCase
{
    use InteractsWithPhongBanModule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }

    public function test_rest_department_routes_use_canonical_names_methods_parameter_and_handler(): void
    {
        $index = Route::getRoutes()->getByName('backend.phongban.index');
        $create = Route::getRoutes()->getByName('backend.phongban.create');
        $store = Route::getRoutes()->getByName('backend.phongban.store');
        $edit = Route::getRoutes()->getByName('backend.phongban.edit');
        $update = Route::getRoutes()->getByName('backend.phongban.update');
        $destroy = Route::getRoutes()->getByName('backend.phongban.destroy');

        $this->assertInstanceOf(RoutingRoute::class, $index);
        $this->assertSame('admin/phong-ban', $index->uri());
        $this->assertSame(['GET', 'HEAD'], $index->methods());
        $this->assertSame('admin/phong-ban/create', $create->uri());
        $this->assertSame(['GET', 'HEAD'], $create->methods());
        $this->assertSame(['POST'], $store->methods());
        $this->assertSame(['GET', 'HEAD'], $edit->methods());
        $this->assertSame('admin/phong-ban/{ma_pb}/edit', $edit->uri());
        $this->assertSame('admin/phong-ban/{ma_pb}', $update->uri());
        $this->assertSame(['PUT', 'PATCH'], $update->methods());
        $this->assertSame('update', $update->getActionMethod());
        $this->assertSame(['DELETE'], $destroy->methods());
        $this->assertSame('destroy', $destroy->getActionMethod());
        $this->assertSame('[1-9][0-9]*', $edit->wheres['ma_pb']);
        $this->assertSame('[1-9][0-9]*', $update->wheres['ma_pb']);
        $this->assertSame('[1-9][0-9]*', $destroy->wheres['ma_pb']);
    }

    public function test_guest_is_redirected_from_every_department_entry_point(): void
    {
        foreach ([
            ['GET', '/admin/phong-ban'],
            ['GET', '/admin/phong-ban/create'],
            ['GET', '/admin/phong-ban/1/edit'],
            ['POST', '/admin/phong-ban'],
            ['PUT', '/admin/phong-ban/1'],
            ['PATCH', '/admin/phong-ban/1'],
            ['DELETE', '/admin/phong-ban/1'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect(route('login'));
        }
    }

    public function test_permission_gates_are_fail_closed_and_do_not_dispatch_service(): void
    {
        $this->actingAsPhongBanEmployee([]);
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('all');
            $mock->shouldNotReceive('findOrFail');
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('delete');
        });

        foreach ([
            ['GET', '/admin/phong-ban'],
            ['GET', '/admin/phong-ban/create'],
            ['GET', '/admin/phong-ban/1/edit'],
            ['POST', '/admin/phong-ban'],
            ['PUT', '/admin/phong-ban/1'],
            ['DELETE', '/admin/phong-ban/1'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }
    }

    public function test_view_permission_renders_list_and_hides_mutations(): void
    {
        $this->actingAsPhongBanEmployee([PhongBanPermission::Xem]);
        $rows = [
            (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 2],
            (object) ['ma_pb' => 2, 'ten_pb' => 'Nhân sự', 'so_nhan_vien' => 0],
        ];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('all')->once()->andReturn($rows);
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('delete');
        });

        $this->get('/admin/phong-ban')->assertOk()
            ->assertViewIs('backend.phongban.index')
            ->assertSee('Kỹ thuật')
            ->assertSee('2', false)
            ->assertSee('Nhân sự')
            ->assertSee('Chưa có nhân viên')
            ->assertDontSee(route('backend.phongban.create'))
            ->assertDontSee('Chỉnh sửa')
            ->assertDontSee('Xóa');
    }

    public function test_all_permission_list_renders_create_edit_and_safe_delete_actions(): void
    {
        $this->actingAsPhongBanEmployee([
            PhongBanPermission::Xem,
            PhongBanPermission::Tao,
            PhongBanPermission::Sua,
            PhongBanPermission::Xoa,
        ]);
        $rows = [
            (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 2],
            (object) ['ma_pb' => 2, 'ten_pb' => 'Nhân sự', 'so_nhan_vien' => 0],
        ];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('all')->once()->andReturn($rows);
        });

        $response = $this->get('/admin/phong-ban');
        $response->assertOk()
            ->assertSee(route('backend.phongban.create'), false)
            ->assertSee('Chỉnh sửa', false)
            ->assertSee('Xóa', false)
            ->assertSee('data-confirm-delete', false)
            ->assertSee('disabled', false);
        $this->assertSame(1, substr_count($response->getContent(), 'name="_method" value="DELETE"'));
    }

    public function test_empty_state_is_safe(): void
    {
        $this->actingAsPhongBanEmployee([PhongBanPermission::Xem]);
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->once()->andReturn([]);
        });
        $this->get('/admin/phong-ban')->assertOk()->assertSee('Chưa có phòng ban nào');
    }

    public function test_service_error_state_is_safe(): void
    {
        $this->actingAsPhongBanEmployee([PhongBanPermission::Xem]);
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('all')->once()->andThrow(new \RuntimeException('SQLSTATE private details'));
        });
        $this->get('/admin/phong-ban')->assertOk()
            ->assertSee('Không thể tải danh sách phòng ban lúc này.')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('private details');
    }

    public function test_create_page_and_store_normalize_input_and_flash_safe_success(): void
    {
        $this->actingAsPhongBanEmployee([PhongBanPermission::Tao]);
        $this->get('/admin/phong-ban/create')->assertOk()
            ->assertViewIs('backend.phongban.create')
            ->assertSee('label', false)
            ->assertSee('name="ten_pb"', false);
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->with('Kỹ thuật')->andReturnNull();
        });

        $this->post('/admin/phong-ban', ['ten_pb' => '  Kỹ thuật  '])
            ->assertRedirect(route('backend.phongban.index'))
            ->assertSessionHas('success', 'Đã thêm phòng ban.');
    }

    public function test_update_accepts_put_and_patch_and_delete_redirects(): void
    {
        $this->actingAsPhongBanEmployee([
            PhongBanPermission::Xem,
            PhongBanPermission::Sua,
            PhongBanPermission::Xoa,
        ]);
        $department = (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 0];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($department): void {
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($department);
            $mock->shouldReceive('update')->once()->with(1, 'Nhân sự')->andReturnNull();
            $mock->shouldReceive('delete')->once()->with(1)->andReturnNull();
        });
        $this->get('/admin/phong-ban/1/edit')->assertOk()
            ->assertViewIs('backend.phongban.edit')
            ->assertSee('value="Kỹ thuật"', false);
        $this->put('/admin/phong-ban/1', ['ten_pb' => 'Nhân sự'])
            ->assertRedirect(route('backend.phongban.index'))
            ->assertSessionHas('success', 'Đã cập nhật phòng ban.');
        $this->delete('/admin/phong-ban/1')
            ->assertRedirect(route('backend.phongban.index'))
            ->assertSessionHas('success', 'Đã xóa phòng ban.');
    }

    public function test_validation_rejects_blank_and_overlong_names_before_service(): void
    {
        $this->actingAsPhongBanEmployee([PhongBanPermission::Tao]);
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });
        $this->from('/admin/phong-ban/create')->post('/admin/phong-ban', ['ten_pb' => '   '])
            ->assertRedirect('/admin/phong-ban/create')->assertSessionHasErrors('ten_pb');
        $this->from('/admin/phong-ban/create')->post('/admin/phong-ban', ['ten_pb' => str_repeat('a', 101)])
            ->assertRedirect('/admin/phong-ban/create')->assertSessionHasErrors('ten_pb');
    }

    public function test_not_found_invalid_id_domain_errors_and_generic_errors_are_safe(): void
    {
        $this->actingAsPhongBanEmployee([
            PhongBanPermission::Xem,
            PhongBanPermission::Sua,
            PhongBanPermission::Xoa,
            PhongBanPermission::Tao,
        ]);
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findOrFail')->once()->with(999)->andThrow(new PhongBanDomainException(
                'Không tìm thấy phòng ban.', 'PB_NOT_FOUND',
            ));
            $mock->shouldReceive('delete')->once()->with(999)->andThrow(new PhongBanDomainException(
                'Không thể xóa phòng ban vì đang có nhân viên thuộc phòng ban này.', 'PB_IN_USE',
            ));
            $mock->shouldReceive('create')->once()->andThrow(new PhongBanDomainException(
                'Tên phòng ban đã tồn tại.', 'PB_NAME_DUPLICATE', 'ten_pb',
            ));
            $mock->shouldReceive('update')->once()->andThrow(new \RuntimeException('SQLSTATE hidden details'));
        });
        $this->get('/admin/phong-ban/999/edit')->assertNotFound()->assertDontSee('PB_NOT_FOUND');
        $this->from('/admin/phong-ban')->delete('/admin/phong-ban/999')->assertRedirect('/admin/phong-ban')
            ->assertSessionHasErrors(['phong_ban' => 'Không thể xóa phòng ban vì đang có nhân viên thuộc phòng ban này.']);
        $this->from('/admin/phong-ban/create')->post('/admin/phong-ban', ['ten_pb' => 'Trùng'])
            ->assertRedirect('/admin/phong-ban/create')->assertSessionHasErrors(['ten_pb' => 'Tên phòng ban đã tồn tại.']);
        $this->from('/admin/phong-ban/1/edit')->put('/admin/phong-ban/1', ['ten_pb' => 'Tên mới'])
            ->assertRedirect('/admin/phong-ban/1/edit')
            ->assertSessionHasErrors(['phong_ban' => 'Không thể cập nhật phòng ban lúc này. Vui lòng thử lại sau.']);
        foreach (['/admin/phong-ban/0', '/admin/phong-ban/abc', '/admin/phong-ban/999999999999999999999/edit'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }
}
