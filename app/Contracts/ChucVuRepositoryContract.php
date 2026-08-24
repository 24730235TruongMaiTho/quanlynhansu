<?php

namespace App\Contracts;

interface ChucVuRepositoryContract
{
    /** @return list<object> */
    public function all(): array;

    public function find(int $maCv): ?object;

    public function create(string $tenCv, string $heSoPhuCap): void;

    public function update(int $maCv, string $tenCv, string $heSoPhuCap): void;

    public function delete(int $maCv): void;
}
