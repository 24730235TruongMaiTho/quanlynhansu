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
    public function test_only_department_permission_ids_are_allowed(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('permissionIds')->once()->with('NV001')->andReturn([201, 203, 101]);
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 5,
            'ma_tt' => 2,
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
        $repository->shouldReceive('permissionIds')->once()->with('NV001')->andThrow(new \RuntimeException('db failure'));
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => 'NV001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => 'hash', 'ma_vt' => 5, 'ma_tt' => 2,
        ]);

        $this->assertFalse((new PhongBanPermissionService($repository))->allows($employee, PhongBanPermission::Xem));
    }
}
