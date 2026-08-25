<?php

namespace App\Contracts;

interface VaiTroRepositoryContract
{
    public function all(string $keyword = ''): array;
    public function find(int $maVt): ?object;
    public function create(array $data): int;
    public function update(int $maVt, array $data): void;
    public function delete(int $maVt): void;
}
