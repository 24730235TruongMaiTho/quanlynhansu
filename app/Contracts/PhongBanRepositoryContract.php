<?php

namespace App\Contracts;

interface PhongBanRepositoryContract
{
    /** @return list<object> */
    public function all(): array;

    public function find(int $maPb): ?object;

    public function create(string $tenPb): void;

    public function update(int $maPb, string $tenPb): void;

    public function delete(int $maPb): void;
}
