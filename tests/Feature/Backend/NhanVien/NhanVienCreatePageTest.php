<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienServiceContract;
use App\Exceptions\NhanVienDomainException;
use App\Http\Controllers\Backend\NhanVienController;
use App\Http\Middleware\EnsureNhanVienModuleEnabled;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienCreatePageTest extends TestCase
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

    public function test_enabled_create_renders_accessible_three_step_form_from_service_lookups(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('lookups')->once()->andReturn($this->completeLookups());
        });

        $response = $this->get('/admin/nhan-vien/create');

        $response
            ->assertOk()
            ->assertViewIs('backend.nhanvien.create')
            ->assertViewHas('missingLookups', [])
            ->assertViewHas('firstErrorStep', 1)
            ->assertSee('data-initial-step="1"', false)
            ->assertSee('data-wizard-step="1"', false)
            ->assertSee('data-wizard-step="2"', false)
            ->assertSee('data-wizard-step="3"', false)
            ->assertSee('<dl', false)
            ->assertSee('action="'.route('backend.nhanvien.store').'"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('Mã nhân viên được hệ thống tự cấp')
            ->assertSee('nhom3@2026')
            ->assertSee('NHAN_VIEN_MAC_DINH')
            ->assertSee('không có quyền mặc định')
            ->assertSee('Kỹ thuật')
            ->assertSee('Lập trình viên')
            ->assertSee('Đang làm việc')
            ->assertDontSee('Đã nghỉ việc')
            ->assertDontSee('name="ma_nv"', false)
            ->assertDontSee('name="ma_vt"', false)
            ->assertDontSee('name="mat_khau"', false)
            ->assertDontSee('type="password"', false)
            ->assertDontSee('name="hop_dong"', false)
            ->assertDontSee('name="luong_co_ban"', false)
            ->assertSee('name="ho_ten"', false)
            ->assertSee('name="anh_dai_dien"', false)
            ->assertSee('name="dia_chi_cu_the"', false)
            ->assertSee('name="ngay_vao_lam"', false)
            ->assertSee('name="ma_pb"', false)
            ->assertSee('name="ma_cv"', false)
            ->assertSee('name="ma_tt"', false)
            ->assertSee('data-submit-employee', false)
            ->assertDontSee('data-submit-employee disabled', false)
            ->assertSee('/build/nhanvien.js', false);

        $this->assertSame(1, substr_count($response->getContent(), '/build/nhanvien.js'));
    }

    public function test_create_lists_each_missing_required_lookup_and_disables_submit(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('lookups')->once()->andReturn([
                'phong_ban' => [],
                'chuc_vu' => [],
                'trang_thai' => [],
            ]);
        });

        $this->get('/admin/nhan-vien/create')
            ->assertOk()
            ->assertViewHas('missingLookups', ['Phòng ban', 'Chức vụ', 'Trạng thái làm việc'])
            ->assertSee('Thiếu dữ liệu danh mục bắt buộc')
            ->assertSee('Phòng ban')
            ->assertSee('Chức vụ')
            ->assertSee('Trạng thái làm việc')
            ->assertSee('data-submit-employee', false)
            ->assertSee('disabled', false)
            ->assertSee('aria-disabled="true"', false);
    }

    public function test_lookup_failure_renders_safe_locked_form_without_internal_details(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('lookups')->once()->andThrow(new NhanVienDomainException(
                'SQLSTATE[42000]: sp_chuc_vu_danh_sach failed',
                'NV_DATABASE_ERROR',
            ));
        });

        $this->get('/admin/nhan-vien/create')
            ->assertOk()
            ->assertSee('Không thể tải dữ liệu danh mục lúc này. Vui lòng thử lại sau.')
            ->assertSee('data-submit-employee', false)
            ->assertSee('disabled', false)
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('sp_chuc_vu_danh_sach')
            ->assertDontSee('NV_DATABASE_ERROR');
    }

    public function test_validation_errors_restore_old_input_and_open_the_first_invalid_step(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('lookups')->once()->andReturn($this->completeLookups());
        });

        $errors = (new ViewErrorBag)->put(
            'default',
            new MessageBag([
                'ma_pb' => ['Phòng ban không hợp lệ.'],
            ]),
        );

        $this->withSession([
            '_old_input' => [
                'ho_ten' => 'Nguyễn An',
                'email' => 'an@example.test',
                'ma_pb' => '1',
            ],
            'errors' => $errors,
        ])->get('/admin/nhan-vien/create')
            ->assertOk()
            ->assertViewHas('firstErrorStep', 2)
            ->assertSee('data-initial-step="2"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('value="Nguyễn An"', false)
            ->assertSee('value="an@example.test"', false)
            ->assertSee('data-error-focus', false)
            ->assertSee('Phòng ban không hợp lệ.');
    }

    public function test_create_and_legacy_routes_are_canonical_ordered_and_guarded(): void
    {
        $createRoute = Route::getRoutes()->getByName('backend.nhanvien.create');
        $showRoute = Route::getRoutes()->getByName('backend.nhanvien.show');

        $this->assertInstanceOf(RoutingRoute::class, $createRoute);
        $this->assertSame('admin/nhan-vien/create', $createRoute->uri());
        $this->assertSame(NhanVienController::class.'@create', $createRoute->getActionName());
        $this->assertContains(EnsureNhanVienModuleEnabled::class, $createRoute->gatherMiddleware());
        $this->assertLessThan(
            array_search($showRoute, Route::getRoutes()->getRoutes(), true),
            array_search($createRoute, Route::getRoutes()->getRoutes(), true),
        );

        $this->get('/admin/nhan-vien/create')->assertNotFound();
        $this->get('/admin/nhan-vien/them-nhan-vien')->assertNotFound();

        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('lookups');
        });

        $this->get('/admin/nhan-vien/them-nhan-vien?from=legacy')
            ->assertStatus(301)
            ->assertRedirect('/admin/nhan-vien/create?from=legacy');
    }

    private function completeLookups(): array
    {
        return [
            'phong_ban' => [(object) ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật']],
            'chuc_vu' => [(object) ['ma_cv' => 1, 'ten_cv' => 'Lập trình viên']],
            'trang_thai' => [
                (object) ['ma_tt' => 1, 'ky_hieu' => 'DANG_LAM', 'ten_tt' => 'Đang làm việc'],
                (object) ['ma_tt' => 2, 'ky_hieu' => 'DA_NGHI', 'ten_tt' => 'Đã nghỉ việc'],
            ],
        ];
    }
}
