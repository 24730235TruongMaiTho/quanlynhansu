<?php

namespace App\Services;

use App\Contracts\PhanQuyenRepositoryContract;
use App\Contracts\PhanQuyenServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;

final class PhanQuyenService implements PhanQuyenServiceContract
{
    public function __construct(private PhanQuyenRepositoryContract $repository) {}
    public function permissionsByModule(): array { return $this->repository->permissionsByModule(); }
    public function permissionIdsForRole(int $maVt): array { return $this->repository->permissionIdsForRole($maVt); }
    public function syncRolePermissions(int $maVt, array $permissionIds): void { $this->repository->syncRolePermissions($maVt, $permissionIds); }
    public function accounts(array $filters = []): LengthAwarePaginator { return $this->repository->accounts($filters); }
    public function assignRoles(array $assignments, string $actorMaNv): void { $this->repository->assignRoles($assignments, $actorMaNv); }
    public function assignRole(string $maNv, int $maVt, string $actorMaNv): void { $this->repository->assignRole($maNv, $maVt, $actorMaNv); }
}
