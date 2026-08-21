<?php

namespace Tests\Integration\MariaDb;

use RuntimeException;

class DisposableMariaDbSmokeTest extends MariaDbTestCase
{
    private static ?string $previousDatabase = null;

    private static ?string $previousDefaultConnection = null;

    public function test_fixture_creates_legacy_employee_schema_in_a_disposable_database(): void
    {
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));

        $table = $this->pdo()->query("SHOW TABLES LIKE 'nhan_vien'")->fetchColumn();
        $this->assertSame('nhan_vien', $table);
        $this->assertSame('vw_danh_sach_nhan_vien_chi_tiet', $this->pdo()
            ->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchColumn());

        self::$previousDatabase = $this->databaseName();
        self::$previousDefaultConnection = $this->previousDefaultConnection();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Synthetic failure path after fixture setup.');

        throw new RuntimeException('Synthetic failure path after fixture setup.');
    }

    public function test_previous_database_was_dropped_and_default_was_restored(): void
    {
        $this->assertNotNull(self::$previousDatabase);
        $this->assertSame('sqlite', self::$previousDefaultConnection);

        $statement = $this->pdo()->prepare(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
        );
        $statement->execute([self::$previousDatabase]);
        $this->assertFalse($statement->fetchColumn());
    }
}
