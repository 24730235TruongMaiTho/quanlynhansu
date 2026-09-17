<?php

namespace Tests\Unit\Services;

use App\Models\NhanVien;
use App\Services\DashboardService;
use App\Services\PermissionService;
use Tests\TestCase;

final class DashboardRbacTest extends TestCase
{
    public function test_overview_omits_unauthorized_module_keys_without_querying_module_tables(): void
    {
        $this->mock(PermissionService::class, function ($mock): void {
            $mock->shouldReceive('allows')->atLeast()->once()->andReturnFalse();
        });

        $overview = app(DashboardService::class)->getOverview($this->actor());

        self::assertSame([], $overview);
    }

    private function actor(): NhanVien
    {
        return NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nhân viên',
            'email' => 'employee@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 5,
            'ma_tt' => 1,
        ]);
    }
}
