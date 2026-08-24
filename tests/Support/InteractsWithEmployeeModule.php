<?php

namespace Tests\Support;

use App\Enums\NhanVienPermission;
use App\Models\NhanVien;
use App\Services\NhanVienPermissionService;

trait InteractsWithEmployeeModule
{
    protected function enableEmployeeModule(): void
    {
        config()->set('nhanvien.enabled', true);
    }

    /**
     * Authenticate a deterministic test actor and grant only the requested symbols.
     * The permission service is mocked at the Gate boundary; controller target guards remain real.
     *
     * @param array<int, string|NhanVienPermission> $symbols
     */
    protected function actingAsEmployeeWithPermissions(array $symbols): NhanVien
    {
        $this->enableEmployeeModule();
        $employee = NhanVien::fromAuthProcedureRow((object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ky_hieu' => 'DANG_LAM',
        ]);
        $allowedSymbols = array_values(array_filter(array_map(
            static fn (mixed $symbol): ?string => $symbol instanceof NhanVienPermission
                ? $symbol->value
                : (is_string($symbol) ? $symbol : null),
            $symbols,
        )));

        $this->actingAs($employee);
        $this->mock(NhanVienPermissionService::class, function ($mock) use ($employee, $allowedSymbols): void {
            $mock->shouldReceive('allows')
                ->withArgs(static function (mixed $candidate, mixed $permission) use ($employee): bool {
                    return $candidate instanceof NhanVien
                        && $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && $permission instanceof NhanVienPermission;
                })
                ->andReturnUsing(static function (NhanVien $candidate, NhanVienPermission $permission) use ($employee, $allowedSymbols): bool {
                    return $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && in_array($permission->value, $allowedSymbols, true);
                });
        });

        return $employee;
    }
}
