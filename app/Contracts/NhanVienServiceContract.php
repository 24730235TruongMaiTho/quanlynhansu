<?php

namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface NhanVienServiceContract
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function paginateForAttendance(array $filters): LengthAwarePaginator;

    public function findOrFail(string $maNv): object;

    public function create(array $validated): string;

    public function update(string $maNv, array $validated): object;

    /**
     * @return array{phong_ban: array, chuc_vu: array, trang_thai: array}
     */
    public function lookups(): array;
}
