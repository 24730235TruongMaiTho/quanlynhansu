<?php

namespace Tests\Feature\Backend\PhongBan;

use App\Contracts\PhongBanServiceContract;
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
        $this->actingAsPhongBanEmployee([
            \App\Enums\PhongBanPermission::Xem,
            \App\Enums\PhongBanPermission::Tao,
            \App\Enums\PhongBanPermission::Sua,
            \App\Enums\PhongBanPermission::Xoa,
        ]);
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
        $this->assertSame('phong-ban', $index->uri());
        $this->assertSame(['GET', 'HEAD'], $index->methods());
        $this->assertSame('phong-ban/create', $create->uri());
        $this->assertSame(['GET', 'HEAD'], $create->methods());
        $this->assertSame(['POST'], $store->methods());
        $this->assertSame(['GET', 'HEAD'], $edit->methods());
        $this->assertSame('phong-ban/{ma_pb}/edit', $edit->uri());
        $this->assertSame('phong-ban/{ma_pb}', $update->uri());
        $this->assertSame(['PUT', 'PATCH'], $update->methods());
        $this->assertSame('update', $update->getActionMethod());
        $this->assertSame(['DELETE'], $destroy->methods());
        $this->assertSame('destroy', $destroy->getActionMethod());
        $this->assertSame('[1-9][0-9]*', $edit->wheres['ma_pb']);
        $this->assertSame('[1-9][0-9]*', $update->wheres['ma_pb']);
        $this->assertSame('[1-9][0-9]*', $destroy->wheres['ma_pb']);
    }

    public function test_authenticated_list_renders_rows_and_mutations(): void
    {
        $rows = [
            (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 2],
            (object) ['ma_pb' => 2, 'ten_pb' => 'Nhân sự', 'so_nhan_vien' => 0],
        ];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator($rows, count($rows), 20, 1, ['pageName' => 'page']));
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
            $mock->shouldNotReceive('delete');
        });

        $this->get('/phong-ban')->assertOk()
            ->assertViewIs('backend.phongban.index')
            ->assertSee('Kỹ thuật')
            ->assertSee('2', false)
            ->assertSee('Nhân sự')
            ->assertSee('0', false)
            ->assertSee(route('backend.phongban.create'))
            ->assertSee('Sửa')
            ->assertSee('Xóa');
    }

    public function test_authenticated_list_renders_create_edit_and_safe_delete_actions(): void
    {
        $rows = [
            (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 2],
            (object) ['ma_pb' => 2, 'ten_pb' => 'Nhân sự', 'so_nhan_vien' => 0],
        ];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator($rows, count($rows), 20, 1, ['pageName' => 'page']));
        });

        $response = $this->get('/phong-ban');
        $response->assertOk()
            ->assertSee(route('backend.phongban.create'), false)
            ->assertSee('Sửa', false)
            ->assertSee('Xóa', false)
            ->assertSee('onsubmit="return confirm(', false)
            ->assertSee('disabled', false);
        $this->assertSame(1, substr_count($response->getContent(), 'name="_method" value="DELETE"'));
    }

    public function test_authenticated_list_filters_paginates_and_keeps_delete_confirmation_safe(): void
    {
        $rows = [
            (object) ['ma_pb' => 6, 'ten_pb' => 'Phòng Kế hoạch', 'so_nhan_vien' => 0],
        ];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('paginate')->once()->with([
                'ten_pb' => 'Kế hoạch',
                'page' => 2,
                'so_dong' => 5,
            ])->andReturn(new LengthAwarePaginator($rows, 11, 5, 2, ['pageName' => 'page']));
        });

        $response = $this->get('/phong-ban?ten_pb=K%E1%BA%BF%20ho%E1%BA%A1ch&page=2&so_dong=5');

        $response->assertOk()
            ->assertSee('Danh sách phòng ban')
            ->assertSee('name="ten_pb"', false)
            ->assertSee('option value="5" selected', false)
            ->assertSee('Hiển thị 6-6 / 11 phòng ban')
            ->assertSee('data-row-action-select', false)
            ->assertSee('Sửa')
            ->assertSee('Xóa')
            ->assertSee('«')
            ->assertSee('Trang cuối')
            ->assertSee('data-confirm-message=', false)
            ->assertSee('<noscript>', false)
            ->assertSee('data-action="modal"', false);
    }

    public function test_list_edit_action_is_an_on_demand_modal_with_a_real_fallback_url(): void
    {
        $department = (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 0];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($department): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator([$department], 1, 20, 1, ['pageName' => 'page']));
        });

        $editUrl = route('backend.phongban.edit', 1);
        $this->get('/phong-ban')
            ->assertOk()
            ->assertSee('data-simple-edit-modal', false)
            ->assertSee('data-action="modal"', false)
            ->assertSee('data-modal-url="'.e($editUrl).'"', false)
            ->assertSee('value="'.$editUrl.'"', false);
    }

    public function test_modal_edit_returns_a_partial_and_ajax_update_returns_safe_json(): void
    {
        $department = (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 0];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($department): void {
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($department);
            $mock->shouldReceive('update')->once()->with(1, 'Nhân sự')->andReturnNull();
        });

        $this->get('/phong-ban/1/edit', ['X-Edit-Modal' => '1', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertViewIs('backend.phongban.partials.edit-modal-content')
            ->assertSee('data-simple-edit-form', false)
            ->assertDontSee('<html', false);

        $this->putJson('/phong-ban/1', ['ten_pb' => 'Nhân sự'])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Đã cập nhật phòng ban.'])
            ->assertJsonMissingPath('redirect');
    }

    public function test_modal_update_domain_error_is_a_safe_form_level_422(): void
    {
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new PhongBanDomainException(
                'Tên phòng ban đã tồn tại.', 'PB_NAME_DUPLICATE', 'ten_pb',
            ));
        });

        $this->putJson('/phong-ban/1', ['ten_pb' => 'Trùng'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ten_pb.0', 'Tên phòng ban đã tồn tại.')
            ->assertJsonMissingPath('domain_code')
            ->assertDontSee('PB_NAME_DUPLICATE');
    }

    public function test_full_edit_keeps_filtered_list_context_and_no_script_fallback_is_real(): void
    {
        $department = (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 0];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($department): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator([$department], 1, 5, 2, ['pageName' => 'page']));
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($department);
        });

        $this->get('/phong-ban?ten_pb=K%E1%BB%B9%20thu%E1%BA%ADt&page=2&so_dong=5')
            ->assertOk()
            ->assertSee('data-action="modal"', false)
            ->assertSee('<noscript>', false);

        $backUrl = route('backend.phongban.index', [
            'ten_pb' => 'Kỹ thuật',
            'page' => 2,
            'so_dong' => 5,
        ]);
        $this->get('/phong-ban/1/edit?ten_pb=K%E1%BB%B9%20thu%E1%BA%ADt&page=2&so_dong=5')
            ->assertOk()
            ->assertSee('href="'.e($backUrl).'"', false)
            ->assertSee('value="Kỹ thuật"', false);
    }

    public function test_empty_state_is_safe(): void
    {
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator([], 0, 20, 1, ['pageName' => 'page']));
        });
        $this->get('/phong-ban')->assertOk()->assertSee('Chưa có phòng ban nào');
    }

    public function test_service_error_state_is_safe(): void
    {
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->andThrow(new \RuntimeException('SQLSTATE private details'));
        });
        $this->get('/phong-ban')->assertOk()
            ->assertSee('Không thể tải danh sách phòng ban lúc này.')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('private details');
    }

    public function test_create_page_and_store_normalize_input_and_flash_safe_success(): void
    {
        $this->get('/phong-ban/create')->assertOk()
            ->assertViewIs('backend.phongban.create')
            ->assertSee('label', false)
            ->assertSee('name="ten_pb"', false);
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')->once()->with('Kỹ thuật')->andReturnNull();
        });

        $this->post('/phong-ban', ['ten_pb' => '  Kỹ thuật  '])
            ->assertRedirect(route('backend.phongban.index'))
            ->assertSessionHas('success', 'Đã thêm phòng ban.');
    }

    public function test_update_accepts_put_and_patch_and_delete_redirects(): void
    {
        $department = (object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật', 'so_nhan_vien' => 0];
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock) use ($department): void {
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($department);
            $mock->shouldReceive('update')->once()->with(1, 'Nhân sự')->andReturnNull();
            $mock->shouldReceive('delete')->once()->with(1)->andReturnNull();
        });
        $this->get('/phong-ban/1/edit')->assertOk()
            ->assertViewIs('backend.phongban.edit')
            ->assertSee('value="Kỹ thuật"', false);
        $this->put('/phong-ban/1', ['ten_pb' => 'Nhân sự'])
            ->assertRedirect(route('backend.phongban.index'))
            ->assertSessionHas('success', 'Đã cập nhật phòng ban.');
        $this->delete('/phong-ban/1')
            ->assertRedirect(route('backend.phongban.index'))
            ->assertSessionHas('success', 'Đã xóa phòng ban.');
    }

    public function test_validation_rejects_blank_and_overlong_names_before_service(): void
    {
        $this->mock(PhongBanServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });
        $this->from('/phong-ban/create')->post('/phong-ban', ['ten_pb' => '   '])
            ->assertRedirect('/phong-ban/create')->assertSessionHasErrors('ten_pb');
        $this->from('/phong-ban/create')->post('/phong-ban', ['ten_pb' => str_repeat('a', 101)])
            ->assertRedirect('/phong-ban/create')->assertSessionHasErrors('ten_pb');
    }

    public function test_not_found_invalid_id_domain_errors_and_generic_errors_are_safe(): void
    {
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
        $this->get('/phong-ban/999/edit')->assertNotFound()->assertDontSee('PB_NOT_FOUND');
        $this->from('/phong-ban')->delete('/phong-ban/999')->assertRedirect('/phong-ban')
            ->assertSessionHasErrors(['phong_ban' => 'Không thể xóa phòng ban vì đang có nhân viên thuộc phòng ban này.']);
        $this->from('/phong-ban/create')->post('/phong-ban', ['ten_pb' => 'Trùng'])
            ->assertRedirect('/phong-ban/create')->assertSessionHasErrors(['ten_pb' => 'Tên phòng ban đã tồn tại.']);
        $this->from('/phong-ban/1/edit')->put('/phong-ban/1', ['ten_pb' => 'Tên mới'])
            ->assertRedirect('/phong-ban/1/edit')
            ->assertSessionHasErrors(['phong_ban' => 'Không thể cập nhật phòng ban lúc này. Vui lòng thử lại sau.']);
        foreach (['/phong-ban/0', '/phong-ban/abc', '/phong-ban/999999999999999999999/edit'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }
}
