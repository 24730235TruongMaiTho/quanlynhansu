<?php

namespace Tests\Integration\MariaDb;

use App\Support\DisposableMariaDbGuard;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\EmployeeDependencyFixture;

class EmployeeLifecycleConcurrencyTest extends MariaDbTestCase
{
    private int $department;

    private int $position;

    private int $workingStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_002_read_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_003_create_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_004_update_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql'));
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Concurrency fixture')");
        $this->department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Concurrency position', 0.10)");
        $this->position = (int) $this->pdo()->lastInsertId();
        $this->workingStatus = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
        )->fetchColumn();
        $this->createEmployee();
        (new EmployeeDependencyFixture($this->pdo()))->add('NV001', 'luong');
    }

    public function test_worker_rejects_missing_or_production_target_before_connection(): void
    {
        foreach ([null, 'quan_ly_nhan_su'] as $database) {
            $environment = $this->workerEnvironment('lifecycle');
            if ($database === null) {
                unset($environment['MARIADB_TEST_DATABASE']);
            } else {
                $environment['MARIADB_TEST_DATABASE'] = $database;
            }
            $environment['MARIADB_TEST_HOST'] = '203.0.113.254';
            $process = $this->newWorker($environment);
            $process->run();

            $this->assertFalse($process->isSuccessful());
            $this->assertSame('WORKER_FAILED', json_decode($process->getErrorOutput(), true)['error'] ?? null);
        }
    }

    public function test_update_lock_releases_then_lifecycle_commits_termination(): void
    {
        $holdReady = $this->marker('hold-ready-');
        $lifecycleReady = $this->marker('lifecycle-ready-');
        $barrier = $this->marker('release-');
        $environment = $this->workerEnvironment('hold_update', $holdReady, $barrier);
        $hold = $this->newWorker($environment);
        $hold->start();
        $this->waitForMarker($holdReady, $hold);

        $lifecycle = $this->newWorker($this->workerEnvironment('lifecycle', $lifecycleReady));
        $lifecycle->start();
        $lifecycleConnectionId = $this->waitForMarker($lifecycleReady, $lifecycle);
        $this->waitForLockWait($lifecycleConnectionId);
        file_put_contents($barrier, 'go');
        $hold->wait();
        $lifecycle->wait();

        $this->assertTrue($hold->isSuccessful(), $hold->getErrorOutput());
        $this->assertTrue($lifecycle->isSuccessful(), $lifecycle->getErrorOutput());
        $this->assertSame('TERMINATED', json_decode($lifecycle->getOutput(), true)['action'] ?? null);
        $this->assertSame('DA_NGHI', $this->pdo()->query(
            "SELECT tt.ky_hieu FROM nhan_vien nv JOIN trang_thai_lam_viec tt ON tt.ma_tt = nv.ma_tt WHERE nv.ma_nv = 'NV001'"
        )->fetchColumn());
        $this->assertSame('2026-08-20', $this->pdo()->query("SELECT ngay_nghi_viec FROM nhan_vien WHERE ma_nv = 'NV001'")->fetchColumn());
    }

    public function test_lifecycle_lock_makes_concurrent_profile_update_fail_invariant(): void
    {
        $lifecycleReady = $this->marker('lifecycle-hold-ready-');
        $updateReady = $this->marker('update-ready-');
        $barrier = $this->marker('release-lifecycle-');
        $lifecycle = $this->newWorker($this->workerEnvironment('hold_lifecycle', $lifecycleReady, $barrier));
        $lifecycle->start();
        $this->waitForMarker($lifecycleReady, $lifecycle);

        $update = $this->newWorker($this->workerEnvironment('update', $updateReady));
        $update->start();
        $updateConnectionId = $this->waitForMarker($updateReady, $update);
        $this->waitForLockWait($updateConnectionId);
        file_put_contents($barrier, 'go');
        $lifecycle->wait();
        $update->wait();

        $this->assertTrue($lifecycle->isSuccessful(), $lifecycle->getErrorOutput());
        $this->assertFalse($update->isSuccessful());
        $this->assertSame('NV_STATUS_MISSING', json_decode($update->getErrorOutput(), true)['error'] ?? null);
        $this->assertSame('DA_NGHI', $this->pdo()->query(
            "SELECT tt.ky_hieu FROM nhan_vien nv JOIN trang_thai_lam_viec tt ON tt.ma_tt = nv.ma_tt WHERE nv.ma_nv = 'NV001'"
        )->fetchColumn());
        $this->assertSame('2026-08-20', $this->pdo()->query("SELECT ngay_nghi_viec FROM nhan_vien WHERE ma_nv = 'NV001'")->fetchColumn());
    }

    public function test_worker_source_is_fail_closed_and_does_not_fallback_to_db_database(): void
    {
        $source = file_get_contents(base_path('tests/Support/MariaDbEmployeeLifecycleWorker.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString("lifecycleRequired('MARIADB_TEST_DATABASE')", $source);
        $this->assertStringNotContainsString('DB_DATABASE', $source);
        $this->assertStringContainsString('DisposableMariaDbGuard::assertSafeDatabaseName', $source);
    }

    public function test_acceptance_dependency_cli_rejects_invalid_preflight_inputs_before_pdo(): void
    {
        $source = file_get_contents(base_path('tests/Support/PrepareEmployeeAcceptanceDependency.php'));
        $this->assertIsString($source);
        $this->assertDoesNotMatchRegularExpression('/\bDB_(?:HOST|PORT|USERNAME|PASSWORD|DATABASE)\b/', $source);

        $cases = [
            'missing database' => static function (array &$environment): void {
                // Symfony Process inherits the parent environment; false explicitly removes
                // this required variable so the worker cannot accidentally borrow a database.
                $environment['MARIADB_TEST_DATABASE'] = false;
            },
            'production database' => static function (array &$environment): void {
                $environment['MARIADB_TEST_DATABASE'] = 'quan_ly_nhan_su';
            },
            'invalid employee code' => static function (array &$environment): void {
                $environment['EMPLOYEE_ACCEPTANCE_MA_NV'] = 'NV01';
            },
            'invalid dependency' => static function (array &$environment): void {
                $environment['EMPLOYEE_ACCEPTANCE_DEPENDENCY'] = 'users';
            },
        ];

        foreach ($cases as $label => $mutate) {
            $environment = $this->acceptanceEnvironment();
            $environment['MARIADB_TEST_HOST'] = '203.0.113.254';
            $mutate($environment);
            $process = $this->newAcceptanceProcess($environment);
            $process->run();

            $this->assertSame(1, $process->getExitCode(), $label);
            $this->assertFalse($process->isSuccessful(), $label);
            $this->assertSame(
                ['ok' => false, 'error' => 'WORKER_FAILED'],
                json_decode($process->getErrorOutput(), true),
                $label,
            );
        }
    }

    public function test_acceptance_dependency_cli_can_mutate_only_the_guarded_disposable_fixture(): void
    {
        $environment = $this->acceptanceEnvironment();
        $environment['EMPLOYEE_ACCEPTANCE_DEPENDENCY'] = 'cham_cong';
        $process = $this->newAcceptanceProcess($environment);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame(['ok' => true], json_decode($process->getOutput(), true));
        $this->assertGreaterThanOrEqual(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM cham_cong WHERE ma_nv = 'NV001'"
        )->fetchColumn());
    }

    private function createEmployee(): void
    {
        $this->pdo()->exec('SET @nv_ma = NULL');
        $statement = $this->pdo()->prepare(
            'CALL sp_nhan_vien_them(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @nv_ma)'
        );
        $statement->execute([
            'Concurrency employee', '1990-01-01', 1, '0901234567', 'concurrency@example.test', '2026-08-12',
            $this->department, $this->position, 'Kinh', '001200000001', 'Fixture', 'Đại học',
            $this->workingStatus, 'original-hash', null,
        ]);
        $statement->closeCursor();
        $this->assertSame('NV001', $this->pdo()->query('SELECT @nv_ma')->fetchColumn());
    }

    private function workerEnvironment(string $operation, ?string $ready = null, ?string $barrier = null): array
    {
        $testEnvironment = DisposableMariaDbGuard::environment();
        $ready ??= $this->marker('ready-');

        return [
            'MARIADB_TEST_ENABLED' => '1',
            'MARIADB_TEST_HOST' => $testEnvironment['host'],
            'MARIADB_TEST_PORT' => $testEnvironment['port'],
            'MARIADB_TEST_USERNAME' => $testEnvironment['username'],
            'MARIADB_TEST_PASSWORD' => $testEnvironment['password'],
            'MARIADB_TEST_DATABASE' => $this->databaseName(),
            'MARIADB_TEST_OPERATION' => $operation,
            'MARIADB_TEST_MA_NV' => 'NV001',
            'MARIADB_TEST_DATE' => '2026-08-20',
            'MARIADB_TEST_READY' => $ready,
            ...($barrier === null ? [] : ['MARIADB_TEST_BARRIER' => $barrier]),
            'DB_DATABASE' => 'quan_ly_nhan_su',
            'DB_HOST' => '203.0.113.253',
        ];
    }

    private function newWorker(array $environment): Process
    {
        return new Process(
            [PHP_BINARY, base_path('tests/Support/MariaDbEmployeeLifecycleWorker.php')],
            base_path(),
            $environment,
            timeout: 40,
        );
    }

    private function acceptanceEnvironment(): array
    {
        $testEnvironment = DisposableMariaDbGuard::environment();

        return [
            'MARIADB_TEST_ENABLED' => '1',
            'MARIADB_TEST_HOST' => $testEnvironment['host'],
            'MARIADB_TEST_PORT' => $testEnvironment['port'],
            'MARIADB_TEST_USERNAME' => $testEnvironment['username'],
            'MARIADB_TEST_PASSWORD' => $testEnvironment['password'],
            'MARIADB_TEST_DATABASE' => $this->databaseName(),
            'EMPLOYEE_ACCEPTANCE_MA_NV' => 'NV001',
            'EMPLOYEE_ACCEPTANCE_DEPENDENCY' => 'luong',
            'DB_DATABASE' => 'quan_ly_nhan_su',
            'DB_HOST' => '203.0.113.253',
        ];
    }

    private function newAcceptanceProcess(array $environment): Process
    {
        return new Process(
            [PHP_BINARY, base_path('tests/Support/PrepareEmployeeAcceptanceDependency.php')],
            base_path(),
            $environment,
            timeout: 20,
        );
    }

    private function marker(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to reserve worker marker.');
        }
        unlink($path);

        return $path;
    }

    private function waitForMarker(string $marker, Process $process): int
    {
        $deadline = microtime(true) + 15;
        while (! is_file($marker)) {
            if (! $process->isRunning()) {
                throw new RuntimeException('Worker exited before ready handshake.');
            }
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Worker ready handshake timed out.');
            }
            usleep(10_000);
        }

        $contents = file_get_contents($marker);
        if (! is_string($contents) || preg_match('/\Aready:(\d+)\z/', $contents, $matches) !== 1) {
            throw new RuntimeException('Worker ready marker did not contain a connection id.');
        }

        return (int) $matches[1];
    }

    private function waitForLockWait(int $waitingConnectionId): void
    {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                config('database.connections.employee_test.host'),
                config('database.connections.employee_test.port'),
                $this->databaseName(),
            ),
            config('database.connections.employee_test.username'),
            config('database.connections.employee_test.password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $sql = <<<'SQL'
SELECT COUNT(*)
FROM information_schema.INNODB_LOCK_WAITS waiting
JOIN information_schema.INNODB_TRX transaction_waiting
  ON transaction_waiting.trx_id = waiting.requesting_trx_id
WHERE transaction_waiting.trx_mysql_thread_id = ?
SQL;
        $deadline = microtime(true) + 15;
        do {
            $statement = $pdo->prepare($sql);
            $statement->execute([$waitingConnectionId]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        $this->fail("MariaDB did not observe a lock wait for connection {$waitingConnectionId}.");
    }
}
