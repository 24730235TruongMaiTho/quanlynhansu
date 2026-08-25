<?php

namespace App\Contracts;

interface VaiTroServiceContract
{
    public function all(string $keyword = ''): array;
    public function findOrFail(int $maVt): object;
    public function create(array $data): int;
    public function update(int $maVt, array $data): void;
    public function delete(int $maVt): void;
}
