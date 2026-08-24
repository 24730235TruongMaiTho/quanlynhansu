<?php

namespace App\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\PhongBanPermission;
use App\Models\NhanVien;
use Throwable;

class PhongBanPermissionService
{
    /** @var array<string, ?array<int, true>> */
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

        return $permissionSet !== null && isset($permissionSet[$permission->id()]);
    }

    /** @return array<int, true>|null */
    private function loadPermissionSet(string $maNv): ?array
    {
        try {
            $ids = $this->repository->permissionIds($maNv);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($ids)) {
            return null;
        }

        $knownIds = array_fill_keys(array_map(
            static fn (PhongBanPermission $permission): int => $permission->id(),
            PhongBanPermission::cases(),
        ), true);
        $set = [];

        foreach ($ids as $id) {
            if ((! is_int($id) && ! (is_string($id) && ctype_digit($id)))) {
                continue;
            }

            $id = (int) $id;
            if (isset($knownIds[$id])) {
                $set[$id] = true;
            }
        }

        return $set;
    }
}
