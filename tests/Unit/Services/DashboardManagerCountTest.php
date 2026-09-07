<?php

namespace Tests\Unit\Services;

use App\Enums\NghiPhepPermission;
use App\Models\NhanVien;
use App\Services\DashboardService;
use App\Services\PermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DashboardManagerCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv')->primary();
            $table->string('ho_ten');
            $table->unsignedInteger('ma_vt');
            $table->unsignedInteger('ma_pb')->nullable();
            $table->unsignedTinyInteger('ma_tt');
        });
        Schema::create('nghi_phep', static function (Blueprint $table): void {
            $table->increments('ma_np');
            $table->string('ma_nv');
            $table->unsignedTinyInteger('trang_thai_duyet');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nghi_phep');
        Schema::dropIfExists('nhan_vien');
        parent::tearDown();
    }

    public function test_count_is_scoped_to_eligible_manager_department(): void
    {
        $manager = $this->actor(['ma_vt' => 4, 'ma_pb' => 2]);
        DB::table('nhan_vien')->insert([
            ['ma_nv' => '00002', 'ho_ten' => 'Cùng phòng', 'ma_vt' => 5, 'ma_pb' => 2, 'ma_tt' => 1],
            ['ma_nv' => '00003', 'ho_ten' => 'Khác phòng', 'ma_vt' => 5, 'ma_pb' => 1, 'ma_tt' => 1],
        ]);
        DB::table('nghi_phep')->insert([
            ['ma_nv' => '00002', 'trang_thai_duyet' => 0],
            ['ma_nv' => '00003', 'trang_thai_duyet' => 0],
            ['ma_nv' => '00002', 'trang_thai_duyet' => 1],
        ]);
        $this->allowManagerPermissions();

        self::assertSame(1, app(DashboardService::class)->getPendingDepartmentLeaveCount($manager));
    }

    public function test_non_manager_or_missing_department_returns_null(): void
    {
        $this->allowManagerPermissions();
        self::assertNull(app(DashboardService::class)->getPendingDepartmentLeaveCount($this->actor(['ma_vt' => 5, 'ma_pb' => 2])));
        self::assertNull(app(DashboardService::class)->getPendingDepartmentLeaveCount($this->actor(['ma_vt' => 4, 'ma_pb' => null])));
    }

    public function test_count_uses_the_same_pending_scope_even_for_terminal_employee_rows(): void
    {
        $manager = $this->actor(['ma_vt' => 4, 'ma_pb' => 2]);
        DB::table('nhan_vien')->insert([
            'ma_nv' => '00004',
            'ho_ten' => 'Đã nghỉ việc',
            'ma_vt' => 5,
            'ma_pb' => 2,
            'ma_tt' => 4,
        ]);
        DB::table('nghi_phep')->insert([
            'ma_nv' => '00004',
            'trang_thai_duyet' => 0,
        ]);
        $this->allowManagerPermissions();

        self::assertSame(1, app(DashboardService::class)->getPendingDepartmentLeaveCount($manager));
    }

    public function test_manager_without_read_or_update_cannot_receive_pending_count(): void
    {
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('allows')
                ->andReturnUsing(static fn (NhanVien $actor, NghiPhepPermission|string $permission): bool => (
                    $permission instanceof NghiPhepPermission ? $permission->value : $permission
                ) === NghiPhepPermission::Xem->value);
        });

        self::assertNull(app(DashboardService::class)->getPendingDepartmentLeaveCount($this->actor(['ma_vt' => 4, 'ma_pb' => 2])));
    }

    private function allowManagerPermissions(): void
    {
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('allows')
                ->andReturnUsing(static fn (NhanVien $actor, NghiPhepPermission|string $permission): bool => in_array(
                    $permission instanceof NghiPhepPermission ? $permission->value : $permission,
                    [NghiPhepPermission::Xem->value, NghiPhepPermission::Sua->value],
                    true,
                ));
        });
    }

    private function actor(array $overrides = []): NhanVien
    {
        return NhanVien::fromAuthRow((object) array_replace([
            'ma_nv' => '00001',
            'ho_ten' => 'Trưởng phòng',
            'email' => 'manager@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 4,
            'ma_pb' => 2,
            'ma_tt' => 1,
        ], $overrides));
    }
}
