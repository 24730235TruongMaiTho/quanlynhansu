<?php

namespace Tests\Support;

use App\Contracts\PermissionDefinitionContract;
use App\Models\NhanVien;
use App\Services\PermissionService;

trait InteractsWithChucVuModule
{
    /** @param array<int, string> $symbols */
    protected function actingAsChucVuEmployee(array $symbols): NhanVien
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn Văn An',
            'email' => 'an.nguyen@company.com',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
        ]);

        $this->actingAs($employee);
        $this->mock(PermissionService::class, function ($mock) use ($employee, $symbols): void {
            $mock->shouldReceive('canSeeModule')
                ->withArgs(static function (mixed $candidate, mixed $module) use ($employee): bool {
                    return $candidate instanceof NhanVien
                        && $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && is_string($module);
                })
                ->andReturnUsing(static function (NhanVien $candidate, string $module) use ($employee, $symbols): bool {
                    $viewSymbol = match ($module) {
                        'ChucVu' => 'ChucVu.Read',
                        'PhongBan' => 'PhongBan.Read',
                        'NhanVien' => 'NhanVien.Read',
                        default => null,
                    };

                    return $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && $viewSymbol !== null
                        && in_array($viewSymbol, $symbols, true);
                });
            $mock->shouldReceive('allows')
                ->withArgs(static function (mixed $candidate, mixed $permission) use ($employee): bool {
                    return $candidate instanceof NhanVien
                        && $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && ($permission instanceof PermissionDefinitionContract || is_string($permission));
                })
                ->andReturnUsing(static function (NhanVien $candidate, PermissionDefinitionContract|string $permission) use ($employee, $symbols): bool {
                    $ability = $permission instanceof PermissionDefinitionContract
                        ? $permission->symbol()
                        : $permission;

                    return $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && in_array($ability, $symbols, true);
                });
        });

        return $employee;
    }
}
