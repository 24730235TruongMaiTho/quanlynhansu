<?php

namespace Tests\Integration\MariaDb;

use PDO;
use Symfony\Component\Process\Process;

final class EmployeeAcceptanceEnvironmentTest extends MariaDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'tests/Fixtures/MariaDb/employee_legacy_schema.sql',
            'database/sql/employee/2026_08_12_001_schema.sql',
            'database/sql/employee/2026_08_12_002_read_routines.sql',
            'database/sql/employee/2026_08_12_003_create_routines.sql',
            'database/sql/employee/2026_08_12_004_update_routines.sql',
            'database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql',
            'database/sql/employee/2026_08_12_006_rbac.sql',
        ] as $path) {
            $this->runSql(base_path($path));
        }
    }

    public function test_create_and_drop_cli_uses_a_guarded_database_and_cleans_it(): void
    {
        $createEnvironment = $this->acceptanceEnvironment(null);
        $createdDatabase = null;

        try {
            $created = $this->runHelper('create', $createEnvironment);
            $createdDatabase = $created['database'] ?? null;
            $this->assertIsString($createdDatabase);
            $this->assertMatchesRegularExpression(
                '/\Aquan_ly_nhan_su_employee_test_[a-f0-9]{12}\z/',
                $createdDatabase,
            );
            $this->assertSame(1, $this->databaseExists($createdDatabase));

            $this->runHelper('drop', $this->acceptanceEnvironment($createdDatabase));
            $this->assertSame(0, $this->databaseExists($createdDatabase));
        } finally {
            if (is_string($createdDatabase) && $this->databaseExists($createdDatabase) === 1) {
                $this->dropDatabase($createdDatabase);
            }
        }
    }

    public function test_create_drops_the_generated_database_after_a_mid_script_failure(): void
    {
        $before = $this->guardedSchemaCount();
        $environment = $this->acceptanceEnvironment(null);
        $environment['EMPLOYEE_ACCEPTANCE_TEST_FAIL_SCRIPT'] = '4';

        $this->runHelperExpectFailure('create', $environment);

        $this->assertSame($before, $this->guardedSchemaCount());
    }

    public function test_verify_runtime_rejects_db_url_or_socket_drift_before_business_work(): void
    {
        $database = $this->databaseName();
        $valid = $this->acceptanceEnvironment($database);
        $validResult = $this->runHelper('verify-runtime', $valid);
        $this->assertTrue($validResult['ok']);

        $invalid = $valid;
        $invalid['DB_URL'] = 'mysql://wrong:wrong@127.0.0.1:3306/quan_ly_nhan_su';
        $this->runHelperExpectFailure('verify-runtime', $invalid);
        $this->assertSame(1, $this->databaseExists($database));

        $invalid = $valid;
        $invalid['DB_SOCKET'] = 'named-pipe';
        $this->runHelperExpectFailure('verify-runtime', $invalid);
        $this->assertSame(1, $this->databaseExists($database));
    }

    public function test_verify_runtime_rejects_cache_route_and_outside_database_drift(): void
    {
        $database = $this->databaseName();
        $valid = $this->acceptanceEnvironment($database);

        $configDrift = $valid;
        $configDrift['APP_CONFIG_CACHE'] = base_path('storage/framework/testing/config-drift.php');
        $this->runHelperExpectFailure('verify-runtime', $configDrift);

        $routeDrift = $valid;
        $routeDrift['APP_ROUTES_CACHE'] = base_path('storage/framework/testing/routes-drift.php');
        $this->runHelperExpectFailure('verify-runtime', $routeDrift);

        $outside = $valid;
        $outside['MARIADB_TEST_DATABASE'] = 'quan_ly_nhan_su';
        $outside['DB_DATABASE'] = 'quan_ly_nhan_su';
        $this->runHelperExpectFailure('verify-runtime', $outside);

        $this->assertSame(1, $this->databaseExists($database));
    }

    public function test_seed_roles_is_idempotent_and_changes_only_exact_fixture_roles(): void
    {
        $environment = $this->acceptanceEnvironment($this->databaseName());
        $this->runHelper('seed-roles', $environment);
        $this->runHelper('seed-roles', $environment);

        $viewRole = $this->roleId('Chỉ xem nhân viên');
        $noneRole = $this->roleId('Không có quyền nhân viên');
        $permission = (int) $this->pdo()->query(
            "SELECT ma_quyen FROM quyen WHERE ky_hieu_quyen = 'NHAN_VIEN_XEM'",
        )->fetchColumn();
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE BINARY ten_vt = BINARY 'Chỉ xem nhân viên'",
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = {$viewRole} AND ma_quyen = {$permission}",
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = {$viewRole}",
        )->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = {$noneRole}",
        )->fetchColumn());
    }

    public function test_assign_role_accepts_only_the_two_aliases_and_uses_the_guarded_target(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Acceptance department')");
        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Acceptance position', 0.00)");
        $baselineRole = (int) $this->pdo()->query(
            "SELECT ma_vt FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'",
        )->fetchColumn();
        $department = (int) $this->pdo()->query('SELECT ma_pb FROM phong_ban LIMIT 1')->fetchColumn();
        $position = (int) $this->pdo()->query('SELECT ma_cv FROM chuc_vu LIMIT 1')->fetchColumn();
        $status = (int) $this->pdo()->query("SELECT ma_tt FROM trang_thai_lam_viec WHERE ky_hieu = 'DANG_LAM'")->fetchColumn();
        $this->pdo()->prepare(
            'INSERT INTO nhan_vien (ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam, ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([
            'NV001', 'Acceptance employee', '1990-01-01', 1, '0901234567', 'acceptance@example.test',
            '2026-08-12', $department, $position, 'Kinh', '001200000001', 'Cục CSQLHC', 'Đại học',
            $status, hash('sha256', 'nhom3@2026'), $baselineRole,
        ]);
        $environment = $this->acceptanceEnvironment($this->databaseName());
        $this->runHelper('seed-roles', $environment);
        $this->runHelper('assign-role', $environment + [
            'EMPLOYEE_ACCEPTANCE_MA_NV' => 'NV001',
            'EMPLOYEE_ACCEPTANCE_ROLE_ALIAS' => 'view-only',
        ]);

        $viewRole = $this->roleId('Chỉ xem nhân viên');
        $this->assertSame($viewRole, (int) $this->pdo()->query(
            "SELECT ma_vt FROM nhan_vien WHERE ma_nv = 'NV001'",
        )->fetchColumn());

        $invalid = $environment + [
            'EMPLOYEE_ACCEPTANCE_MA_NV' => 'NV001',
            'EMPLOYEE_ACCEPTANCE_ROLE_ALIAS' => 'arbitrary-role',
        ];
        $this->runHelperExpectFailure('assign-role', $invalid);
        $this->assertSame($viewRole, (int) $this->pdo()->query(
            "SELECT ma_vt FROM nhan_vien WHERE ma_nv = 'NV001'",
        )->fetchColumn());
    }

    public function test_standalone_add_dependency_and_assign_role_build_exact_child_environment(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);

        try {
            $this->runHarness('Start', $stateFile, $environment, true);
            $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($state);
            $database = (string) $state['database'];

            $dependency = $this->runHarness('AddDependency', $stateFile, $environment, true, [
                'Employee' => 'NV001',
                'Dependency' => 'hop_dong',
            ]);
            $this->assertTrue($dependency['ok'] ?? false);
            $acceptancePdo = $this->acceptanceDatabasePdo($database);
            $this->assertSame(1, (int) $acceptancePdo->query(
                "SELECT COUNT(*) FROM hop_dong WHERE ma_nv = 'NV001'",
            )->fetchColumn());

            // The bootstrap action creates NV001 with its synthetic administrator role.
            // The guarded internal assignment procedure intentionally accepts only a
            // baseline employee, so arrange that disposable precondition before the
            // standalone AssignRole invocation.
            $baselineRole = (int) $acceptancePdo->query(
                "SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'",
            )->fetchColumn();
            $this->assertGreaterThan(0, $baselineRole);
            $update = $acceptancePdo->prepare('UPDATE nhan_vien SET ma_vt = ? WHERE ma_nv = ?');
            $update->execute([$baselineRole, 'NV001']);

            $role = $this->runHarness('AssignRole', $stateFile, $environment, true, [
                'Employee' => 'NV001',
                'RoleAlias' => 'view-only',
            ]);
            $this->assertTrue($role['ok'] ?? false);
            $viewRole = (int) $acceptancePdo->query(
                "SELECT ma_vt FROM vai_tro WHERE BINARY ten_vt = BINARY 'Chỉ xem nhân viên'",
            )->fetchColumn();
            $this->assertSame($viewRole, (int) $acceptancePdo->query(
                "SELECT ma_vt FROM nhan_vien WHERE ma_nv = 'NV001'",
            )->fetchColumn());

            $this->runHarness('Stop', $stateFile, $environment, true);
        } finally {
            if (is_file($statePath)) {
                $this->runHarness('Stop', $stateFile, $environment, false);
            }
        }

        $this->assertFileDoesNotExist($statePath);
    }

    public function test_harness_rejects_extra_action_parameters_before_claim_or_database(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $before = $this->guardedSchemaCount();
        $environment = $this->acceptanceEnvironment(null);

        $process = $this->runHarnessRaw([
            '-Action', 'Start', '-StateFile', $stateFile, '-EnableDisposableMariaDb',
            '-Employee', 'NV001',
        ], $environment);
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertFileDoesNotExist($statePath);
        $this->assertSame($before, $this->guardedSchemaCount());

        $process = $this->runHarnessRaw([
            '-Action', 'AddDependency', '-StateFile', $stateFile, '-EnableDisposableMariaDb',
            '-Employee', 'NV001', '-Dependency', 'hop_dong', '-RoleAlias', 'view-only',
        ], $environment);
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertFileDoesNotExist($statePath);
        $this->assertSame($before, $this->guardedSchemaCount());
    }

    public function test_preexisting_storage_link_is_preserved_when_state_ownership_is_tampered(): void
    {
        $publicStorage = base_path('public/storage');
        $target = base_path('storage/app/public');
        if ($this->storageEntryPresent($publicStorage)) {
            $this->markTestSkipped('The shared storage link path is not disposablely absent.');
        }
        if (! $this->createJunction($publicStorage, $target)) {
            // Some Windows providers create the junction before reporting a
            // non-zero process status; dispose that exact attempted fixture
            // before the platform skip can leak it into the next test.
            if ($this->storageEntryPresent($publicStorage)) {
                $this->removeJunction($publicStorage);
            }
            $this->markTestSkipped('The platform rejected the disposable storage junction.');
        }

        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $original = null;
        $database = null;
        $runRoot = base_path('storage/framework/testing/employee-acceptance/'.$runId);
        $failureInjected = false;
        $preservedAfterStop = false;
        $cleanupFailure = null;

        try {
            $this->runHarness('Start', $stateFile, $environment, true);
            $original = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($original);
            $database = (string) ($original['database'] ?? '');
            $this->assertFalse($original['owns_storage_link'] ?? true);
            $tampered = $original;
            $tampered['owns_storage_link'] = true;
            file_put_contents($statePath, json_encode($tampered, JSON_THROW_ON_ERROR));
            $this->runHarness('Stop', $stateFile, $environment, false);
            $this->assertFileExists($publicStorage);
            $this->assertFileExists($statePath);

            try {
                throw new \RuntimeException('TEST_INJECTED_AFTER_TAMPER');
            } catch (\RuntimeException $exception) {
                $failureInjected = true;
            }
        } finally {
            try {
                if (is_file($statePath)) {
                    if (is_array($original)) {
                        file_put_contents($statePath, json_encode($original, JSON_THROW_ON_ERROR));
                    }
                    try {
                        // A tampered Stop may legitimately report a guarded cleanup error;
                        // retain that evidence while still guaranteeing fixture disposal below.
                        $this->runHarness('Stop', $stateFile, $environment, is_array($original));
                    } catch (\Throwable $exception) {
                        $cleanupFailure = $exception;
                    }
                    $preservedAfterStop = $this->storageEntryPresent($publicStorage);
                }
            } finally {
                try {
                    if ($this->storageEntryPresent($publicStorage)) {
                        $this->removeJunction($publicStorage);
                    }
                } catch (\Throwable $exception) {
                    $cleanupFailure ??= $exception;
                }
            }
        }

        if ($cleanupFailure !== null) {
            throw $cleanupFailure;
        }

        $this->assertTrue($failureInjected);
        $this->assertTrue($preservedAfterStop);
        $this->assertFalse($this->storageEntryPresent($publicStorage));
        $this->assertFileDoesNotExist($statePath);
        $this->assertDirectoryDoesNotExist($runRoot);
        $this->assertSame(0, $this->listenerCount());
        $this->assertSame(0, $this->databaseExists((string) $database));
    }

    public function test_replaced_owned_storage_link_is_preserved_while_other_resources_clean(): void
    {
        $publicStorage = base_path('public/storage');
        if ($this->storageEntryPresent($publicStorage)) {
            $this->markTestSkipped('The shared storage link path is not disposablely absent.');
        }

        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $database = null;

        try {
            $this->runHarness('Start', $stateFile, $environment, true);
            $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
            $database = (string) $state['database'];
            $this->assertTrue($state['owns_storage_link'] ?? false);
            $this->assertTrue(is_string($state['storage_link_identity'] ?? null));
            $this->removeJunction($publicStorage);
            $this->assertTrue($this->createJunction($publicStorage, base_path('storage/app/public')));

            $this->runHarness('Stop', $stateFile, $environment, false);
            clearstatcache(true, $statePath);
            $this->assertFileExists($publicStorage);
            $this->assertFileDoesNotExist($statePath);
            $this->assertSame(0, $this->databaseExists($database));
        } finally {
            clearstatcache(true, $statePath);
            if (is_file($statePath)) {
                $this->runHarness('Stop', $stateFile, $environment, false);
            }
            if ($this->storageEntryPresent($publicStorage)) {
                $this->removeJunction($publicStorage);
            }
        }
    }

    public function test_cleanup_uploads_removes_referenced_and_orphan_files_only_in_guarded_prefix(): void
    {
        $database = $this->databaseName();
        $target = base_path('storage/app/public/nhan-vien/acceptance/'.substr($database, -12).'/avatars');
        if (! is_dir($target) && ! mkdir($target, 0777, true) && ! is_dir($target)) {
            $this->fail('Unable to create acceptance upload fixture.');
        }
        file_put_contents($target.'/referenced.jpg', 'synthetic');
        mkdir($target.'/orphan', 0777, true);
        file_put_contents($target.'/orphan/orphan.jpg', 'synthetic');

        try {
            $this->runHelper('cleanup-uploads', $this->acceptanceEnvironment($database));
            $this->assertFileDoesNotExist($target);
        } finally {
            if (is_file($target.'/referenced.jpg')) {
                unlink($target.'/referenced.jpg');
            }
            if (is_file($target.'/orphan/orphan.jpg')) {
                unlink($target.'/orphan/orphan.jpg');
            }
            if (is_dir($target.'/orphan')) {
                rmdir($target.'/orphan');
            }
            if (is_dir($target)) {
                rmdir($target);
            }
            $raceOriginal = $target.'.employee-acceptance-race-original';
            if (is_dir($raceOriginal)) {
                rmdir($raceOriginal);
            }
            $runRoot = dirname($target);
            if (is_dir($runRoot) && count(scandir($runRoot) ?: []) === 2) {
                rmdir($runRoot);
            }
        }
    }

    public function test_cleanup_uploads_rejects_a_dangling_upload_reparse_without_touching_outside(): void
    {
        $database = $this->databaseName();
        $target = base_path('storage/app/public/nhan-vien/acceptance/'.substr($database, -12).'/avatars');
        $outside = base_path('storage/app/public/nhan-vien/acceptance-outside-'.substr($database, -12));
        if (! is_dir($outside) && ! mkdir($outside, 0777, true) && ! is_dir($outside)) {
            $this->fail('Unable to create exact outside upload fixture.');
        }
        file_put_contents($outside.'/sentinel.txt', 'outside');
        $runRoot = dirname($target);
        if (! is_dir($runRoot) && ! mkdir($runRoot, 0777, true) && ! is_dir($runRoot)) {
            $this->fail('Unable to create exact upload run root.');
        }
        if (! is_dir($outside.'/missing') && ! mkdir($outside.'/missing') && ! is_dir($outside.'/missing')) {
            $this->fail('Unable to create exact dangling target fixture.');
        }
        $reparseKind = null;
        if (function_exists('symlink') && @symlink($outside.'/missing', $target)) {
            $reparseKind = 'symlink';
        } elseif ($this->createJunction($target, $outside.'/missing')) {
            $reparseKind = 'junction';
        }
        if ($reparseKind === null) {
            $this->removeExactDirectory($outside.'/missing');
            $this->removeExactDirectory($outside);
            $this->removeExactDirectory($runRoot);
            $this->markTestSkipped('The platform rejected both disposable dangling reparse fixtures.');
        }
        $this->removeExactDirectory($outside.'/missing');

        try {
            $this->runHelperExpectFailure('cleanup-uploads', $this->acceptanceEnvironment($database));
            $this->assertFileExists($outside.'/sentinel.txt');
            $this->assertSame('outside', file_get_contents($outside.'/sentinel.txt'));
        } finally {
            if ($reparseKind === 'junction') {
                $this->removeJunction($target);
            } elseif (is_link($target)) {
                unlink($target);
            }
            if (is_file($outside.'/sentinel.txt')) {
                unlink($outside.'/sentinel.txt');
            }
            if (is_dir($outside)) {
                rmdir($outside);
            }
            if (is_dir($runRoot)) {
                rmdir($runRoot);
            }
        }
    }

    public function test_cleanup_uploads_blocks_a_post_lease_path_swap_and_preserves_outside_sentinel(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('The platform does not expose symlink().');
        }

        $database = $this->databaseName();
        $runId = substr($database, -12);
        $target = base_path('storage/app/public/nhan-vien/acceptance/'.$runId.'/avatars');
        $outside = base_path('storage/app/public/nhan-vien/acceptance-swap-outside-'.$runId);
        if (! is_dir($target) && ! mkdir($target, 0777, true) && ! is_dir($target)) {
            $this->fail('Unable to create exact swap-race upload fixture.');
        }
        if (! is_dir($outside) && ! mkdir($outside, 0777, true) && ! is_dir($outside)) {
            $this->fail('Unable to create exact swap-race outside fixture.');
        }
        file_put_contents($outside.'/sentinel.txt', 'outside');

        try {
            $environment = $this->acceptanceEnvironment($database);
            $environment['EMPLOYEE_ACCEPTANCE_TEST_SWAP_RACE'] = '1';
            $this->runHelper('cleanup-uploads', $environment);
            $this->assertFileDoesNotExist($target);
            $this->assertFileExists($outside.'/sentinel.txt');
            $this->assertSame('outside', file_get_contents($outside.'/sentinel.txt'));
        } finally {
            if (is_link($target)) {
                unlink($target);
            }
            if (is_dir($target)) {
                rmdir($target);
            }
            if (is_file($outside.'/sentinel.txt')) {
                unlink($outside.'/sentinel.txt');
            }
            if (is_dir($outside)) {
                rmdir($outside);
            }
            $runRoot = dirname($target);
            if (is_dir($runRoot) && count(scandir($runRoot) ?: []) === 2) {
                rmdir($runRoot);
            }
        }
    }

    public function test_stop_rejects_extra_substring_and_reordered_argv_without_killing_the_owned_server(): void
    {
        $publicStorage = base_path('public/storage');
        if ($this->storageEntryPresent($publicStorage)) {
            $this->markTestSkipped('The shared storage link path is not disposablely absent.');
        }

        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $original = null;
        $lastCurrent = null;
        $ownedStorageLinkFixture = false;
        $cleanupFailure = null;

        try {
            $this->runHarness('Start', $stateFile, $environment, true);
            $original = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($original);
            $this->assertIsInt((int) ($original['pid'] ?? 0));
            $this->assertSame(1, $this->listenerContains((int) $original['pid']));
            $originalTokens = $original['command_tokens'];
            $variants = [
                array_merge($originalTokens, ['--extra-token']),
                array_map(static fn (string $token): string => str_replace('8012', '8012x', $token), $originalTokens),
                [$originalTokens[0], $originalTokens[1], $originalTokens[3], $originalTokens[2], $originalTokens[4]],
            ];

            $this->runHarness('Stop', $stateFile, $environment, true);

            foreach ($variants as $tokens) {
                $this->runHarness('Start', $stateFile, $environment, true);
                $ownedStorageLinkFixture = true;
                $current = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
                $this->assertIsArray($current);
                $lastCurrent = $current;
                $tampered = $original;
                $tampered['pid'] = $current['pid'];
                $tampered['process_start_utc'] = $current['process_start_utc'];
                $tampered['database'] = $current['database'];
                $tampered['run_id'] = $current['run_id'];
                $tampered['owner_marker'] = $current['owner_marker'];
                $tampered['admin_email'] = $current['admin_email'];
                $tampered['command_tokens'] = $tokens;
                $tampered['owns_storage_link'] = $current['owns_storage_link'];
                if (array_key_exists('storage_link_identity', $current)) {
                    $tampered['storage_link_identity'] = $current['storage_link_identity'];
                } else {
                    unset($tampered['storage_link_identity']);
                }
                file_put_contents($statePath, json_encode($tampered, JSON_THROW_ON_ERROR));
                $stopPayload = $this->runHarness('Stop', $stateFile, $environment, false);
                $this->assertFileDoesNotExist($statePath);
                $this->assertSame(1, $this->listenerContains((int) $current['pid']));
                $this->assertSame(0, $this->databaseExists((string) $current['database']));
                $this->assertFalse(
                    $this->storageEntryPresent($publicStorage),
                    json_encode($stopPayload, JSON_THROW_ON_ERROR),
                );
                $this->stopHarnessProcessExact($current);
                $this->assertSame(0, $this->listenerContains((int) $current['pid']));
            }
            $this->assertFileDoesNotExist($statePath);
        } finally {
            $stateNeedsOfficialStop = is_file($statePath);
            $cleanupState = $stateNeedsOfficialStop && is_array($lastCurrent) ? $lastCurrent : null;

            if ($stateNeedsOfficialStop && $cleanupState === null) {
                try {
                    $candidate = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($candidate)) {
                        $cleanupState = $candidate;
                    }
                } catch (\Throwable $exception) {
                    $cleanupFailure ??= $exception;
                }
            }

            try {
                if ($stateNeedsOfficialStop && is_array($cleanupState)) {
                    file_put_contents($statePath, json_encode($cleanupState, JSON_THROW_ON_ERROR));
                    $this->runHarness('Stop', $stateFile, $environment, true);
                }
            } catch (\Throwable $exception) {
                $cleanupFailure = $exception;
            }

            try {
                if (is_file($statePath)) {
                    unlink($statePath);
                }
            } catch (\Throwable $exception) {
                $cleanupFailure ??= $exception;
            }

            try {
                $cleanupDatabase = is_array($lastCurrent)
                    ? (string) ($lastCurrent['database'] ?? '')
                    : (is_array($cleanupState) ? (string) ($cleanupState['database'] ?? '') : '');
                if ($this->isGuardedEmployeeDatabase($cleanupDatabase) && $this->databaseExists($cleanupDatabase) === 1) {
                    $this->dropDatabase($cleanupDatabase);
                }
            } catch (\Throwable $exception) {
                $cleanupFailure ??= $exception;
            }

            try {
                if (
                    is_array($lastCurrent)
                    && $this->listenerContains((int) ($lastCurrent['pid'] ?? 0)) === 1
                ) {
                    $this->stopHarnessProcessExact($lastCurrent);
                }
            } catch (\Throwable $exception) {
                $cleanupFailure ??= $exception;
            }

            try {
                if ($ownedStorageLinkFixture && $this->storageEntryPresent($publicStorage)) {
                    $this->removeJunction($publicStorage);
                }
            } catch (\Throwable $exception) {
                $cleanupFailure ??= $exception;
            }
        }

        if ($cleanupFailure !== null) {
            throw $cleanupFailure;
        }
    }

    public function test_stop_cleans_a_valid_state_when_its_owned_run_root_was_lost(): void
    {
        $created = $this->runHelper('create', $this->acceptanceEnvironment(null));
        $database = (string) ($created['database'] ?? '');
        $this->assertMatchesRegularExpression('/\Aquan_ly_nhan_su_employee_test_[a-f0-9]{12}\z/', $database);
        $runId = substr($database, -12);
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);

        $state = [
            'schema' => 1,
            'phase' => 'started',
            'owner_marker' => bin2hex(random_bytes(16)),
            'database' => $database,
            'run_id' => $runId,
            'admin_ma_nv' => 'NV001',
            'admin_email' => 'admin-'.$runId.'@example.test',
            'port' => 8012,
            'owns_storage_link' => false,
        ];
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));

        try {
            $this->runHarness('Stop', $stateFile, $this->acceptanceEnvironment($database), false);
            $this->assertFileDoesNotExist($statePath);
            $this->assertSame(0, $this->databaseExists($database));
        } finally {
            if (is_file($statePath)) {
                unlink($statePath);
            }
            if ($this->databaseExists($database) === 1) {
                $this->dropDatabase($database);
            }
        }
    }

    public function test_start_route_marker_failure_cleans_every_owned_resource(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $environment['EMPLOYEE_ACCEPTANCE_TEST_APP_MARKER_FAIL'] = '1';
        $beforeSchemas = $this->guardedSchemaCount();

        $this->runHarness('Start', $stateFile, $environment, false);

        $this->assertFileDoesNotExist($statePath);
        $this->assertSame(0, $this->listenerCount());
        $this->assertSame($beforeSchemas, $this->guardedSchemaCount());
        $runRoot = base_path('storage/framework/testing/employee-acceptance/'.$runId);
        $this->assertDirectoryDoesNotExist($runRoot);
    }

    public function test_start_storage_link_marker_failure_cleans_the_current_invocation_link(): void
    {
        $publicStorage = base_path('public/storage');
        if ($this->storageEntryPresent($publicStorage)) {
            $this->markTestSkipped('The shared storage link path is not disposablely absent.');
        }

        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $runRoot = base_path('storage/framework/testing/employee-acceptance/'.$runId);
        $environment = $this->acceptanceEnvironment(null);
        $environment['EMPLOYEE_ACCEPTANCE_TEST_STORAGE_LINK_MARKER_FAIL'] = '1';
        $beforeSchemas = $this->guardedSchemaCount();

        try {
            $this->runHarness('Start', $stateFile, $environment, false);

            $this->assertFileDoesNotExist($statePath);
            $this->assertFalse($this->storageEntryPresent($publicStorage));
            $this->assertDirectoryDoesNotExist($runRoot);
            $this->assertSame(0, $this->listenerCount());
            $this->assertSame($beforeSchemas, $this->guardedSchemaCount());
        } finally {
            if (is_file($statePath)) {
                $this->runHarness('Stop', $stateFile, $this->acceptanceEnvironment(null), false);
            }
            if ($this->storageEntryPresent($publicStorage)) {
                $this->removeJunction($publicStorage);
            }
        }
    }

    public function test_start_bootstrap_failure_cleans_every_owned_resource(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $environment['EMPLOYEE_ACCEPTANCE_TEST_BOOTSTRAP_FAIL'] = '1';
        $beforeSchemas = $this->guardedSchemaCount();

        $this->runHarness('Start', $stateFile, $environment, false);

        $this->assertFileDoesNotExist($statePath);
        $this->assertSame(0, $this->listenerCount());
        $this->assertSame($beforeSchemas, $this->guardedSchemaCount());
        $this->assertDirectoryDoesNotExist(base_path('storage/framework/testing/employee-acceptance/'.$runId));
    }

    public function test_state_publish_is_atomic_for_a_concurrent_reader(): void
    {
        $stateFile = 'storage/framework/testing/employee-acceptance-'.bin2hex(random_bytes(6)).'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $environment['EMPLOYEE_ACCEPTANCE_TEST_STATE_CONCURRENCY'] = '1';
        $environment['EMPLOYEE_ACCEPTANCE_TEST_STATE_WRITE_DELAY_MS'] = '2';
        $state = null;

        try {
            $this->runHarness('Start', $stateFile, $environment, true);
            $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($state);
            $runId = (string) ($state['run_id'] ?? '');
            $probe = base_path('storage/framework/testing/employee-acceptance/'.$runId.'/.state-concurrency-verified');
            $lockPath = base_path('storage/framework/testing/.employee-acceptance-lock-employee-acceptance-'.$runId.'.lock');
            $this->assertFileExists($probe, 'The synchronized state-publish probe did not run.');
            $this->assertSame("passed\n", file_get_contents($probe));
        } finally {
            if (is_file($statePath)) {
                $this->runHarness('Stop', $stateFile, $environment, true);
            }
            $this->assertFileDoesNotExist($statePath);
            if (isset($lockPath)) {
                $this->assertFileDoesNotExist($lockPath);
            }
        }
    }

    public function test_state_lock_serializes_a_synchronized_concurrent_invocation(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $lockPath = base_path('storage/framework/testing/.employee-acceptance-lock-employee-acceptance-'.$runId.'.lock');
        $readyLeaf = 'employee-acceptance-lock-'.$runId.'-ready.gate';
        $releaseLeaf = 'employee-acceptance-lock-'.$runId.'-release.gate';
        $readyPath = base_path('storage/framework/testing/'.$readyLeaf);
        $releasePath = base_path('storage/framework/testing/'.$releaseLeaf);
        $firstEnvironment = $this->acceptanceEnvironment(null);
        $firstEnvironment['EMPLOYEE_ACCEPTANCE_TEST_LOCK_READY_LEAF'] = $readyLeaf;
        $firstEnvironment['EMPLOYEE_ACCEPTANCE_TEST_LOCK_RELEASE_LEAF'] = $releaseLeaf;
        $arguments = [
            'pwsh', '-NoProfile', '-File', base_path('tests/Support/employee-acceptance.ps1'),
            '-Action', 'Start', '-StateFile', $stateFile, '-EnableDisposableMariaDb',
        ];
        $first = new Process($arguments, base_path(), $firstEnvironment);
        $first->setTimeout(120);
        $first->start();
        $firstStopped = false;

        try {
            $deadline = microtime(true) + 20.0;
            while (! is_file($readyPath) && microtime(true) < $deadline) {
                if (! $first->isRunning()) {
                    break;
                }
                usleep(10_000);
            }
            $this->assertFileExists($readyPath, 'The first invocation did not publish the lock-ready gate.');

            $secondEnvironment = $this->acceptanceEnvironment(null);
            $second = new Process($arguments, base_path(), $secondEnvironment);
            $second->setTimeout(90);
            $second->run();
            $this->assertNotSame(0, $second->getExitCode());
            $secondPayload = json_decode(trim($second->getOutput()), true);
            $this->assertIsArray($secondPayload);
            $this->assertSame('STATE_LOCKED', $secondPayload['error'] ?? null);

            $release = fopen($releasePath, 'x');
            $this->assertIsResource($release);
            fwrite($release, "release\n");
            fclose($release);
            $first->wait();
            $this->assertSame(0, $first->getExitCode());
            $this->assertFileDoesNotExist($readyPath);
            $firstPayload = json_decode(trim($first->getOutput()), true);
            $this->assertIsArray($firstPayload);
            $this->assertArrayHasKey('state_file', $firstPayload);
            $this->runHarness('Stop', $stateFile, $this->acceptanceEnvironment(null), true);
            $firstStopped = true;
        } finally {
            if (! is_file($releasePath)) {
                $release = @fopen($releasePath, 'x');
                if (is_resource($release)) {
                    fwrite($release, "release\n");
                    fclose($release);
                }
            }
            if ($first->isRunning()) {
                $first->wait();
            }
            if (! $firstStopped && is_file($statePath)) {
                $this->runHarness('Stop', $stateFile, $this->acceptanceEnvironment(null), false);
            }
            if (is_file($readyPath)) {
                unlink($readyPath);
            }
            if (is_file($releasePath)) {
                unlink($releasePath);
            }
        }

        $this->assertFileDoesNotExist($statePath);
        $this->assertFileDoesNotExist($lockPath);
        $this->assertFileDoesNotExist($readyPath);
        $this->assertFileDoesNotExist($releasePath);
    }

    public function test_state_removal_detects_a_post_lease_path_swap_without_touching_outside(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $lockPath = base_path('storage/framework/testing/.employee-acceptance-lock-employee-acceptance-'.$runId.'.lock');
        $readyLeaf = 'employee-acceptance-state-swap-'.$runId.'-ready.gate';
        $releaseLeaf = 'employee-acceptance-state-swap-'.$runId.'-release.gate';
        $readyPath = base_path('storage/framework/testing/'.$readyLeaf);
        $releasePath = base_path('storage/framework/testing/'.$releaseLeaf);
        $backupPath = base_path('storage/framework/testing/.employee-acceptance-state-swap-'.$runId.'.json');
        $outsidePath = base_path('storage/framework/employee-acceptance-state-swap-outside-'.$runId.'.txt');
        $this->runHarness('Start', $stateFile, $this->acceptanceEnvironment(null), true);
        $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
        $stopEnvironment = $this->acceptanceEnvironment(null);
        $stopEnvironment['EMPLOYEE_ACCEPTANCE_TEST_STATE_SWAP_READY_LEAF'] = $readyLeaf;
        $stopEnvironment['EMPLOYEE_ACCEPTANCE_TEST_STATE_SWAP_RELEASE_LEAF'] = $releaseLeaf;
        $stopArguments = [
            'pwsh', '-NoProfile', '-File', base_path('tests/Support/employee-acceptance.ps1'),
            '-Action', 'Stop', '-StateFile', $stateFile, '-EnableDisposableMariaDb',
        ];
        $stop = new Process($stopArguments, base_path(), $stopEnvironment);
        $stop->setTimeout(120);
        $stop->start();

        try {
            $deadline = microtime(true) + 20.0;
            while (! is_file($readyPath) && microtime(true) < $deadline) {
                if (! $stop->isRunning()) {
                    break;
                }
                usleep(10_000);
            }
            $this->assertFileExists($readyPath, 'The stop invocation did not publish the state-swap gate.');
            file_put_contents($outsidePath, 'outside-sentinel');
            $this->assertTrue(rename($statePath, $backupPath));
            file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));
            $release = fopen($releasePath, 'x');
            $this->assertIsResource($release);
            fwrite($release, "release\n");
            fclose($release);
            $stop->wait();
            $this->assertNotSame(0, $stop->getExitCode());
            $payload = json_decode(trim($stop->getOutput()), true);
            $this->assertIsArray($payload);
            $this->assertFalse($payload['ok'] ?? true);
            $this->assertFileExists($statePath);
            $this->assertFileExists($backupPath);
            $this->assertSame('outside-sentinel', file_get_contents($outsidePath));
        } finally {
            if ($stop->isRunning()) {
                $release = @fopen($releasePath, 'x');
                if (is_resource($release)) {
                    fwrite($release, "release\n");
                    fclose($release);
                }
                $stop->wait();
            }
            if (is_file($statePath)) {
                unlink($statePath);
            }
            if (is_file($backupPath)) {
                unlink($backupPath);
            }
            if (is_file($readyPath)) {
                unlink($readyPath);
            }
            if (is_file($releasePath)) {
                unlink($releasePath);
            }
            if (is_file($outsidePath)) {
                unlink($outsidePath);
            }
        }
        $this->assertFileDoesNotExist($lockPath);
        $this->assertFileDoesNotExist($readyPath);
        $this->assertFileDoesNotExist($releasePath);
    }

    public function test_start_rejects_a_decoy_listener_before_claiming_state_or_database(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $beforeSchemas = $this->guardedSchemaCount();
        $decoy = $this->startDecoyServer();

        try {
            $this->runHarness('Start', $stateFile, $environment, false);
            $this->assertFileDoesNotExist($statePath);
            $this->assertSame($beforeSchemas, $this->guardedSchemaCount());
            $this->assertSame(1, $this->listenerContains($decoy['pid']));
        } finally {
            $this->stopDecoyServerExact($decoy);
            $this->assertSame(0, $this->listenerContains($decoy['pid']));
            $this->assertFileDoesNotExist($statePath);
        }
    }

    public function test_stop_rejects_tampered_phase_without_killing_or_dropping_then_stops_after_restore(): void
    {
        $runId = bin2hex(random_bytes(6));
        $stateFile = 'storage/framework/testing/employee-acceptance-'.$runId.'.json';
        $statePath = base_path($stateFile);
        $environment = $this->acceptanceEnvironment(null);
        $original = null;
        $tamperers = [
            static function (array $state): array {
                $state['phase'] = 'tampered';

                return $state;
            },
            static function (array $state): array {
                $state['owner_marker'] = str_repeat('0', 32);

                return $state;
            },
            static function (array $state): array {
                $state['run_id'] = 'abcdefabcdef';

                return $state;
            },
            static function (array $state): array {
                $state['database'] = 'quan_ly_nhan_su_employee_test_abcdefabcdef';

                return $state;
            },
        ];

        try {
            foreach ($tamperers as $tamper) {
                $this->runHarness('Start', $stateFile, $environment, true);
                $original = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
                $this->assertIsArray($original);
                file_put_contents($statePath, json_encode($tamper($original), JSON_THROW_ON_ERROR));

                $this->runHarness('Stop', $stateFile, $environment, false);
                $this->assertFileExists($statePath);
                $this->assertSame(1, $this->listenerContains((int) $original['pid']));
                $this->assertSame(1, $this->databaseExists((string) $original['database']));

                file_put_contents($statePath, json_encode($original, JSON_THROW_ON_ERROR));
                $this->runHarness('Stop', $stateFile, $environment, true);
                $this->assertFileDoesNotExist($statePath);
                $this->assertSame(0, $this->databaseExists((string) $original['database']));
            }
        } finally {
            if (is_file($statePath) && is_array($original)) {
                file_put_contents($statePath, json_encode($original, JSON_THROW_ON_ERROR));
                $this->runHarness('Stop', $stateFile, $environment, false);
                if ($this->listenerContains((int) ($original['pid'] ?? 0)) === 1) {
                    $this->stopHarnessProcessExact($original);
                }
                if ($this->databaseExists((string) ($original['database'] ?? '')) === 1) {
                    $this->dropDatabase((string) $original['database']);
                }
            }
        }
    }

    public function test_state_outside_and_reparse_paths_are_rejected_without_touching_sentinel(): void
    {
        $environment = $this->acceptanceEnvironment(null);
        $outsidePath = base_path('storage/framework/employee-acceptance-outside.json');
        file_put_contents($outsidePath, 'sentinel');
        try {
            $this->runHarness('Stop', $outsidePath, $environment, false);
            $this->assertSame('sentinel', file_get_contents($outsidePath));
        } finally {
            if (is_file($outsidePath)) {
                unlink($outsidePath);
            }
        }

        if (! function_exists('symlink')) {
            $this->markTestSkipped('The platform does not expose symlink().');
        }
        $target = base_path('storage/framework/employee-acceptance-reparse-target.json');
        $link = base_path('storage/framework/testing/employee-acceptance-deadbeef.json');
        file_put_contents($target, 'sentinel');
        if (! @symlink($target, $link)) {
            unlink($target);
            $this->markTestSkipped('The platform rejected the disposable state symlink.');
        }
        try {
            $this->runHarness('Stop', $link, $environment, false);
            $this->assertFileExists($link);
            $this->assertSame('sentinel', file_get_contents($target));
        } finally {
            if (is_link($link)) {
                unlink($link);
            }
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    /** @return array<string, mixed> */
    private function runHelper(string $action, array $environment): array
    {
        $process = new Process(
            [PHP_BINARY, base_path('tests/Support/EmployeeAcceptanceEnvironment.php'), $action],
            base_path(),
            $environment,
        );
        $process->setTimeout(60);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $payload = json_decode(trim($process->getOutput()), true);
        $this->assertIsArray($payload, 'Helper did not emit JSON.');

        return $payload;
    }

    private function runHelperExpectFailure(string $action, array $environment): void
    {
        $process = new Process(
            [PHP_BINARY, base_path('tests/Support/EmployeeAcceptanceEnvironment.php'), $action],
            base_path(),
            $environment,
        );
        $process->setTimeout(60);
        $process->run();
        $this->assertNotSame(0, $process->getExitCode());
        $payload = json_decode(trim($process->getErrorOutput()), true);
        $this->assertIsArray($payload);
        $this->assertFalse($payload['ok'] ?? true);
        $this->assertArrayHasKey('error', $payload);
    }

    /** @return array<string, mixed> */
    private function runHarness(string $action, string $stateFile, array $environment, bool $expectSuccess, array $actionParameters = []): array
    {
        $arguments = [
            'pwsh', '-NoProfile', '-File', base_path('tests/Support/employee-acceptance.ps1'),
            '-Action', $action, '-StateFile', $stateFile, '-EnableDisposableMariaDb',
        ];
        foreach ($actionParameters as $name => $value) {
            $arguments[] = '-'.$name;
            $arguments[] = $value;
        }
        $process = new Process($arguments, base_path(), $environment);
        $process->setTimeout(90);
        $process->run();
        $diagnostic = trim(implode("\n", array_filter([
            trim($process->getOutput()),
            trim($process->getErrorOutput()),
        ], static fn (string $output): bool => $output !== '')));
        if ($expectSuccess) {
            $this->assertSame(0, $process->getExitCode(), $diagnostic);
        } else {
            $this->assertNotSame(0, $process->getExitCode());
        }
        $payload = json_decode(trim($process->getOutput()), true);
        $this->assertIsArray($payload, $diagnostic);
        if ($expectSuccess) {
            if ($action === 'Start') {
                $this->assertArrayHasKey('state_file', $payload, json_encode($payload, JSON_THROW_ON_ERROR).($process->getErrorOutput()));
            } else {
                $this->assertTrue($payload['ok'] ?? false, json_encode($payload, JSON_THROW_ON_ERROR).($process->getErrorOutput()));
            }
        } else {
            $this->assertFalse($payload['ok'] ?? true);
        }

        return $payload;
    }

    private function runHarnessRaw(array $arguments, array $environment): Process
    {
        $process = new Process(
            array_merge(['pwsh', '-NoProfile', '-File', base_path('tests/Support/employee-acceptance.ps1')], $arguments),
            base_path(),
            $environment,
        );
        $process->setTimeout(60);
        $process->run();

        return $process;
    }

    private function listenerContains(int $processId): int
    {
        $process = new Process([
            'pwsh', '-NoProfile', '-Command',
            "@(Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort 8012 -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess) -contains $processId",
        ], base_path());
        $process->setTimeout(10);
        $process->run();

        return trim($process->getOutput()) === 'True' ? 1 : 0;
    }

    private function listenerCount(): int
    {
        $process = new Process([
            'pwsh', '-NoProfile', '-Command',
            "@(Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort 8012 -State Listen -ErrorAction SilentlyContinue).Count",
        ], base_path());
        $process->setTimeout(10);
        $process->run();

        return (int) trim($process->getOutput());
    }

    private function createJunction(string $path, string $target): bool
    {
        $pathLiteral = str_replace("'", "''", $path);
        $targetLiteral = str_replace("'", "''", $target);
        $process = new Process([
            'pwsh', '-NoProfile', '-Command',
            "New-Item -ItemType Junction -Path '$pathLiteral' -Target '$targetLiteral' | Out-Null",
        ], base_path());
        $process->setTimeout(10);
        $process->run();

        return $process->getExitCode() === 0;
    }

    private function removeJunction(string $path): void
    {
        $pathLiteral = str_replace("'", "''", $path);
        $process = new Process([
            'pwsh', '-NoProfile', '-Command', "[IO.Directory]::Delete('$pathLiteral', \$false)",
        ], base_path());
        $process->setTimeout(10);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    private function storageEntryPresent(string $path): bool
    {
        $pathLiteral = str_replace("'", "''", $path);
        $process = new Process([
            'pwsh', '-NoProfile', '-Command',
            "\$item=Get-Item -LiteralPath '$pathLiteral' -Force -ErrorAction SilentlyContinue;if(\$null -eq \$item){exit 1};exit 0",
        ], base_path());
        $process->setTimeout(10);
        $process->run();

        return $process->getExitCode() === 0;
    }

    private function removeExactDirectory(string $path): void
    {
        if (is_dir($path) && count(scandir($path) ?: []) === 2) {
            rmdir($path);
        }
    }

    /** @param array<string, mixed> $state */
    private function stopHarnessProcessExact(array $state): void
    {
        $pid = (int) $state['pid'];
        $executable = str_replace("'", "''", (string) $state['executable']);
        $expected = '"'.(string) $state['executable'].'" '.implode(' ', array_map('strval', $state['command_tokens']));
        $expectedLiteral = str_replace("'", "''", $expected);
        $command = "\$p=Get-CimInstance Win32_Process -Filter 'ProcessId = $pid';if(\$null -eq \$p -or \$p.ExecutablePath -cne '$executable' -or \$p.CommandLine -cne '$expectedLiteral'){throw 'PROCESS_IDENTITY_MISMATCH'};Stop-Process -Id $pid -Force";
        $process = new Process(['pwsh', '-NoProfile', '-Command', $command], base_path());
        $process->setTimeout(10);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    /** @return array{process: Process, pid: int, executable: string, tokens: list<string>, command_line: string, start_utc: string} */
    private function startDecoyServer(): array
    {
        $tokens = [
            '-S', '127.0.0.1:8012', '-t', base_path('public'),
            base_path('tests/Support/employee-acceptance-router.php'),
        ];
        $process = new Process(array_merge([PHP_BINARY], $tokens), base_path());
        $process->start();
        $deadline = microtime(true) + 10.0;
        $listenerPid = null;
        while (microtime(true) < $deadline && $listenerPid === null) {
            $listenerPid = $this->listenerOwnerPid();
            usleep(100000);
        }
        $this->assertNotNull($listenerPid, $process->getErrorOutput());
        $identity = $this->readProcessIdentity($listenerPid);
        $this->assertSame(strtolower(str_replace('/', '\\', PHP_BINARY)), strtolower(str_replace('/', '\\', $identity['executable'])));
        $this->assertStringContainsString('127.0.0.1:8012', $identity['command_line']);
        $this->assertStringContainsString(base_path('public'), $identity['command_line']);
        $this->assertStringContainsString(base_path('tests/Support/employee-acceptance-router.php'), $identity['command_line']);

        return [
            'process' => $process,
            'pid' => $listenerPid,
            'executable' => PHP_BINARY,
            'tokens' => $tokens,
            'command_line' => $identity['command_line'],
            'start_utc' => $identity['start_utc'],
        ];
    }

    private function listenerOwnerPid(): ?int
    {
        $process = new Process([
            'pwsh', '-NoProfile', '-Command',
            "@(Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort 8012 -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess | Select-Object -First 1)",
        ], base_path());
        $process->setTimeout(10);
        $process->run();
        $value = trim($process->getOutput());

        return preg_match('/\A[0-9]+\z/', $value) === 1 ? (int) $value : null;
    }

    /** @return array{executable: string, command_line: string, start_utc: string} */
    private function readProcessIdentity(int $pid): array
    {
        $process = new Process([
            'pwsh', '-NoProfile', '-Command',
            "\$p=Get-CimInstance Win32_Process -Filter 'ProcessId = $pid';\$g=Get-Process -Id $pid -ErrorAction SilentlyContinue;if(\$null -eq \$p -or \$null -eq \$g){exit 2};[pscustomobject]@{ExecutablePath=\$p.ExecutablePath;CommandLine=\$p.CommandLine;StartUtc=\$g.StartTime.ToUniversalTime().ToString('o')}|ConvertTo-Json -Compress",
        ], base_path());
        $process->setTimeout(10);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $identity = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($identity);
        $this->assertIsString($identity['ExecutablePath'] ?? null);
        $this->assertIsString($identity['CommandLine'] ?? null);
        $this->assertIsString($identity['StartUtc'] ?? null);

        return ['executable' => $identity['ExecutablePath'], 'command_line' => $identity['CommandLine'], 'start_utc' => $identity['StartUtc']];
    }

    /** @param array{process: Process, pid: int, executable: string, tokens: list<string>, command_line: string, start_utc: string} $decoy */
    private function stopDecoyServerExact(array $decoy): void
    {
        $pid = $decoy['pid'];
        $executable = str_replace("'", "''", $decoy['executable']);
        $commandLine = str_replace("'", "''", $decoy['command_line']);
        $startUtc = str_replace("'", "''", $decoy['start_utc']);
        $command = "\$p=Get-CimInstance Win32_Process -Filter 'ProcessId = $pid';\$g=Get-Process -Id $pid -ErrorAction SilentlyContinue;if(\$null -eq \$p -or \$null -eq \$g -or \$p.ExecutablePath -cne '$executable' -or \$p.CommandLine -cne '$commandLine' -or \$g.StartTime.ToUniversalTime().ToString('o') -cne '$startUtc'){throw 'PROCESS_IDENTITY_MISMATCH'};Stop-Process -Id $pid -Force";
        $stop = new Process(['pwsh', '-NoProfile', '-Command', $command], base_path());
        $stop->setTimeout(10);
        $stop->run();
        $this->assertSame(0, $stop->getExitCode(), $stop->getErrorOutput());
        $decoy['process']->wait();
    }

    /** @return array<string, string> */
    private function acceptanceEnvironment(?string $database): array
    {
        $host = (string) getenv('MARIADB_TEST_HOST');
        $port = (string) getenv('MARIADB_TEST_PORT');
        $username = (string) getenv('MARIADB_TEST_USERNAME');
        $password = (string) getenv('MARIADB_TEST_PASSWORD');
        $database ??= $this->databaseName();
        $runId = substr($database, -12);
        $configRoot = base_path('storage/framework/testing/employee-acceptance/'.$runId);

        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_URL' => 'http://127.0.0.1:8012',
            'APP_TIMEZONE' => 'Asia/Ho_Chi_Minh',
            'APP_CONFIG_CACHE' => $configRoot.'/config.php',
            'APP_ROUTES_CACHE' => $configRoot.'/routes.php',
            'DB_CONNECTION' => 'mysql',
            'DB_URL' => 'mysql://'.rawurlencode($username).':'.rawurlencode($password).'@'.$host.':'.$port.'/'.$database,
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
            'DB_SOCKET' => '',
            'DB_TIMEZONE' => '+07:00',
            'MARIADB_TEST_ENABLED' => '1',
            'MARIADB_TEST_DATABASE' => $database,
            'MARIADB_TEST_HOST' => $host,
            'MARIADB_TEST_PORT' => $port,
            'MARIADB_TEST_USERNAME' => $username,
            'MARIADB_TEST_PASSWORD' => $password,
            'NHAN_VIEN_MODULE_ENABLED' => 'true',
            'EMPLOYEE_AVATAR_PREFIX' => 'nhan-vien/acceptance/'.$runId.'/avatars',
            'EMPLOYEE_ACCEPTANCE_RUN_ID' => $runId,
            'SESSION_DRIVER' => 'cookie',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'stderr',
        ];
    }

    private function databaseExists(string $database): int
    {
        $credentials = [
            'host' => (string) getenv('MARIADB_TEST_HOST'),
            'port' => (string) getenv('MARIADB_TEST_PORT'),
            'username' => (string) getenv('MARIADB_TEST_USERNAME'),
            'password' => (string) getenv('MARIADB_TEST_PASSWORD'),
        ];
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $credentials['host'], $credentials['port']),
            $credentials['username'],
            $credentials['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
        );
        $statement->execute([$database]);

        return (int) $statement->fetchColumn();
    }

    private function isGuardedEmployeeDatabase(string $database): bool
    {
        return preg_match('/\Aquan_ly_nhan_su_employee_test_[a-f0-9]{12}\z/', $database) === 1;
    }

    private function acceptanceDatabasePdo(string $database): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('MARIADB_TEST_HOST'),
                getenv('MARIADB_TEST_PORT'),
                $database,
            ),
            getenv('MARIADB_TEST_USERNAME'),
            getenv('MARIADB_TEST_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function guardedSchemaCount(): int
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', getenv('MARIADB_TEST_HOST'), getenv('MARIADB_TEST_PORT')),
            getenv('MARIADB_TEST_USERNAME'),
            getenv('MARIADB_TEST_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $statement = $pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME REGEXP '^quan_ly_nhan_su_employee_test_[a-f0-9]{12}$'");

        return (int) $statement->fetchColumn();
    }

    private function dropDatabase(string $database): void
    {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                getenv('MARIADB_TEST_HOST'),
                getenv('MARIADB_TEST_PORT'),
            ),
            getenv('MARIADB_TEST_USERNAME'),
            getenv('MARIADB_TEST_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $pdo->exec('DROP DATABASE IF EXISTS `'.str_replace('`', '``', $database).'`');
    }

    private function roleId(string $name): int
    {
        $statement = $this->pdo()->prepare('SELECT ma_vt FROM vai_tro WHERE BINARY ten_vt = BINARY ?');
        $statement->execute([$name]);

        return (int) $statement->fetchColumn();
    }
}
