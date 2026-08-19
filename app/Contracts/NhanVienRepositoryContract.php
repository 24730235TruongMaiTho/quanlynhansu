<?php

namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface NhanVienRepositoryContract
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function paginateAttendance(array $filters): LengthAwarePaginator;

    public function find(string $maNv): ?object;

    public function create(array $profile, string $passwordHash, ?string $avatarPath): string;

    public function upsertAddress(string $maNv, array $address): void;

    /**
     * @return array{phong_ban: array, chuc_vu: array, trang_thai: array}
     */
    public function lookups(): array;
}
