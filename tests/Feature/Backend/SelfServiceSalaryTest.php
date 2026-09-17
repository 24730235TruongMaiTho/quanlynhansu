<?php

namespace Tests\Feature\Backend;

use App\Services\LuongService;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class SelfServiceSalaryTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('luong', static function (Blueprint $table): void {
            $table->increments('ma_luong');
            $table->string('ma_nv', 5);
            $table->date('ky_luong');
            $table->decimal('thuong', 18, 0)->nullable();
            $table->decimal('phat', 18, 0)->nullable();
            $table->decimal('bao_hiem', 18, 0)->nullable();
            $table->decimal('thue', 18, 0)->nullable();
        });
        DB::getPdo()->sqliteCreateFunction('fn_so_ngay_cong_chuan', static fn (): int => 20, 2);
        DB::getPdo()->sqliteCreateFunction('fn_so_ngay_cong_thuc_te', static fn (): float => 19.5, 2);
        DB::getPdo()->sqliteCreateFunction('fn_tinh_luong_thuc_nhan', static fn (): int => 12000000, 2);
        DB::getPdo()->sqliteCreateFunction('fn_thong_bao_tinh_luong', static fn (): string => 'Hoàn tất tính lương', 2);

        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('luong');

        parent::tearDown();
    }

    public function test_repository_returns_only_the_exact_owner_and_requested_period(): void
    {
        DB::table('luong')->insert([
            ['ma_nv' => '00007', 'ky_luong' => '2026-09-01', 'thuong' => 1, 'phat' => 0, 'bao_hiem' => 0, 'thue' => 0],
            ['ma_nv' => '00007', 'ky_luong' => '2026-08-01', 'thuong' => 2, 'phat' => 0, 'bao_hiem' => 0, 'thue' => 0],
            ['ma_nv' => '00008', 'ky_luong' => '2026-09-01', 'thuong' => 3, 'phat' => 0, 'bao_hiem' => 0, 'thue' => 0],
        ]);

        $page = app(\App\Repositories\LuongRepository::class)->paginateForEmployee('00007', [
            'ky_luong' => '2026-09-01',
            'page' => 1,
            'per_page' => 20,
        ]);

        self::assertSame([1], array_map(static fn (object $row): int => (int) $row->ma_luong, $page->items()));
        self::assertSame(['00007'], array_map(static fn (object $row): string => $row->ma_nv, $page->items()));
    }

    public function test_own_salary_api_is_auth_only_and_rejects_identity_filters(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);
        $service = Mockery::mock(LuongService::class);
        $service->shouldReceive('getForEmployee')->once()->with('00007', [
            'ky_luong' => '2026-09-01',
            'page' => 1,
            'per_page' => 20,
        ])->andReturn([
            'success' => true,
            'data' => new LengthAwarePaginator([], 0, 20, 1, ['pageName' => 'page']),
        ]);
        $this->app->instance(LuongService::class, $service);

        $this->getJson('/api/v1/luong/cua-toi?ky_luong=2026-09&per_page=20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.per_page', 20);

        foreach (['ma_nv=00008', 'ma_pb=2', 'ma_cv=3', 'tu_khoa=khac'] as $query) {
            $this->getJson('/api/v1/luong/cua-toi?'.$query)
                ->assertUnprocessable();
        }
    }

    public function test_own_salary_page_is_read_only_and_management_stays_protected(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);
        $service = Mockery::mock(LuongService::class);
        $service->shouldReceive('getForEmployee')->once()->with('00007', [
            'ky_luong' => null,
            'page' => 1,
            'per_page' => 10,
        ])->andReturn([
            'success' => true,
            'data' => new LengthAwarePaginator([
                (object) [
                    'ma_luong' => 1,
                    'ma_nv' => '00007',
                    'ky_luong' => '2026-09-01',
                    'thuong' => 0,
                    'phat' => 0,
                    'bao_hiem' => 0,
                    'thue' => 0,
                    'so_ngay_cong_chuan' => 20,
                    'so_ngay_cong_thuc_te' => 19.5,
                    'thuc_nhan' => 12000000,
                    'thong_bao_tinh_luong' => 'Hoàn tất tính lương',
                ],
            ], 1, 10),
        ]);
        $this->app->instance(LuongService::class, $service);

        $this->get('/luong-cua-toi')
            ->assertOk()
            ->assertViewIs('backend.selfservice.luong')
            ->assertSee('Lương của tôi')
            ->assertSee('19,5')
            ->assertDontSee('00007')
            ->assertDontSee('Xuất')
            ->assertDontSee('Tạo')
            ->assertDontSee('Chỉnh sửa')
            ->assertDontSee('Xóa');

        $this->get('/luong')->assertForbidden();
    }

    public function test_own_salary_routes_are_auth_only_and_management_route_keeps_read_permission(): void
    {
        $selfApi = Route::getRoutes()->getByName('api.v1.luong.cua-toi');
        self::assertNotNull($selfApi);
        self::assertContains('auth', $selfApi->gatherMiddleware());
        self::assertNotContains('can:Luong.Read', $selfApi->gatherMiddleware());

        $selfWeb = Route::getRoutes()->getByName('backend.selfservice.luong.index');
        self::assertNotNull($selfWeb);
        self::assertContains('auth', $selfWeb->gatherMiddleware());

        $management = Route::getRoutes()->getByName('luong.index');
        self::assertNotNull($management);
        self::assertContains('can:Luong.Read', $management->gatherMiddleware());
    }

    public function test_salary_self_service_exception_is_safe(): void
    {
        $repository = Mockery::mock(\App\Repositories\LuongRepository::class);
        $repository->shouldReceive('paginateForEmployee')->once()->andThrow(new \RuntimeException('SQLSTATE internal'));

        $result = (new LuongService($repository))->getForEmployee('00007', []);

        self::assertFalse($result['success']);
        self::assertSame('Không thể tải lương của bạn.', $result['message']);
        self::assertStringNotContainsString('SQLSTATE', $result['message']);
    }
}
