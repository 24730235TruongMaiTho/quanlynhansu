<?php

namespace Tests\Support;

use App\Support\DisposableMariaDbGuard;
use Illuminate\Support\Facades\DB;
use PDO;

trait CreatesDisposableMariaDb
{
    private ?string $databaseName = null;

    private ?string $previousDefaultConnection = null;

    private ?PDO $adminPdo = null;

    protected function createDisposableMariaDb(): void
    {
        $testEnv = DisposableMariaDbGuard::environment();
        $this->databaseName = 'quan_ly_nhan_su_employee_test_'.bin2hex(random_bytes(6));
        DisposableMariaDbGuard::assertSafeDatabaseName($this->databaseName);

        $this->previousDefaultConnection = DB::getDefaultConnection();
        $this->adminPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $testEnv['host'], $testEnv['port']),
            $testEnv['username'],
            $testEnv['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->adminPdo->exec("SET time_zone = '+07:00'");
        $this->adminPdo->exec(sprintf(
            'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $this->databaseName
        ));

        config()->set('database.connections.employee_test', [
            'driver' => 'mysql',
            'host' => $testEnv['host'],
            'port' => $testEnv['port'],
            'database' => $this->databaseName,
            'username' => $testEnv['username'],
            'password' => $testEnv['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'timezone' => '+07:00',
        ]);
        DB::purge('employee_test');
        DB::setDefaultConnection('employee_test');
    }

    protected function destroyDisposableMariaDb(): void
    {
        $databaseName = $this->databaseName;

        try {
            DB::disconnect('employee_test');
            DB::purge('employee_test');
        } finally {
            try {
                if ($this->previousDefaultConnection !== null) {
                    DB::setDefaultConnection($this->previousDefaultConnection);
                }
            } finally {
                if ($databaseName !== null) {
                    DisposableMariaDbGuard::assertSafeDatabaseName($databaseName);
                    $this->adminPdo?->exec(sprintf('DROP DATABASE `%s`', $databaseName));
                }

                $this->adminPdo = null;
                $this->databaseName = null;
                $this->previousDefaultConnection = null;
            }
        }
    }

    public function databaseName(): string
    {
        if ($this->databaseName === null) {
            throw new \RuntimeException('Disposable MariaDB has not been created.');
        }

        return $this->databaseName;
    }

    public function previousDefaultConnection(): string
    {
        if ($this->previousDefaultConnection === null) {
            throw new \RuntimeException('Default connection has not been captured.');
        }

        return $this->previousDefaultConnection;
    }
}
