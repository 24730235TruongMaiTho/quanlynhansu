<?php

namespace Tests\Feature\Backend\ChucVu;

use App\Contracts\ChucVuServiceContract;
use Illuminate\Foundation\Vite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $this->actingAsChucVuEmployee(['ChucVu.Read', 'ChucVu.Insert', 'ChucVu.Update', 'ChucVu.Delete']);
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

    public function test_position_routes_use_canonical_names_methods_and_positive_parameter(): void
    {
        $index = Route::getRoutes()->getByName('backend.chucvu.index');
        $create = Route::getRoutes()->getByName('backend.chucvu.create');
        $store = Route::getRoutes()->getByName('backend.chucvu.store');
        $edit = Route::getRoutes()->getByName('backend.chucvu.edit');
        $update = Route::getRoutes()->getByName('backend.chucvu.update');
        $destroy = Route::getRoutes()->getByName('backend.chucvu.destroy');

        $this->assertInstanceOf(RoutingRoute::class, $index);
        $this->assertSame('chuc-vu', $index->uri());
        $this->assertSame(['GET', 'HEAD'], $index->methods());
        $this->assertSame('chuc-vu/create', $create->uri());
        $this->assertSame(['POST'], $store->methods());
        $this->assertSame('chuc-vu/{ma_cv}/edit', $edit->uri());
        $this->assertSame('chuc-vu/{ma_cv}', $update->uri());
        $this->assertSame(['PUT', 'PATCH'], $update->methods());
        $this->assertSame(['DELETE'], $destroy->methods());
        $this->assertSame('[1-9][0-9]*', $edit->wheres['ma_cv']);
        $this->assertSame('[1-9][0-9]*', $update->wheres['ma_cv']);
        $this->assertSame('[1-9][0-9]*', $destroy->wheres['ma_cv']);
    }

    public function test_authenticated_list_renders_rows_and_mutation_actions(): void
    {
        $rows = [
            (object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 2],
            (object) ['ma_cv' => 2, 'ten_cv' => 'Nhân viên', 'he_so_phu_cap' => '1.00', 'so_nhan_vien' => 0],
        ];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator($rows, count($rows), 20, 1, ['pageName' => 'page']));
        });

        $this->get('/chuc-vu')->assertOk()
            ->assertViewIs('backend.chucvu.index')
            ->assertSee('Giám đốc')
            ->assertSee('2.00')
            ->assertSee(route('backend.chucvu.create'))
            ->assertSee('Chưa có nhân viên')
            ->assertSee('Sửa')
            ->assertSee('Xóa');
    }

    public function test_authenticated_list_disables_delete_for_dependencies(): void
    {
        $rows = [(object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 1]];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator($rows, count($rows), 20, 1, ['pageName' => 'page']));
        });

        $this->get('/chuc-vu')->assertOk()
            ->assertSee(route('backend.chucvu.create'), false)
            ->assertSee('Sửa', false)
            ->assertSee('disabled', false)
            ->assertSee('Không thể xóa chức vụ đang có nhân viên', false);
    }

    public function test_authenticated_list_filters_paginates_and_exposes_only_authorized_row_actions(): void
    {
        $rows = [
            (object) ['ma_cv' => 6, 'ten_cv' => 'Trưởng khoa', 'he_so_phu_cap' => '2.50', 'so_nhan_vien' => 0],
        ];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($rows): void {
            $mock->shouldReceive('paginate')->once()->with([
                'ten_cv' => 'Trưởng',
                'page' => 2,
                'so_dong' => 5,
            ])->andReturn(new LengthAwarePaginator($rows, 11, 5, 2, ['pageName' => 'page']));
        });

        $response = $this->get('/chuc-vu?ten_cv=Tr%C6%B0%E1%BB%9Fng&page=2&so_dong=5');

        $response->assertOk()
            ->assertSee('Danh sách chức vụ')
            ->assertSee('name="ten_cv"', false)
            ->assertSee('option value="5" selected', false)
            ->assertSee('Hiển thị 6-6 / 11 chức vụ')
            ->assertDontSee('data-row-action-select', false)
            ->assertSee('btn-icon-action', false)
            ->assertSee('aria-label="Sửa Trưởng khoa"', false)
            ->assertSee('title="Sửa Trưởng khoa"', false)
            ->assertSee('aria-label="Xóa Trưởng khoa"', false)
            ->assertSee('«')
            ->assertSee('Trang cuối')
            ->assertSee('onsubmit="return confirm(', false)
            ->assertSee('data-action="modal"', false);
    }

    public function test_list_edit_action_is_an_on_demand_modal_with_a_real_fallback_url(): void
    {
        $position = (object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 0];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($position): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator([$position], 1, 20, 1, ['pageName' => 'page']));
        });

        $editUrl = route('backend.chucvu.edit', 1);
        $this->get('/chuc-vu')
            ->assertOk()
            ->assertSee('data-simple-edit-modal', false)
            ->assertSee('data-action="modal"', false)
            ->assertSee('data-modal-url="'.e($editUrl).'"', false)
            ->assertSee('href="'.e($editUrl).'"', false);
    }

    public function test_modal_edit_returns_a_partial_and_ajax_update_returns_safe_json(): void
    {
        $position = (object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 0];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($position): void {
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($position);
            $mock->shouldReceive('update')->once()->with(1, 'Trưởng phòng', '1.25')->andReturnNull();
        });

        $this->get('/chuc-vu/1/edit', ['X-Edit-Modal' => '1', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertViewIs('backend.chucvu.partials.edit-modal-content')
            ->assertSee('data-simple-edit-form', false)
            ->assertDontSee('<html', false);

        $this->putJson('/chuc-vu/1', ['ten_cv' => 'Trưởng phòng', 'he_so_phu_cap' => '1.25'])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Đã cập nhật chức vụ.'])
            ->assertJsonMissingPath('redirect');
    }

    public function test_modal_update_domain_error_is_a_safe_form_level_422(): void
    {
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new \App\Exceptions\ChucVuDomainException(
                'Tên chức vụ đã tồn tại.', 'CV_NAME_DUPLICATE', 'ten_cv',
            ));
        });

        $this->putJson('/chuc-vu/1', ['ten_cv' => 'Trùng', 'he_so_phu_cap' => '1.25'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ten_cv.0', 'Tên chức vụ đã tồn tại.')
            ->assertJsonMissingPath('domain_code')
            ->assertDontSee('CV_NAME_DUPLICATE');
    }

    public function test_full_edit_keeps_filtered_list_context_and_no_script_fallback_is_real(): void
    {
        $position = (object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 0];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($position): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator([$position], 1, 5, 2, ['pageName' => 'page']));
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($position);
        });

        $this->get('/chuc-vu?ten_cv=Gi%C3%A1m%20%C4%91%E1%BB%91c&page=2&so_dong=5')
            ->assertOk()
            ->assertSee('data-action="modal"', false)
            ->assertSee('btn-icon-action', false);

        $backUrl = route('backend.chucvu.index', [
            'ten_cv' => 'Giám đốc',
            'page' => 2,
            'so_dong' => 5,
        ]);
        $this->get('/chuc-vu/1/edit?ten_cv=Gi%C3%A1m%20%C4%91%E1%BB%91c&page=2&so_dong=5')
            ->assertOk()
            ->assertSee('href="'.e($backUrl).'"', false)
            ->assertSee('value="Giám đốc"', false);
    }

    public function test_store_update_and_delete_normalize_input_and_flash_success(): void
    {
        $position = (object) ['ma_cv' => 1, 'ten_cv' => 'Giám đốc', 'he_so_phu_cap' => '2.00', 'so_nhan_vien' => 0];
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock) use ($position): void {
            $mock->shouldReceive('findOrFail')->once()->with(1)->andReturn($position);
            $mock->shouldReceive('create')->once()->with('Kế toán', '1.50')->andReturnNull();
            $mock->shouldReceive('update')->once()->with(1, 'Trưởng phòng', '1.25')->andReturnNull();
            $mock->shouldReceive('delete')->once()->with(1)->andReturnNull();
        });

        $this->get('/chuc-vu/create')->assertOk()->assertViewIs('backend.chucvu.create');
        $this->post('/chuc-vu', ['ten_cv' => '  Kế toán  ', 'he_so_phu_cap' => '1.50'])
            ->assertRedirect(route('backend.chucvu.index'))
            ->assertSessionHas('success', 'Đã thêm chức vụ.');
        $this->get('/chuc-vu/1/edit')->assertOk()->assertViewIs('backend.chucvu.edit');
        $this->put('/chuc-vu/1', ['ten_cv' => '  Trưởng phòng  ', 'he_so_phu_cap' => '1.25'])
            ->assertRedirect(route('backend.chucvu.index'))
            ->assertSessionHas('success', 'Đã cập nhật chức vụ.');
        $this->delete('/chuc-vu/1')->assertRedirect(route('backend.chucvu.index'))
            ->assertSessionHas('success', 'Đã xóa chức vụ.');
    }

    public function test_validation_rejects_blank_overlong_and_invalid_decimal_before_service(): void
    {
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
        });

        foreach ([
            ['ten_cv' => '   ', 'he_so_phu_cap' => '1.00'],
            ['ten_cv' => str_repeat('a', 101), 'he_so_phu_cap' => '1.00'],
            ['ten_cv' => 'Hợp lệ', 'he_so_phu_cap' => '1.234'],
        ] as $input) {
            $this->from('/chuc-vu/create')->post('/chuc-vu', $input)
                ->assertRedirect('/chuc-vu/create')
                ->assertSessionHasErrors();
        }
    }

    public function test_not_found_and_generic_errors_are_safe(): void
    {
        $this->mock(ChucVuServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findOrFail')->once()->with(999)->andThrow(new \App\Exceptions\ChucVuDomainException('Không tìm thấy chức vụ.', 'CV_NOT_FOUND'));
            $mock->shouldReceive('update')->once()->andThrow(new \RuntimeException('SQLSTATE private details'));
        });

        $this->get('/chuc-vu/999/edit')->assertNotFound()->assertDontSee('CV_NOT_FOUND');
        $this->from('/chuc-vu/1/edit')->put('/chuc-vu/1', ['ten_cv' => 'Mới', 'he_so_phu_cap' => '1.00'])
            ->assertRedirect('/chuc-vu/1/edit')
            ->assertSessionHasErrors(['chuc_vu' => 'Không thể cập nhật chức vụ lúc này. Vui lòng thử lại sau.'])
            ->assertDontSee('SQLSTATE');
    }
}
