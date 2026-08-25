<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HopDongServiceContract
{
    public function paginate(array $filters): LengthAwarePaginator;
    public function findOrFail(int $maHd): object;
    public function formOptions(): array;
    public function create(array $data): int;
    public function update(int $maHd, array $data): void;
    public function delete(int $maHd): void;
}
