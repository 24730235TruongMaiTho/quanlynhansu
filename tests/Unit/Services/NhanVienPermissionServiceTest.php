<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\NhanVienPermission;
use App\Models\NhanVien;
use App\Services\NhanVienPermissionService;
use Mockery;
use Tests\TestCase;

class NhanVienPermissionServiceTest extends TestCase
{
    public function test_repository_is_read_once_per_actor_and_cached_for_repeated_checks(): void
    {
        $employee = $this->employee('NV001');
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('permissionSymbols')
            ->once()
            ->with('NV001')
            ->andReturn(['NHAN_VIEN_XEM', 'NHAN_VIEN_SUA']);

        $service = new NhanVienPermissionService($repository);

        $this->assertTrue($service->allows($employee, NhanVienPermission::Xem));
        $this->assertTrue($service->allows($employee, NhanVienPermission::Sua));
        $this->assertFalse($service->allows($employee, NhanVienPermission::Xoa));
    }

    public function test_permission_sets_are_independent_for_distinct_actors(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('permissionSymbols')->once()->with('NV001')->andReturn(['NHAN_VIEN_XEM']);
        $repository->shouldReceive('permissionSymbols')->once()->with('NV002')->andReturn(['NHAN_VIEN_TAO']);
        $service = new NhanVienPermissionService($repository);

        $this->assertTrue($service->allows($this->employee('NV001'), NhanVienPermission::Xem));
        $this->assertFalse($service->allows($this->employee('NV001'), NhanVienPermission::Tao));
        $this->assertTrue($service->allows($this->employee('NV002'), NhanVienPermission::Tao));
        $this->assertFalse($service->allows($this->employee('NV002'), NhanVienPermission::Xem));
    }

    public function test_unknown_or_malformed_repository_symbols_never_grant(): void
    {
        foreach ([
            ['UNKNOWN_PERMISSION'],
            [' NHAN_VIEN_XEM '],
        ] as $symbols) {
            $repository = Mockery::mock(NhanVienRepositoryContract::class);
            $repository->shouldReceive('permissionSymbols')->once()->andReturn($symbols);
            $service = new NhanVienPermissionService($repository);

            $this->assertFalse(
                $service->allows($this->employee('NV001'), NhanVienPermission::Xem),
                'Malformed or unknown symbols must not grant a permission.',
            );
        }
    }

    public function test_valid_employee_symbols_are_evaluated_even_with_unrelated_repository_symbols(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('permissionSymbols')->once()->andReturn(['NHAN_VIEN_XEM', 'NHOM_KHAC_XEM', null]);
        $service = new NhanVienPermissionService($repository);

        $this->assertTrue($service->allows($this->employee('NV001'), NhanVienPermission::Xem));
    }

    public function test_invalid_actor_identifier_fails_closed_without_repository_access(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldNotReceive('permissionSymbols');
        $service = new NhanVienPermissionService($repository);

        $this->assertFalse($service->allows($this->employee('not-an-employee'), NhanVienPermission::Xem));
    }

    private function employee(string $maNv): NhanVien
    {
        return NhanVien::fromAuthProcedureRow((object) [
            'ma_nv' => $maNv,
            'ho_ten' => 'Nguyễn An',
            'email' => $maNv.'@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ky_hieu' => 'DANG_LAM',
        ]);
    }
}
