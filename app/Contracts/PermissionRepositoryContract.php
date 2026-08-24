<?php

namespace App\Contracts;

interface PermissionRepositoryContract
{
    /**
     * Return the permission catalog rows granted to an employee's role.
     *
     * The projection is intentionally kept as scalar data so the service can
     * validate the ID, exact symbol and module against its static registry.
     *
     * @return list<array{ma_quyen: int|string, ky_hieu_quyen: string, module: string}>
     */
    public function permissionsForActor(string $maNv): array;
}
