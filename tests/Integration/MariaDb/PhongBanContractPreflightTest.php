<?php

namespace Tests\Integration\MariaDb;

use PDOException;

class PhongBanContractPreflightTest extends MariaDbTestCase
{
    public function test_rollout_refuses_untrimmed_department_data_before_unique_ddl(): void
    {
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES (' Untrimmed')");

        try {
            $this->runSql(base_path('database/sql/department/2026_08_22_001_department_contract.sql'));
            $this->fail('The department contract must fail closed before DDL.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('PB_CONTRACT_PREFLIGHT_FAILED', $exception->getMessage());
        }

        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phong_ban'
               AND COLUMN_NAME = 'ten_pb' AND NON_UNIQUE = 0"
        )->fetchColumn());
    }

    public function test_rollout_refuses_collation_duplicate_department_data_before_unique_ddl(): void
    {
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật'), ('Kỹ thuật')");

        try {
            $this->runSql(base_path('database/sql/department/2026_08_22_001_department_contract.sql'));
            $this->fail('The department contract must reject duplicate names before DDL.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('PB_CONTRACT_PREFLIGHT_FAILED', $exception->getMessage());
        }

        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phong_ban'
               AND COLUMN_NAME = 'ten_pb' AND NON_UNIQUE = 0"
        )->fetchColumn());
    }

    public function test_rollout_refuses_empty_department_name_before_unique_ddl_without_rewriting_data(): void
    {
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('   ')");

        try {
            $this->runSql(base_path('database/sql/department/2026_08_22_001_department_contract.sql'));
            $this->fail('The department contract must reject empty names before DDL.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('PB_CONTRACT_PREFLIGHT_FAILED', $exception->getMessage());
        }

        $this->assertSame('   ', $this->pdo()->query(
            'SELECT ten_pb FROM phong_ban WHERE ma_pb = 1'
        )->fetchColumn());
        $this->assertNoDepartmentNameUniqueConstraint();
    }

    public function test_rollout_refuses_null_department_name_before_unique_ddl_without_rewriting_data(): void
    {
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->pdo()->exec('ALTER TABLE phong_ban MODIFY ten_pb NVARCHAR(100) NULL');
        $this->pdo()->exec('INSERT INTO phong_ban (ten_pb) VALUES (NULL)');

        try {
            $this->runSql(base_path('database/sql/department/2026_08_22_001_department_contract.sql'));
            $this->fail('The department contract must reject NULL names before DDL.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('PB_CONTRACT_PREFLIGHT_FAILED', $exception->getMessage());
        }

        $this->assertNull($this->pdo()->query(
            'SELECT ten_pb FROM phong_ban WHERE ma_pb = 1'
        )->fetchColumn());
        $this->assertNoDepartmentNameUniqueConstraint();
    }

    private function assertNoDepartmentNameUniqueConstraint(): void
    {
        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'phong_ban'
               AND COLUMN_NAME = 'ten_pb' AND NON_UNIQUE = 0"
        )->fetchColumn());
    }
}
