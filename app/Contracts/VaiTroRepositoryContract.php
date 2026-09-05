<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VaiTroRepositoryContract
{
    public function all(string $keyword = ''): array;
    public function paginate(array $filters): LengthAwarePaginator;
    public function find(int $maVt): ?object;
    public function create(array $data): int;
    public function update(int $maVt, array $data): void;
    public function delete(int $maVt): void;
}
