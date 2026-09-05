<?php

namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface PhanQuyenRepositoryContract
{
    public function permissionsByModule(): array;
    public function permissionIdsForRole(int $maVt): array;
    public function syncRolePermissions(int $maVt, array $permissionIds): void;
    public function accounts(array $filters = []): LengthAwarePaginator;
    public function assignRoles(array $assignments, string $actorMaNv): void;
    public function assignRole(string $maNv, int $maVt, string $actorMaNv): void;
}
