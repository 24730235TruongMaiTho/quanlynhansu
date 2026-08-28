<?php

namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface ChucVuServiceContract
{
    /** @return list<object> */
    public function all(): array;

    public function paginate(array $filters): LengthAwarePaginator;

    public function findOrFail(int $maCv): object;

    public function create(string $tenCv, string $heSoPhuCap): void;

    public function update(int $maCv, string $tenCv, string $heSoPhuCap): void;

    public function delete(int $maCv): void;
}
