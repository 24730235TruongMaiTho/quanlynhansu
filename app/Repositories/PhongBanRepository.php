<?php

namespace App\Repositories;

use App\Contracts\PhongBanRepositoryContract;
use App\Support\PhongBanProcedureExceptionMapper;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

final class PhongBanRepository implements PhongBanRepositoryContract
{
    public function __construct(
        private DatabaseManager $database,
        private PhongBanProcedureExceptionMapper $exceptions,
    ) {}

    public function all(): array
    {
        return $this->databaseOperation(function (): array {
            return $this->call($this->connection(), 'CALL sp_phong_ban_danh_sach()', [])[0] ?? [];
        });
    }

    public function find(int $maPb): ?object
    {
        return $this->databaseOperation(function () use ($maPb): ?object {
            $rows = $this->call($this->connection(), 'CALL sp_phong_ban_chi_tiet(?)', [$maPb])[0] ?? [];
            $row = $rows[0] ?? null;

            return is_object($row) ? $row : null;
        });
    }

    public function create(string $tenPb): void
    {
        $this->databaseOperation(function () use ($tenPb): void {
            $this->writeCall($this->connection(), 'CALL sp_phong_ban_them(?)', [$tenPb]);
        });
    }

    public function update(int $maPb, string $tenPb): void
    {
        $this->databaseOperation(function () use ($maPb, $tenPb): void {
            $this->writeCall($this->connection(), 'CALL sp_phong_ban_sua(?, ?)', [$maPb, $tenPb]);
        });
    }

    public function delete(int $maPb): void
    {
        $this->databaseOperation(function () use ($maPb): void {
            $this->writeCall($this->connection(), 'CALL sp_phong_ban_xoa(?)', [$maPb]);
        });
    }

    private function connection(): Connection
    {
        return $this->database->connection();
    }

    private function call(Connection $connection, string $sql, array $bindings): array
    {
        return $connection->selectResultSets($sql, $bindings, false);
    }

    private function writeCall(Connection $connection, string $sql, array $bindings): void
    {
        $this->call($connection, $sql, $bindings);
    }

    private function databaseOperation(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $exception) {
            throw $this->exceptions->map($exception);
        }
    }
}
