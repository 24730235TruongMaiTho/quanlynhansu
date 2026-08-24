<?php

namespace Tests\Feature\Authorization;

use App\Authorization\PermissionRegistry;
use App\Contracts\PermissionRepositoryContract;
use App\Enums\NhanVienPermission;
use App\Enums\PermissionAction;
use App\Models\NhanVien;
use App\Repositories\PermissionRepository;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PermissionIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        Schema::create('nhan_vien', function ($table): void {
            $table->string('ma_nv', 5)->primary();
            $table->unsignedInteger('ma_vt');
            $table->unsignedInteger('ma_tt');
        });
        Schema::create('quyen', function ($table): void {
            $table->unsignedInteger('ma_quyen')->primary();
            $table->string('ky_hieu_quyen', 100)->unique();
            $table->string('module', 50);
        });
        Schema::create('vai_tro_quyen', function ($table): void {
            $table->unsignedInteger('ma_vt');
            $table->unsignedInteger('ma_quyen');
            $table->primary(['ma_vt', 'ma_quyen']);
        });

        DB::table('nhan_vien')->insert(['ma_nv' => 'NV001', 'ma_vt' => 1, 'ma_tt' => 2]);
        DB::table('quyen')->insert([
            ['ma_quyen' => 101, 'ky_hieu_quyen' => 'NV_VIEW', 'module' => 'NhanVien'],
            ['ma_quyen' => 102, 'ky_hieu_quyen' => 'NV_CREATE', 'module' => 'NhanVien'],
            ['ma_quyen' => 105, 'ky_hieu_quyen' => 'NV_RESET_PASSWORD', 'module' => 'NhanVien'],
            ['ma_quyen' => 201, 'ky_hieu_quyen' => 'PB_VIEW', 'module' => 'PhongBan'],
            ['ma_quyen' => 202, 'ky_hieu_quyen' => 'PB_CREATE', 'module' => 'PhongBan'],
        ]);
        DB::table('vai_tro_quyen')->insert([
            ['ma_vt' => 1, 'ma_quyen' => 101],
            ['ma_vt' => 1, 'ma_quyen' => 102],
            ['ma_vt' => 1, 'ma_quyen' => 105],
            ['ma_vt' => 1, 'ma_quyen' => 201],
            ['ma_vt' => 1, 'ma_quyen' => 202],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('vai_tro_quyen');
        Schema::dropIfExists('quyen');
        Schema::dropIfExists('nhan_vien');
        parent::tearDown();
    }

    public function test_container_repository_and_gate_use_real_role_mapping_rows(): void
    {
        $repository = $this->app->make(PermissionRepositoryContract::class);
        $this->assertInstanceOf(PermissionRepository::class, $repository);
        $service = $this->app->make(PermissionService::class);
        $employee = $this->employee();

        $this->assertTrue($service->allows($employee, NhanVienPermission::Xem));
        $this->assertTrue($service->allowsModuleAction($employee, 'NhanVien', PermissionAction::Create));
        $this->assertTrue($service->canSeeModule($employee, 'PhongBan'));
        $this->assertTrue(Gate::forUser($employee)->allows('NV_VIEW'));
        $this->assertTrue(Gate::forUser($employee)->allows('NV_RESET_PASSWORD'));
        $this->assertFalse(Gate::forUser($employee)->allows('NV_DELETE'));
    }

    public function test_gate_denies_when_id_symbol_or_module_metadata_drifts(): void
    {
        DB::table('quyen')->where('ma_quyen', 101)->update(['module' => 'PhongBan']);
        $service = new PermissionService(
            $this->app->make(PermissionRepositoryContract::class),
            new PermissionRegistry(),
        );

        $this->assertFalse($service->allows($this->employee(), NhanVienPermission::Xem));
        $this->assertFalse(Gate::forUser($this->employee())->allows('NV_VIEW'));
    }

    public function test_module_visibility_requires_the_registered_view_mapping(): void
    {
        DB::table('vai_tro_quyen')->where('ma_vt', 1)->where('ma_quyen', 201)->delete();
        $service = new PermissionService(
            $this->app->make(PermissionRepositoryContract::class),
            new PermissionRegistry(),
        );

        $this->assertFalse($service->canSeeModule($this->employee(), 'PhongBan'));
        $this->assertTrue($service->allowsModuleAction($this->employee(), 'PhongBan', PermissionAction::Create));
    }

    private function employee(): NhanVien
    {
        return NhanVien::fromAuthRow((object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn Văn An',
            'email' => 'an.nguyen@company.com',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ma_tt' => 2,
        ]);
    }
}
