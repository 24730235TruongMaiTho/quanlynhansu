<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HopDongRepositoryContract
{
    public function paginate(array $filters, int $perPage, int $warningDays): LengthAwarePaginator;
    public function find(int $maHd): ?object;
    public function employees(): array;
    public function types(): array;
    public function create(array $data): int;
    public function update(int $maHd, array $data): void;
    public function delete(int $maHd): void;
}
