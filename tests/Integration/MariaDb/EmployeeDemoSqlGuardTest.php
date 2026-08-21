<?php

namespace Tests\Integration\MariaDb;

use PDOException;
use RuntimeException;

class EmployeeDemoSqlGuardTest extends MariaDbTestCase
{
    public function test_direct_source_without_a_session_marker_fails_before_mutation(): void
    {
        $this->installCanonicalDump();
        $before = $this->demoEmployeeCount();

        try {
            $this->runDemoSql(base_path('database/sql/employee/demo/2026_08_21_001_demo_seed.sql'));
            $this->fail('Direct demo seed unexpectedly succeeded without a session marker.');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('DEMO_SEED_GUARD', $exception->getMessage());
        }

        $this->assertSame($before, $this->demoEmployeeCount());
    }

    public function test_ephemeral_marker_allows_seed_and_cleanup_on_the_same_connection(): void
    {
        $this->installCanonicalDump();
        $this->assertSame(0, $this->demoEmployeeCount());

        $this->createSessionMarker();
        $this->runDemoSql(base_path('database/sql/employee/demo/2026_08_21_001_demo_seed.sql'));
        $this->assertSame(5, $this->demoEmployeeCount());
        $this->assertSame(5, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM dia_chi_nhan_vien WHERE ma_nv IN (
                SELECT ma_nv FROM nhan_vien
                WHERE email LIKE '%@employee.example.test'
            )"
        )->fetchColumn());

        $this->createSessionMarker();
        $this->runDemoSql(base_path('database/sql/employee/demo/2026_08_21_002_demo_cleanup.sql'));
        $this->assertSame(0, $this->demoEmployeeCount());
    }

    private function installCanonicalDump(): void
    {
        $dump = file_get_contents(base_path('quan_ly_nhan_su.session.sql'));
        if ($dump === false) {
            throw new RuntimeException('Unable to read canonical dump.');
        }

        $database = $this->databaseName();
        $patterns = [
            '/^DROP DATABASE IF EXISTS quan_ly_nhan_su;\s*$/m',
            '/^CREATE DATABASE quan_ly_nhan_su CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\s*$/m',
            '/^USE quan_ly_nhan_su;\s*$/m',
        ];
        $replacements = [
            "DROP DATABASE IF EXISTS {$database};",
            "CREATE DATABASE {$database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
            "USE {$database};",
        ];

        foreach ($patterns as $index => $pattern) {
            $dump = preg_replace($pattern, $replacements[$index], $dump, 1, $count);
            if ($dump === null || $count !== 1) {
                throw new RuntimeException('Canonical dump database rewrite failed.');
            }
        }

        $dump = preg_replace('/\/\*.*?\*\//s', '', $dump);
        $dump = preg_replace('/^\s*--.*$/m', '', $dump);
        if ($dump === null) {
            throw new RuntimeException('Canonical dump comment removal failed.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'employee-demo-');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create canonical dump temp file.');
        }

        try {
            if (file_put_contents($tempPath, $dump) === false) {
                throw new RuntimeException('Unable to write canonical dump temp file.');
            }

            $this->runSql($tempPath);
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    private function createSessionMarker(): void
    {
        $token = bin2hex(random_bytes(32));
        $pdo = $this->pdo();
        $pdo->exec('DROP TEMPORARY TABLE IF EXISTS employee_demo_guard');
        $pdo->exec(
            'CREATE TEMPORARY TABLE employee_demo_guard (
                marker_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                database_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
            ) ENGINE=MEMORY'
        );
        $pdo->exec("SET @employee_demo_guard_token = '{$token}'");
        $statement = $pdo->prepare(
            'INSERT INTO employee_demo_guard (marker_id, token, database_name)
             VALUES (1, :token, DATABASE())'
        );
        $statement->execute(['token' => $token]);
    }

    private function runDemoSql(string $path): void
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read demo SQL.');
        }

        $withoutComments = preg_replace('/^\s*--.*$/m', '', $contents);
        if ($withoutComments === null) {
            throw new RuntimeException('Unable to remove demo SQL comments.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'employee-demo-script-');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create demo SQL temp file.');
        }

        try {
            if (file_put_contents($tempPath, $withoutComments) === false) {
                throw new RuntimeException('Unable to write demo SQL temp file.');
            }

            $this->runSql($tempPath);
        } finally {
            if (is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    private function demoEmployeeCount(): int
    {
        return (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien
             WHERE email IN (
                 'demo.admin@employee.example.test',
                 'demo.a@employee.example.test',
                 'demo.b@employee.example.test',
                 'demo.c@employee.example.test',
                 'demo.d@employee.example.test'
             )"
        )->fetchColumn();
    }
}
