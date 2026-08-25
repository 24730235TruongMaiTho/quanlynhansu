<?php

namespace Tests\Feature\Compatibility;

use App\Contracts\NhanVienServiceContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_guest_cannot_read_or_update_attendance_api(): void
    {
        $this->getJson('/api/v1/cham-cong')->assertUnauthorized();
        $this->putJson('/api/v1/cham-cong/1', [
            'so_gio_lam' => 8,
            'vao_muon' => false,
            've_som' => false,
        ])->assertUnauthorized();
    }

    public function test_guest_cannot_read_attendance_department_lookup(): void
    {
        $this->getJson('/api/v1/cham-cong/phong-ban')->assertUnauthorized();
    }

    public function test_zero_permission_actor_cannot_read_or_update_attendance_api(): void
    {
        $this->actingAsEmployeeWithPermissions([]);

        $this->getJson('/api/v1/cham-cong')->assertForbidden();
        $this->putJson('/api/v1/cham-cong/1', [
            'so_gio_lam' => 8,
            'vao_muon' => false,
            've_som' => false,
        ])->assertForbidden();
    }

    public function test_zero_permission_actor_cannot_read_attendance_department_lookup(): void
    {
        $this->actingAsEmployeeWithPermissions([]);

        $this->getJson('/api/v1/cham-cong/phong-ban')->assertForbidden();
    }

    public function test_xem_actor_can_read_attendance_department_lookup(): void
    {
        $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        DB::shouldReceive('select')
            ->once()
            ->with('CALL sp_phong_ban_danh_sach()')
            ->andReturn([]);

        $this->getJson('/api/v1/cham-cong/phong-ban')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_rollout_disabled_hides_attendance_department_lookup(): void
    {
        $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        config()->set('nhanvien.enabled', false);

        $this->getJson('/api/v1/cham-cong/phong-ban')->assertNotFound();
    }

    public function test_xem_only_actor_cannot_update_attendance_api(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);

        $this->putJson('/api/v1/cham-cong/1', [
            'so_gio_lam' => 8,
            'vao_muon' => false,
            've_som' => false,
        ])->assertForbidden();
    }

    public function test_rollout_disabled_precedes_attendance_permissions(): void
    {
        $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
            \App\Enums\NhanVienPermission::Sua,
        ]);
        config()->set('nhanvien.enabled', false);

        $this->getJson('/api/v1/cham-cong')->assertNotFound();
        $this->putJson('/api/v1/cham-cong/1', [
            'so_gio_lam' => 8,
            'vao_muon' => false,
            've_som' => false,
        ])->assertNotFound();
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

    public function test_department_manager_attendance_lookup_forces_forged_and_omitted_department_filters(): void
    {
        $actor = $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        $actor->forceFill([
            'ma_vt' => \App\Enums\NhanVienRole::DepartmentManager->value,
            'ma_pb' => 2,
        ]);
        DB::table('phong_ban')->insert(['ma_pb' => 3]);

        $paginator = new LengthAwarePaginator([], 0, 15, 1);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginateForAttendance')->twice()
                ->withArgs(static fn (array $filters): bool => $filters['ma_pb'] === 2)
                ->andReturn($paginator);
        });

        $this->getJson('/api/v1/cham-cong/nhan-vien?ma_pb=3&thang=8&nam=2026')
            ->assertOk();
        $this->getJson('/api/v1/cham-cong/nhan-vien?thang=8&nam=2026')
            ->assertOk();
    }

    public function test_department_manager_attendance_lookup_with_missing_department_fails_closed(): void
    {
        $actor = $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        $actor->forceFill([
            'ma_vt' => \App\Enums\NhanVienRole::DepartmentManager->value,
            'ma_pb' => null,
        ]);
        $paginator = new LengthAwarePaginator([], 0, 15, 1);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginateForAttendance')->once()
                ->withArgs(static fn (array $filters): bool => $filters['ma_pb'] === 0)
                ->andReturn($paginator);
        });

        $this->getJson('/api/v1/cham-cong/nhan-vien?thang=8&nam=2026')
            ->assertOk();
    }

    #[DataProvider('unscopedRoles')]
    public function test_unscoped_roles_keep_requested_attendance_department_filter(int $role): void
    {
        $actor = $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        $actor->forceFill([
            'ma_vt' => $role,
            'ma_pb' => 2,
        ]);
        DB::table('phong_ban')->insert(['ma_pb' => 3]);
        $paginator = new LengthAwarePaginator([], 0, 15, 1);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginateForAttendance')->once()
                ->withArgs(static fn (array $filters): bool => $filters['ma_pb'] === 3)
                ->andReturn($paginator);
        });

        $this->getJson('/api/v1/cham-cong/nhan-vien?ma_pb=3&thang=8&nam=2026')
            ->assertOk();
    }

    public static function unscopedRoles(): array
    {
        return [
            'super admin' => [1],
            'human resources' => [2],
            'cbl admin' => [3],
            'employee' => [5],
        ];
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
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);

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
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);

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
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);

        Schema::create('nhan_vien', function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        DB::table('nhan_vien')->insert(['ma_nv' => 'NV001']);

        $this->getJson('/api/v1/cham-cong?ma_nv=NV001&thang=13&nam=2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['thang']);
    }

    public function test_authorized_update_reaches_controller_and_hides_database_error(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Sua]);

        Schema::create('cham_cong', function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->decimal('so_gio_lam', 5, 2);
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
        });
        DB::table('cham_cong')->insert([
            'ma_nv' => 'NV001',
            'ngay_lam' => '2026-08-01',
            'so_gio_lam' => 8,
            'vao_muon' => 0,
            've_som' => 0,
        ]);

        $response = $this->putJson('/api/v1/cham-cong/1', [
            'so_gio_lam' => 7.5,
            'vao_muon' => true,
            've_som' => false,
        ]);

        $response
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Không thể cập nhật chấm công.',
            ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('CALL', $body);
        $this->assertStringNotContainsString('cham_cong', $body);
    }
}
