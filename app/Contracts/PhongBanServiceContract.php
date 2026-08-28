<?php

namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface PhongBanServiceContract
{
    /** @return list<object> */
    public function all(): array;

    public function paginate(array $filters): LengthAwarePaginator;

    public function findOrFail(int $maPb): object;

    public function create(string $tenPb): void;

    public function update(int $maPb, string $tenPb): void;

    public function delete(int $maPb): void;
}
