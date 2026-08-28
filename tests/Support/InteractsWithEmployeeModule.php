<?php

namespace Tests\Support;

use App\Enums\NhanVienPermission;
use App\Enums\ChamCongPermission;
use App\Enums\NghiPhepPermission;
use App\Enums\LuongPermission;
use App\Contracts\PermissionDefinitionContract;
use App\Models\NhanVien;
use App\Services\PermissionService;

trait InteractsWithEmployeeModule
{
    /**
     * Xác thực một tác nhân kiểm thử cố định và chỉ cấp các symbol được yêu cầu.
     * PermissionService được mô phỏng ở ranh giới Gate để kiểm thử route độc lập.
     *
     * @param array<int, string|PermissionDefinitionContract> $symbols
     */
    protected function actingAsEmployeeWithPermissions(array $symbols, array $actorOverrides = []): NhanVien
    {
        // Giữ actor mặc định khác employee fixture để các test action không
        // vô tình kiểm tra self-delete; test self-delete truyền override rõ ràng.
        $employee = NhanVien::fromAuthRow((object) array_replace([
            'ma_nv' => '00999',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
            'ma_pb' => null,
        ], $actorOverrides));
        $allowedSymbols = array_values(array_filter(array_map(
            static fn (mixed $symbol): ?string => $symbol instanceof PermissionDefinitionContract
                ? $symbol->symbol()
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
                        'ChamCong' => ChamCongPermission::Xem->value,
                        'NghiPhep' => NghiPhepPermission::Xem->value,
                        'Luong' => LuongPermission::Xem->value,
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
