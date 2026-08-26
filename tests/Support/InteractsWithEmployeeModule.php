<?php

namespace Tests\Support;

use App\Enums\NhanVienPermission;
use App\Contracts\PermissionDefinitionContract;
use App\Models\NhanVien;
use App\Services\PermissionService;

trait InteractsWithEmployeeModule
{
    /**
     * Authenticate a deterministic test actor and grant only the requested symbols.
     * The permission service is mocked at the Gate boundary; controller target guards remain real.
     *
     * @param array<int, string|NhanVienPermission> $symbols
     */
    protected function actingAsEmployeeWithPermissions(array $symbols): NhanVien
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ma_tt' => 2,
        ]);
        $allowedSymbols = array_values(array_filter(array_map(
            static fn (mixed $symbol): ?string => $symbol instanceof NhanVienPermission
                ? $symbol->value
                : (is_string($symbol) ? $symbol : null),
            $symbols,
        )));

        $this->actingAs($employee);
        $this->mock(PermissionService::class, function ($mock) use ($employee, $allowedSymbols): void {
            $mock->shouldReceive('canSeeModule')
                ->withArgs(static function (mixed $candidate, mixed $module) use ($employee): bool {
                    return $candidate instanceof NhanVien
                        && $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && is_string($module);
                })
                ->andReturnUsing(static function (NhanVien $candidate, string $module) use ($employee, $allowedSymbols): bool {
                    $viewSymbol = match ($module) {
                        'NhanVien' => NhanVienPermission::Xem->value,
                        'PhongBan' => \App\Enums\PhongBanPermission::Xem->value,
                        default => null,
                    };

                    return $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && $viewSymbol !== null
                        && in_array($viewSymbol, $allowedSymbols, true);
                });
            $mock->shouldReceive('allows')
                ->withArgs(static function (mixed $candidate, mixed $permission) use ($employee): bool {
                    return $candidate instanceof NhanVien
                        && $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && ($permission instanceof PermissionDefinitionContract || is_string($permission));
                })
                ->andReturnUsing(static function (NhanVien $candidate, PermissionDefinitionContract|string $permission) use ($employee, $allowedSymbols): bool {
                    return $candidate->getAuthIdentifier() === $employee->getAuthIdentifier()
                        && in_array($permission instanceof PermissionDefinitionContract ? $permission->symbol() : $permission, $allowedSymbols, true);
                });
        });

        return $employee;
    }
}
