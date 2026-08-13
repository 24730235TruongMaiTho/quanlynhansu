<?php

namespace Tests\Integration\MariaDb;

use Illuminate\Support\Facades\DB;
use PDO;
use Tests\Support\CreatesDisposableMariaDb;
use Tests\Support\SqlScriptRunner;
use Tests\TestCase;

abstract class MariaDbTestCase extends TestCase
{
    use CreatesDisposableMariaDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('', config('database.connections.mysql.url'));
        $this->assertSame('', config('database.connections.mysql.unix_socket'));
        $this->assertArrayNotHasKey('mysql', DB::getConnections());
        $this->assertArrayNotHasKey('mariadb', DB::getConnections());
        $this->assertArrayNotHasKey('employee_test', DB::getConnections());

        $this->createDisposableMariaDb();
    }

    protected function tearDown(): void
    {
        try {
            $this->destroyDisposableMariaDb();
        } finally {
            parent::tearDown();
        }
    }

    public function pdo(): PDO
    {
        return DB::connection('employee_test')->getPdo();
    }

    public function runSql(string $path): void
    {
        SqlScriptRunner::run($this->pdo(), $path);
    }
}
