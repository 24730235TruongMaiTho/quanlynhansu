<?php

namespace Tests\Support;

use App\Enums\PhongBanPermission;
use App\Models\NhanVien;
use App\Services\PhongBanPermissionService;

trait InteractsWithPhongBanModule
{
    /**
     * Authenticate a deterministic actor and grant only the requested department symbols.
     * The permission service is mocked at the Gate boundary; the HTTP contract stays real.
     *
     * @param array<int, string|PhongBanPermission> $symbols
     */
    protected function actingAsPhongBanEmployee(array $symbols): NhanVien
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ky_hieu' => 'DANG_LAM',
        ]);
        $allowedSymbols = array_values(array_filter(array_map(
            static fn (mixed $symbol): ?string => $symbol instanceof PhongBanPermission
                ? $symbol->value
                : (is_string($symbol) ? $symbol : null),
            $symbols,
        )));

        $this->actingAs($employee);
        $this->mock(PhongBanPermissionService::class, function ($mock) use ($employee, $allowedSymbols): void {
            $mock->shouldReceive('allows')
                ->withArgs(static function (mixed $candidate, mixed $permission) use ($employee): bool {
                    return $candidate instanceof NhanVien
                        && $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && $permission instanceof PhongBanPermission;
                })
                ->andReturnUsing(static function (NhanVien $candidate, PhongBanPermission $permission) use ($employee, $allowedSymbols): bool {
                    return $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && in_array($permission->value, $allowedSymbols, true);
                });
        });

        return $employee;
    }
}
