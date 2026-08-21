<?php

namespace Tests\Integration\MariaDb;

use App\Support\DisposableMariaDbGuard;
use RuntimeException;
use Symfony\Component\Process\Process;

class EmployeeCreateConcurrencyTest extends MariaDbTestCase
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

        $createScript = base_path('database/sql/employee/2026_08_12_003_create_routines.sql');
        if (is_file($createScript)) {
            $this->runSql($createScript);
        }

        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật')");
        $this->department = (int) $this->pdo()->lastInsertId();
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Lập trình viên', 0.20)");
        $this->position = (int) $this->pdo()->lastInsertId();
        $this->workingStatus = (int) $this->pdo()->query(
            "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
        )->fetchColumn();
    }

    public function test_worker_rejects_missing_or_production_database_before_connection(): void
    {
        foreach ([null, 'quan_ly_nhan_su'] as $database) {
            $environment = $this->workerEnvironment($this->profile());
            if ($database === null) {
                unset($environment['MARIADB_TEST_DATABASE']);
            } else {
                $environment['MARIADB_TEST_DATABASE'] = $database;
            }
            $environment['MARIADB_TEST_HOST'] = '203.0.113.254';
            $environment['MARIADB_TEST_BARRIER'] = base_path('composer.json');

            $process = $this->newWorker($environment);
            $process->run();

            $this->assertFalse($process->isSuccessful());
            $this->assertSame('WORKER_FAILED', json_decode($process->getErrorOutput(), true)['error'] ?? null);
        }
    }

    public function test_worker_source_requires_ready_handshake_and_passes_raw_identity_inputs(): void
    {
        $source = file_get_contents(base_path('tests/Support/MariaDbEmployeeCreateWorker.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("requiredEnvironment('MARIADB_TEST_READY')", $source);
        $this->assertStringNotContainsString('strtolower(trim((string) $profile[4]))', $source);
        $this->assertStringNotContainsString('trim((string) $profile[9])', $source);
    }

    public function test_concurrent_creates_receive_unique_consecutive_codes(): void
    {
        [$first, $second] = $this->runRace(
            $this->profile(['email' => 'one@example.test', 'cccd' => '001200000001']),
            $this->profile(['email' => 'two@example.test', 'cccd' => '001200000002']),
        );

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $codes = [$first['ma_nv'], $second['ma_nv']];
        sort($codes);
        $this->assertSame(['NV001', 'NV002'], $codes);
    }

    public function test_email_case_race_commits_exactly_one_normalized_identity(): void
    {
        $results = $this->runRace(
            $this->profile(['email' => ' Race@Example.Test ', 'cccd' => '001200000011']),
            $this->profile(['email' => 'race@example.test', 'cccd' => '001200000012']),
        );

        $this->assertOneCommitAndOneDomainFailure($results, 'NV_EMAIL_DUPLICATE');
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien WHERE email = 'race@example.test'"
        )->fetchColumn());
    }

    public function test_cccd_duplicate_race_commits_exactly_one_identity(): void
    {
        // p_cccd is VARCHAR(12): race the exact canonical interface value.
        $results = $this->runRace(
            $this->profile(['email' => 'cccd-one@example.test', 'cccd' => '001200000021']),
            $this->profile(['email' => 'cccd-two@example.test', 'cccd' => '001200000021']),
        );

        $this->assertOneCommitAndOneDomainFailure($results, 'NV_CCCD_DUPLICATE');
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM nhan_vien WHERE cccd = '001200000021'"
        )->fetchColumn());
    }

    private function runRace(array $firstProfile, array $secondProfile): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'nv-race-');
        if ($barrier === false) {
            throw new RuntimeException('Unable to reserve worker barrier.');
        }
        unlink($barrier);
        $firstReady = tempnam(sys_get_temp_dir(), 'nv-ready-');
        $secondReady = tempnam(sys_get_temp_dir(), 'nv-ready-');
        if ($firstReady === false || $secondReady === false) {
            throw new RuntimeException('Unable to reserve worker ready markers.');
        }
        unlink($firstReady);
        unlink($secondReady);

        try {
            $first = $this->newWorker($this->workerEnvironment($firstProfile, $barrier, $firstReady));
            $second = $this->newWorker($this->workerEnvironment($secondProfile, $barrier, $secondReady));
            $first->start();
            $second->start();

            $deadline = microtime(true) + 15;
            while (! (is_file($firstReady) && is_file($secondReady))) {
                if (! $first->isRunning() || ! $second->isRunning()) {
                    throw new RuntimeException('A worker exited before the ready handshake completed.');
                }
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Worker ready handshake timed out.');
                }
                usleep(10_000);
            }

            if (! $first->isRunning() || ! $second->isRunning()) {
                throw new RuntimeException('A worker exited after ready but before barrier release.');
            }

            file_put_contents($barrier, 'go');
            $first->wait();
            $second->wait();

            return [$this->workerResult($first), $this->workerResult($second)];
        } finally {
            if (is_file($barrier)) {
                unlink($barrier);
            }
            foreach ([$firstReady, $secondReady] as $ready) {
                if (is_file($ready)) {
                    unlink($ready);
                }
            }
        }
    }

    private function profile(array $overrides = []): array
    {
        return array_values(array_replace([
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'employee@example.test',
            'ngay_vao_lam' => '2026-08-12',
            'ma_pb' => $this->department,
            'ma_cv' => $this->position,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => $this->workingStatus,
            'password_hash' => '$2y$12$'.str_repeat('x', 53),
            'avatar_path' => null,
        ], $overrides));
    }

    private function workerEnvironment(array $profile, ?string $barrier = null, ?string $ready = null): array
    {
        $testEnvironment = DisposableMariaDbGuard::environment();

        return [
            'MARIADB_TEST_ENABLED' => '1',
            'MARIADB_TEST_HOST' => $testEnvironment['host'],
            'MARIADB_TEST_PORT' => $testEnvironment['port'],
            'MARIADB_TEST_USERNAME' => $testEnvironment['username'],
            'MARIADB_TEST_PASSWORD' => $testEnvironment['password'],
            'MARIADB_TEST_DATABASE' => $this->databaseName(),
            'MARIADB_TEST_BARRIER' => $barrier ?? base_path('composer.json'),
            'MARIADB_TEST_READY' => $ready ?? base_path('composer.lock'),
            'MARIADB_TEST_PROFILE' => json_encode($profile, JSON_THROW_ON_ERROR),
            'DB_DATABASE' => 'quan_ly_nhan_su',
            'DB_HOST' => '203.0.113.253',
        ];
    }

    private function newWorker(array $environment): Process
    {
        return new Process(
            [PHP_BINARY, base_path('tests/Support/MariaDbEmployeeCreateWorker.php')],
            base_path(),
            $environment,
            timeout: 30,
        );
    }

    private function workerResult(Process $process): array
    {
        $json = $process->isSuccessful() ? $process->getOutput() : $process->getErrorOutput();
        $result = json_decode($json, true);
        $this->assertIsArray($result, "Worker returned invalid JSON: {$json}");

        return $result;
    }

    private function assertOneCommitAndOneDomainFailure(array $results, string $error): void
    {
        $successes = array_values(array_filter($results, fn (array $result): bool => $result['ok'] === true));
        $failures = array_values(array_filter($results, fn (array $result): bool => $result['ok'] === false));

        $this->assertCount(1, $successes);
        $this->assertCount(1, $failures);
        $this->assertSame($error, $failures[0]['error']);
    }
}
