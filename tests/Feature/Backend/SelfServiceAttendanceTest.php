<?php

namespace Tests\Feature\Backend;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class SelfServiceAttendanceTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        Schema::create('cham_cong', static function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->smallInteger('so_gio_lam');
            $table->boolean('vao_muon')->default(false);
            $table->boolean('ve_som')->default(false);
        });

        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00007'],
            ['ma_nv' => '00008'],
        ]);
        DB::table('cham_cong')->insert([
            ['ma_nv' => '00007', 'ngay_lam' => '2026-09-01', 'so_gio_lam' => 5, 'vao_muon' => 1, 've_som' => 0],
            ['ma_nv' => '00007', 'ngay_lam' => '2026-09-02', 'so_gio_lam' => 7, 'vao_muon' => 0, 've_som' => 1],
            ['ma_nv' => '00008', 'ngay_lam' => '2026-09-01', 'so_gio_lam' => 8, 'vao_muon' => 0, 've_som' => 0],
        ]);

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
        Schema::dropIfExists('cham_cong');
        Schema::dropIfExists('nhan_vien');

        parent::tearDown();
    }

    public function test_employee_can_read_only_exact_own_attendance_without_module_permission(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);

        $this->getJson('/api/v1/cham-cong/cua-toi?thang=9&nam=2026&per_page=20')
            ->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.ma_nv', '00007')
            ->assertJsonPath('summary.tong_gio_lam', 12)
            ->assertJsonPath('summary.so_lan_vao_muon', 1)
            ->assertJsonPath('summary.so_lan_ve_som', 1);

        $this->get('/cham-cong-cua-toi?thang=9&nam=2026')
            ->assertOk()
            ->assertViewIs('backend.selfservice.chamcong')
            ->assertSee('Chấm công của tôi')
            ->assertSee('12')
            ->assertDontSee('employee-search')
            ->assertDontSee('department-filter')
            ->assertDontSee('data-delete')
            ->assertDontSee('data-save')
            ->assertDontSee('Nhập file')
            ->assertDontSee('Xuất file');
    }

    public function test_identity_filters_are_rejected_instead_of_being_used_as_selector(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);

        foreach (['ma_nv=00008', 'ma_pb=2', 'tu_khoa=khac'] as $query) {
            $this->getJson('/api/v1/cham-cong/cua-toi?'.$query)->assertUnprocessable();
        }
    }

    public function test_self_service_routes_are_auth_only_and_management_keeps_read_permission(): void
    {
        $selfApi = \Illuminate\Support\Facades\Route::getRoutes()->getByName('api.v1.cham-cong.cua-toi');
        self::assertNotNull($selfApi);
        self::assertContains('auth', $selfApi->gatherMiddleware());
        self::assertNotContains('can:ChamCong.Read', $selfApi->gatherMiddleware());

        $selfWeb = \Illuminate\Support\Facades\Route::getRoutes()->getByName('backend.selfservice.chamcong.index');
        self::assertNotNull($selfWeb);
        self::assertContains('auth', $selfWeb->gatherMiddleware());

        $management = \Illuminate\Support\Facades\Route::getRoutes()->getByName('backend.chamcong.index');
        self::assertNotNull($management);
        self::assertContains('can:ChamCong.Read', $management->gatherMiddleware());
    }
}
