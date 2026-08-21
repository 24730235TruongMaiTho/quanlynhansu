<?php

namespace Tests\Feature\Compatibility;

use App\Contracts\NhanVienServiceContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class ChamCongEmployeeLookupSecurityTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('phong_ban', function (Blueprint $table): void {
            $table->unsignedInteger('ma_pb')->primary();
        });
        DB::table('phong_ban')->insert(['ma_pb' => 2]);
    }

    public function test_enabled_lookup_maps_filters_and_preserves_attendance_aggregates(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);

        $paginator = new LengthAwarePaginator(
            collect([(object) [
                'ma_nv' => 'NV001',
                'ho_ten' => 'Nguyễn An',
                'ma_pb' => 2,
                'ten_pb' => 'Kỹ thuật',
                'ma_cv' => 3,
                'ten_cv' => 'Lập trình viên',
                'so_lan_vao_muon' => 2,
                'so_lan_ve_som' => 1,
                'so_ngay_cham_cong' => 20.5,
            ]]),
            51,
            25,
            3,
            ['pageName' => 'page'],
        );

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginateForAttendance')->once()->with([
                'tu_khoa' => 'NV001',
                'ma_pb' => 2,
                'thang' => 8,
                'nam' => 2026,
                'page' => 3,
                'so_dong' => 25,
            ])->andReturn($paginator);
        });

        $this->getJson(
            '/api/v1/cham-cong/nhan-vien?tu_khoa=%20NV001%20&ma_pb=2&thang=8&nam=2026&page=3&per_page=25',
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_page', 3)
            ->assertJsonPath('data.per_page', 25)
            ->assertJsonPath('data.total', 51)
            ->assertJsonPath('data.data.0.ma_nv', 'NV001')
            ->assertJsonPath('data.data.0.so_lan_vao_muon', 2)
            ->assertJsonPath('data.data.0.so_lan_ve_som', 1)
            ->assertJsonPath('data.data.0.so_ngay_cham_cong', 20.5);
    }

    public function test_any_lookup_failure_returns_only_the_stable_public_error(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginateForAttendance')->once()->andThrow(
                new RuntimeException('SQLSTATE[42000] CALL sp_internal mat_khau'),
            );
        });

        $response = $this->getJson(
            '/api/v1/cham-cong/nhan-vien?thang=8&nam=2026',
        );

        $response
            ->assertStatus(500)
            ->assertExactJson([
                'success' => false,
                'message' => 'Không thể tải danh sách nhân viên.',
            ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('CALL', $body);
        $this->assertStringNotContainsString('mat_khau', $body);
    }

    public function test_exists_validation_database_failure_returns_only_the_stable_public_error(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);
        Schema::drop('phong_ban');
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginateForAttendance');
        });

        $response = $this->getJson(
            '/api/v1/cham-cong/nhan-vien?ma_pb=2&thang=8&nam=2026',
        );

        $response
            ->assertStatus(500)
            ->assertExactJson([
                'success' => false,
                'message' => 'Không thể tải danh sách nhân viên.',
            ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('phong_ban', $body);
        $this->assertStringNotContainsString('select count', strtolower($body));
    }

    public function test_ordinary_invalid_filter_still_returns_laravel_validation_errors(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginateForAttendance');
        });

        $this->getJson('/api/v1/cham-cong/nhan-vien?thang=13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['thang']);
    }

    public function test_attendance_detail_uses_query_builder_pagination_and_summary(): void
    {
        Schema::create('nhan_vien', function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        Schema::create('cham_cong', function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->decimal('so_gio_lam', 5, 2);
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
        });

        DB::table('nhan_vien')->insert(['ma_nv' => 'NV001']);
        DB::table('cham_cong')->insert([
            [
                'ma_nv' => 'NV001',
                'ngay_lam' => '2026-08-01',
                'so_gio_lam' => 8,
                'vao_muon' => 1,
                've_som' => 0,
            ],
            [
                'ma_nv' => 'NV001',
                'ngay_lam' => '2026-08-02',
                'so_gio_lam' => 4,
                'vao_muon' => 0,
                've_som' => 1,
            ],
            [
                'ma_nv' => 'NV001',
                'ngay_lam' => '2026-07-31',
                'so_gio_lam' => 8,
                'vao_muon' => 0,
                've_som' => 0,
            ],
        ]);

        $response = $this->getJson(
            '/api/v1/cham-cong?ma_nv=NV001&thang=8&nam=2026&per_page=1',
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.per_page', 1)
            ->assertJsonPath('data.data.0.ngay_lam', '2026-08-01')
            ->assertJsonPath('summary.tong_gio_lam', 12)
            ->assertJsonPath('summary.so_lan_vao_muon', 1)
            ->assertJsonPath('summary.so_lan_ve_som', 1)
            ->assertJsonPath('summary.so_ngay_cham_cong', 1.5);
    }

    public function test_attendance_detail_query_failure_returns_stable_error_without_sql(): void
    {
        Schema::create('nhan_vien', function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        DB::table('nhan_vien')->insert(['ma_nv' => 'NV001']);

        $response = $this->getJson(
            '/api/v1/cham-cong?ma_nv=NV001&thang=8&nam=2026',
        );

        $response
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Không thể tải dữ liệu chấm công.',
            ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('cham_cong', $body);
        $this->assertStringNotContainsString('select', strtolower($body));
    }

    public function test_attendance_detail_invalid_filter_keeps_laravel_validation_contract(): void
    {
        Schema::create('nhan_vien', function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        DB::table('nhan_vien')->insert(['ma_nv' => 'NV001']);

        $this->getJson('/api/v1/cham-cong?ma_nv=NV001&thang=13&nam=2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['thang']);
    }
}
