<?php

namespace App\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\PhongBanPermission;
use App\Models\NhanVien;
use Throwable;

class PhongBanPermissionService
{
    /** @var array<string, ?array<string, true>> */
    private array $permissionSets = [];

    public function __construct(private NhanVienRepositoryContract $repository) {}

    public function allows(NhanVien $employee, PhongBanPermission $permission): bool
    {
        $maNv = $employee->getAuthIdentifier();

        if (! is_string($maNv) || preg_match('/\ANV[0-9]{3}\z/', $maNv) !== 1) {
            return false;
        }

        if (! array_key_exists($maNv, $this->permissionSets)) {
            $this->permissionSets[$maNv] = $this->loadPermissionSet($maNv);
        }

        $permissionSet = $this->permissionSets[$maNv];

        return $permissionSet !== null && isset($permissionSet[$permission->value]);
    }

    /** @return array<string, true>|null */
    private function loadPermissionSet(string $maNv): ?array
    {
        try {
            $symbols = $this->repository->permissionSymbols($maNv);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($symbols)) {
            return null;
        }

        $knownSymbols = array_fill_keys(array_map(
            static fn (PhongBanPermission $permission): string => $permission->value,
            PhongBanPermission::cases(),
        ), true);
        $set = [];

        foreach ($symbols as $symbol) {
            if (is_string($symbol) && isset($knownSymbols[$symbol])) {
                $set[$symbol] = true;
            }
        }

        return $set;
    }
}
