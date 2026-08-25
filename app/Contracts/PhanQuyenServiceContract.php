<?php

namespace App\Contracts;

interface PhanQuyenServiceContract
{
    public function permissionsByModule(): array;
    public function permissionIdsForRole(int $maVt): array;
    public function syncRolePermissions(int $maVt, array $permissionIds): void;
    public function accounts(string $keyword = ''): array;
    public function assignRole(string $maNv, int $maVt, string $actorMaNv): void;
}
