<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\PhongBanPermission;
use App\Models\NhanVien;
use App\Services\PhongBanPermissionService;
use Mockery;
use Tests\TestCase;

class PhongBanPermissionServiceTest extends TestCase
{
    public function test_only_canonical_department_symbols_are_allowed_and_legacy_symbol_is_ignored(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('permissionSymbols')->once()->with('NV001')->andReturn([
            'PHONG_BAN_XEM',
            'PHONG_BAN_SUA',
            'PB_VIEW',
        ]);
        $employee = NhanVien::fromAuthProcedureRow((object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 2,
            'ky_hieu' => 'DANG_LAM',
        ]);

        $service = new PhongBanPermissionService($repository);

        $this->assertTrue($service->allows($employee, PhongBanPermission::Xem));
        $this->assertTrue($service->allows($employee, PhongBanPermission::Sua));
        $this->assertFalse($service->allows($employee, PhongBanPermission::Tao));
        $this->assertFalse($service->allows($employee, PhongBanPermission::Xoa));
    }

    public function test_repository_failure_fails_closed_for_department_gate(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('permissionSymbols')->once()->with('NV001')->andThrow(new \RuntimeException('db failure'));
        $employee = NhanVien::fromAuthProcedureRow((object) [
            'ma_nv' => 'NV001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => 'hash', 'ma_vt' => 2, 'ky_hieu' => 'DANG_LAM',
        ]);

        $this->assertFalse((new PhongBanPermissionService($repository))->allows($employee, PhongBanPermission::Xem));
    }
}
